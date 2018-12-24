<?php
class term_ui
{
    private static $_instance;
    public function instance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance =  new self;
        }
        return self::$_instance;
    }
    private $taxonomy;
    private $term;
    public function __construct()
    {
        add_action('load-edit-tags.php', [$this, 'load_edit_tags'], PHP_INT_MAX);
        add_action('load-term.php', [$this, 'load_term']);
    }

    //edit-tags.php
    public function load_edit_tags()
    {
        //$this->edit_tags();
        $this->taxonomy = $_REQUEST['taxonomy'];
        add_action('created_term', function ($term_id) {
            if ($_POST['action'] == 'add-tag') {
                $this->term = $term_id;
            }
        });
        add_filter('redirect_term_location', function ($location, $tax) {
            if ($_POST['action'] == 'add-tag') {
                if ($this->term) {
                    $location = get_edit_term_link($this->term, $_POST['taxonomy'], $_POST['post_type']);
                }
            }
            //		if($_POST['action'] != 'add-tag' || empty($_POST['tag-name'])) return $location;
            //		$term = get_term_by('name', $_POST['tag-name'], $_POST['taxonomy']);
            //		if($term->term_id) $location = get_edit_term_link( $term, $_POST['taxonomy'], $_POST['post_type'] );
            return $location;
        }, 10, 2);
        if ($GLOBALS['pagenow'] !== 'edit-tags.php') {
            return;
        } //prevent running on term.php!


        if (isset($_REQUEST['new'])) {
            $this->term_new();
            exit;
        }
        add_action('admin_head', [$this, 'admin_head_edit_tags']);
        add_action('admin_footer', [$this, 'admin_footer_edit_tags']);
        if (!taxonomy_supports($this->taxonomy, 'editor')) {
            add_filter('manage_edit-'.$this->taxonomy.'_columns', function ($columns) {
                unset($columns['description']);
                return $columns;
            });
        }
    }

    public function admin_head_edit_tags()
    {
        ?><style>
			#col-left, .page-title-action {display:none}
			#col-right {
				float:none;
				width:100%;
			}
			</style><?php
    }
    public function admin_footer_edit_tags()
    {
        ?>
			<a href="<?php echo add_query_arg(['new'=>'','taxonomy'=>$this->taxonomy], 'edit-tags.php') ?>" class="page-title-action"><?php echo _x('Add New', 'post') ?></a>
			<script>
				jQuery(document).ready( function($) {
					$('#col-left').remove();
					jQuery('.page-title-action').detach().appendTo('h1').show();
				});
			</script><?php
    }

    public function description_field_remove()
    {
        ?><script>
    jQuery('.form-field.term-description-wrap').remove();
    </script><?php
    }

    //term.php
    public function load_term()
    {
        $this->taxonomy = $_REQUEST['taxonomy'];
        if (!taxonomy_supports($this->taxonomy, 'editor')) {
            add_action($this->taxonomy."_edit_form_fields", [$this, 'description_field_remove']);
        }
        add_action('admin_footer', function () {
            ?>
  <a href="<?php echo add_query_arg(['new'=>'','taxonomy'=>$this->taxonomy], 'edit-tags.php') ?>" class="page-title-action"><?php echo _x('Add New', 'post') ?></a>
  <script>
    jQuery(document).ready( function($) {
      jQuery('h1').addClass('wp-heading-inline');
      jQuery('.page-title-action').detach().appendTo('h1').show();
    });
  </script><?php
});
    }

    public function term_new()
    {
        $tax      = get_taxonomy($this->taxonomy);
        $taxonomy = $tax->name;

        if (! in_array($taxonomy, get_taxonomies(array( 'show_ui' => true ))) ||
     ! current_user_can('manage_categories')
) {
            wp_die(
        '<h1>' . __('You need a higher level of permission.') . '</h1>' .
        '<p>' . __('Sorry, you are not allowed to edit this item.') . '</p>',
        403
    );
        }

        $post_type = get_current_screen()->post_type;

        // Default to the first object_type associated with the taxonomy if no post type was passed.
        if (empty($post_type)) {
            $post_type = reset($tax->object_type);
        }

        if ('post' != $post_type) {
            $parent_file  = ('attachment' == $post_type) ? 'upload.php' : "edit.php?post_type=$post_type";
            $submenu_file = "edit-tags.php?taxonomy=$taxonomy&amp;post_type=$post_type";
        } elseif ('link_category' == $taxonomy) {
            $parent_file  = 'link-manager.php';
            $submenu_file = 'edit-tags.php?taxonomy=link_category';
        } else {
            $parent_file  = 'edit.php';
            $submenu_file = "edit-tags.php?taxonomy=$taxonomy";
        }

        get_current_screen()->set_screen_reader_content(array(
    'heading_pagination' => $tax->labels->items_list_navigation,
    'heading_list'       => $tax->labels->items_list,
));
        //wp_enqueue_script( 'admin-tags' );
        require_once(ABSPATH . 'wp-admin/admin-header.php');
        //include( ABSPATH . 'wp-admin/edit-tag-form.php' );
        $this->add_tag_form();
        include(ABSPATH . 'wp-admin/admin-footer.php');
    }




    public function add_tag_form()
    {
        $tax      = get_taxonomy($this->taxonomy);
        $taxonomy = $tax->name;
        $title    = $tax->labels->add_new_item; ?>
<div class="wrap">
<h1><?php echo $title; ?></h1>

<?php if ($message) : ?>
<div id="message" class="updated">
	<p><strong><?php echo $message; ?></strong></p>
	<?php if ($wp_http_referer) {
            ?>
	<p><a href="<?php echo esc_url(wp_validate_redirect(esc_url_raw($wp_http_referer), admin_url('term.php?taxonomy=' . $taxonomy))); ?>"><?php
        echo esc_html($tax->labels->back_to_items); ?></a></p>
	<?php
        } ?>
</div>
<?php endif; ?>

<div id="ajax-response"></div>

<?php

if (current_user_can($tax->cap->edit_terms)) {
    if ('category' == $taxonomy) {
        /**
         * Fires before the Add Category form.
         *
         * @since 2.1.0
         * @deprecated 3.0.0 Use {$taxonomy}_pre_add_form instead.
         *
         * @param object $arg Optional arguments cast to an object.
         */
        do_action('add_category_form_pre', (object) array( 'parent' => 0 ));
    } elseif ('link_category' == $taxonomy) {
        /**
         * Fires before the link category form.
         *
         * @since 2.3.0
         * @deprecated 3.0.0 Use {$taxonomy}_pre_add_form instead.
         *
         * @param object $arg Optional arguments cast to an object.
         */
        do_action('add_link_category_form_pre', (object) array( 'parent' => 0 ));
    } else {
        /**
         * Fires before the Add Tag form.
         *
         * @since 2.5.0
         * @deprecated 3.0.0 Use {$taxonomy}_pre_add_form instead.
         *
         * @param string $taxonomy The taxonomy slug.
         */
        do_action('add_tag_form_pre', $taxonomy);
    }

    /**
     * Fires before the Add Term form for all taxonomies.
     *
     * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
     *
     * @since 3.0.0
     *
     * @param string $taxonomy The taxonomy slug.
     */
    do_action("{$taxonomy}_pre_add_form", $taxonomy); ?>

<form id="addtag" method="post" action="edit-tags.php" class="validate"<?php
/**
 * Fires inside the Add Tag form tag.
 *
 * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
 *
 * @since 3.7.0
 */
do_action("{$taxonomy}_term_new_form_tag"); ?>>
<input type="hidden" name="action" value="add-tag" />
<input type="hidden" name="screen" value="<?php echo esc_attr($current_screen->id); ?>" />
<input type="hidden" name="taxonomy" value="<?php echo esc_attr($taxonomy); ?>" />
<input type="hidden" name="post_type" value="<?php echo esc_attr($post_type); ?>" />
<?php wp_nonce_field('add-tag', '_wpnonce_add-tag'); ?>

<?php do_action("{$taxonomy}_term_add_form_top", $taxonomy) ?>
<div class="form-wrap">
<div class="form-field form-required term-name-wrap">
	<label for="tag-name"><?php _ex('Name', 'term name'); ?></label>
	<input name="tag-name" id="tag-name" type="text" value="" size="40" aria-required="true" />
	<p><?php _e('The name is how it appears on your site.'); ?></p>
</div>
<?php if (! global_terms_enabled()) : ?>
<div class="form-field term-slug-wrap">
	<label for="tag-slug"><?php _e('Slug'); ?></label>
	<input name="slug" id="tag-slug" type="text" value="" size="40" />
	<p><?php _e('The &#8220;slug&#8221; is the URL-friendly version of the name. It is usually all lowercase and contains only letters, numbers, and hyphens.'); ?></p>
</div>
<?php endif; // global_terms_enabled()?>
<?php if (is_taxonomy_hierarchical($taxonomy)) : ?>
<div class="form-field term-parent-wrap">
	<label for="parent"><?php echo esc_html($tax->labels->parent_item); ?></label>
	<?php
    $dropdown_args = array(
        'hide_empty'       => 0,
        'hide_if_empty'    => false,
        'taxonomy'         => $taxonomy,
        'name'             => 'parent',
        'orderby'          => 'name',
        'hierarchical'     => true,
        'show_option_none' => __('None'),
    );

    /**
     * Filters the taxonomy parent drop-down on the Edit Term page.
     *
     * @since 3.7.0
     * @since 4.2.0 Added `$context` parameter.
     *
     * @param array  $dropdown_args {
     *     An array of taxonomy parent drop-down arguments.
     *
     *     @type int|bool $hide_empty       Whether to hide terms not attached to any posts. Default 0|false.
     *     @type bool     $hide_if_empty    Whether to hide the drop-down if no terms exist. Default false.
     *     @type string   $taxonomy         The taxonomy slug.
     *     @type string   $name             Value of the name attribute to use for the drop-down select element.
     *                                      Default 'parent'.
     *     @type string   $orderby          The field to order by. Default 'name'.
     *     @type bool     $hierarchical     Whether the taxonomy is hierarchical. Default true.
     *     @type string   $show_option_none Label to display if there are no terms. Default 'None'.
     * }
     * @param string $taxonomy The taxonomy slug.
     * @param string $context  Filter context. Accepts 'new' or 'edit'.
     */
    $dropdown_args = apply_filters('taxonomy_parent_dropdown_args', $dropdown_args, $taxonomy, 'new');

    wp_dropdown_categories($dropdown_args); ?>
	<?php if ('category' == $taxonomy) : ?>
		<p><?php _e('Categories, unlike tags, can have a hierarchy. You might have a Jazz category, and under that have children categories for Bebop and Big Band. Totally optional.'); ?></p>
	<?php else : ?>
		<p><?php _e('Assign a parent term to create a hierarchy. The term Jazz, for example, would be the parent of Bebop and Big Band.'); ?></p>
	<?php endif; ?>
</div>
<?php endif; // is_taxonomy_hierarchical()?>
<?php if (taxonomy_supports($taxonomy, 'editor')) : ?>
<div class="form-field term-description-wrap">
	<label for="tag-description"><?php _e('Description'); ?></label>
	<textarea name="description" id="tag-description" rows="5" cols="40"></textarea>
	<p><?php _e('The description is not prominent by default; however, some themes may show it.'); ?></p>
</div>
<?php endif ?>
<?php
if (! is_taxonomy_hierarchical($taxonomy)) {
        /**
         * Fires after the Add Tag form fields for non-hierarchical taxonomies.
         *
         * @since 3.0.0
         *
         * @param string $taxonomy The taxonomy slug.
         */
        do_action('add_tag_form_fields', $taxonomy);
    }

    /**
     * Fires after the Add Term form fields.
     *
     * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
     *
     * @since 3.0.0
     *
     * @param string $taxonomy The taxonomy slug.
     */
    do_action("{$taxonomy}_add_form_fields", $taxonomy); ?>
</div><!-- /form-wrap -->
<?php
submit_button($tax->labels->add_new_item);

    if ('category' == $taxonomy) {
        /**
         * Fires at the end of the Edit Category form.
         *
         * @since 2.1.0
         * @deprecated 3.0.0 Use {$taxonomy}_add_form instead.
         *
         * @param object $arg Optional arguments cast to an object.
         */
        do_action('edit_category_form', (object) array( 'parent' => 0 ));
    } elseif ('link_category' == $taxonomy) {
        /**
         * Fires at the end of the Edit Link form.
         *
         * @since 2.3.0
         * @deprecated 3.0.0 Use {$taxonomy}_add_form instead.
         *
         * @param object $arg Optional arguments cast to an object.
         */
        do_action('edit_link_category_form', (object) array( 'parent' => 0 ));
    } else {
        /**
         * Fires at the end of the Add Tag form.
         *
         * @since 2.7.0
         * @deprecated 3.0.0 Use {$taxonomy}_add_form instead.
         *
         * @param string $taxonomy The taxonomy slug.
         */
        do_action('add_tag_form', $taxonomy);
    }

    /**
     * Fires at the end of the Add Term form for all taxonomies.
     *
     * The dynamic portion of the hook name, `$taxonomy`, refers to the taxonomy slug.
     *
     * @since 3.0.0
     *
     * @param string $taxonomy The taxonomy slug.
     */
    do_action("{$taxonomy}_add_form", $taxonomy); ?>
</form>
<?php
} ?>

</div><!-- /wrap -->

<?php
    }
} //class
term_ui::instance();
