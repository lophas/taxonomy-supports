<?php
class term_thumbnail_support
{
    private static $_instance;
    public static function instance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance =  new self;
        }
        return self::$_instance;
    }
    public function __construct()
    {
        add_action('admin_init', [$this, 'load_edit_tags']);
        add_action('wp_head', [$this, 'wp_admin'], 1);
        add_action('load-term.php', [$this, 'load_term']);
        add_action('load-edit-tags.php', [$this, 'load_term']);
        add_action('created_term', [$this, 'created_term']);
        add_action('edited_terms', [$this, 'edited_terms']);
        add_filter('taxonomy_supports_defaults', function ($supports, $taxonomy, $taxonomy_object) {
            //			if($taxonomy_object['hierarchical']) $supports[] = 'thumbnail';
            if (in_array('editor', $supports)) {
                $supports[] = 'thumbnail';
            }
            return $supports;
        }, 11, 3);
        add_filter('taxonomy_supports_options', function ($supports) {
            $supports[] = 'thumbnail';
            return $supports;
        });
    }
    public function wp_admin()
    {
        if (!$object = get_queried_object()) {
            return;
        }
        if (!$taxonomy = $object->taxonomy) {
            return;
        }
        if (!taxonomy_supports($taxonomy, 'thumbnail')) {
            return;
        }
        if (!$thumbnail_id = get_term_meta($object->term_id, '_thumbnail_id', true)) {
            return;
        }
        if (!$src = wp_get_attachment_url($thumbnail_id)) {
            return;
        }
        echo '<meta property="og:image" content="'.esc_url($src).'" />'."\n";
    }

    //term.php
    public function created_term($term_id)
    {
        $term = get_term($term_id);
        if (!taxonomy_supports($term->taxonomy, 'thumbnail')) {
            return;
        }
        if (!empty($_REQUEST['_thumbnail_id'])) {
            if ($_REQUEST['_thumbnail_id'] > 0) {
                add_term_meta($term_id, '_thumbnail_id', $_REQUEST['_thumbnail_id']);
            }
        }
    }
    public function edited_terms($term_id)
    {
        $term = get_term($term_id);
        if (!taxonomy_supports($term->taxonomy, 'thumbnail')) {
            return;
        }
        if (!empty($_REQUEST['_thumbnail_id'])) {
            if ($_REQUEST['_thumbnail_id'] > 0) {
                update_term_meta($term_id, '_thumbnail_id', $_REQUEST['_thumbnail_id']);
            } else {
                delete_term_meta($term_id, '_thumbnail_id');
            }
        }
    }

    public function load_term()
    {
        if (!taxonomy_supports($_REQUEST['taxonomy'], 'thumbnail') || !taxonomy_supports($_REQUEST['taxonomy'], 'editor') || !current_user_can('upload_files')) {
            return;
        }
        //	if($GLOBALS['pagenow'] == 'edit-tags.php' && !isset($_REQUEST['new'])) return;
        add_action('add_termmeta_boxes', function () {
            add_meta_box('postimagediv', esc_html_x('Set featured image', 'post'), [$this,'term_thumbnail_meta_box'], null, 'side', 'low');
            add_action('admin_footer', [$this, 'admin_footer']);
        });
    }

    public function term_thumbnail_meta_box($term = null)
    {
        $thumbnail_id = $term ? get_term_meta($term->term_id, '_thumbnail_id', true) : 0;

        $_wp_additional_image_sizes = wp_get_additional_image_sizes();
        //echo "<input type='hidden' id='post_ID' name='post_ID' value='28765' />";
        //	$term               = get_term( $term );
        $set_thumbnail_link = '<p class="hide-if-no-js"><a href="%s" id="%s"%s class="thickbox">%s</a></p>';
        //	$upload_iframe_src  = $this->get_upload_iframe_src( 'image', $term->term_id );
        //	add_filter( "image_upload_iframe_src", [$this, 'image_upload_iframe_src'] );
        $upload_iframe_src  = get_upload_iframe_src('image', $term->term_id);
        //	remove_filter( "image_upload_iframe_src", [$this, 'image_upload_iframe_src'] );


        $thumbnail_html = esc_html_x('Set featured image', 'post');
        $thumbnail_aria = '';
        $style = ' style="display:none"';
        $tid = -1;
        if ($thumbnail_id && get_post($thumbnail_id)) {
            $size = isset($_wp_additional_image_sizes['post-thumbnail']) ? 'post-thumbnail' : array( 266, 266 );

            $size = apply_filters('admin_term_thumbnail_size', $size, $thumbnail_id, $term);

            $thumbnail_html = wp_get_attachment_image($thumbnail_id, $size);
            $thumbnail_aria = ' aria-describedby="set-term-thumbnail-desc"';
            $style = '';
            $tid = $thumbnail_id;
        }
        $content = sprintf(
        $set_thumbnail_link,
        esc_url($upload_iframe_src),
        'set-term-thumbnail',
        $thumbnail_aria, // Empty when there's no featured image set, `aria-describedby` attribute otherwise.
        $thumbnail_html
    );
        $content .= '<p class="hide-if-no-js howto" id="set-term-thumbnail-desc"'.$style.'>' . __('Click the image to edit or update') . '</p>';
        $content .= '<p class="hide-if-no-js"><a id="remove-term-thumbnail"'.$style.'>' . esc_html_x('Remove featured image', 'post') . '</a></p>';


        $content .= '<input type="hidden" id="_thumbnail_id" name="_thumbnail_id" value="' . esc_attr($tid) . '" />';

        echo apply_filters('admin_term_thumbnail_html', $content, $term->term_id, $thumbnail_id);
    }
    public function admin_footer()
    {
        ?>
<script>
jQuery(document).ready(function($){
			$('#postimagediv').on( 'click', '#set-term-thumbnail', function( event ) {
				event.preventDefault();
				// Stop propagation to prevent thickbox from activating.
				event.stopPropagation();
				wp.media.view.settings.post.featuredImageId = parseInt($('#_thumbnail_id').val());
				e = wp.media.featuredImage.frame();
				e.open();
				browser = $(e.modal.$el.context);
				button = browser.find('.media-button-select');
				button.unbind('click');
				button.click(function() {
					selected = browser.find('ul.attachments li.selected');
					if(selected.length == 1) {
						$('#_thumbnail_id').val(selected.attr('data-id'));
						$('#set-term-thumbnail').html(browser.find('.attachment-info .thumbnail-image').html());
						$('#set-term-thumbnail').attr('aria-describedby',"set-term-thumbnail-desc");
						$('#set-term-thumbnail-desc').show();
						$('#remove-term-thumbnail').show();
						e.close();
					}
				});
			}).on( 'click', '#remove-term-thumbnail', function() {
				$('#_thumbnail_id').val(-1);
				$('#set-term-thumbnail').html('<?php echo esc_html_x('Set featured image', 'post') ?>');
				$('#set-term-thumbnail').removeAttr('aria-describedby');
				$('#set-term-thumbnail-desc').hide();
				$('#remove-term-thumbnail').hide();
			});
});
</script>
<?php
    }


    //edit-tags.php
    public function load_edit_tags()
    {
        if ($GLOBALS['pagenow'] !== 'edit-tags.php' && !defined('DOING_AJAX')) {
            return;
        } //prevent running on term.php!
        if (!taxonomy_supports($_REQUEST['taxonomy'], 'thumbnail') || !taxonomy_supports($_REQUEST['taxonomy'], 'editor')) {
            return;
        }
        add_action('admin_head', [$this, 'admin_head']);
        add_filter('manage_edit-'.$_REQUEST['taxonomy'].'_columns', [$this, 'manage_edit_columns']);
        add_filter('manage_'.$_REQUEST['taxonomy'].'_custom_column', [$this, 'manage_custom_column'], 10, 3);
    } //load_edit_tags

    public function admin_head()
    {
        ?><style>
			.column-thumbnail img.attachment-thumbnail {
    			object-fit: cover;
			}
			.column-thumbnail {width: 100px;};
		</style><?php
    }
    public function manage_edit_columns($columns)
    {
        $columns['thumbnail'] = __('Thumbnail');
        return $columns;
    }
    public function manage_custom_column($content, $column_name, $term_id)
    {
        if ($column_name == 'thumbnail') {
            if ($thumbnail_id = get_term_meta($term_id, '_thumbnail_id', true)) {
                echo wp_get_attachment_image($thumbnail_id, [80,80], false);
            }
        }
    }
} //term_thumbnail_support
term_thumbnail_support::instance();
