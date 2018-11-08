<?php
class term_editor_support
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
    private $psudeocount = [];
    public function __construct()
    {
        add_action('wp_head', [$this, 'wp_admin'], 1);
        add_filter('taxonomy_supports_defaults', function ($supports, $taxonomy, $taxonomy_object) {
            if ($taxonomy_object['hierarchical']) {
                $supports[] = 'editor';
            }
            return $supports;
        }, 10, 3);
        add_filter('taxonomy_supports_options', function ($supports) {
            $supports[] = 'editor';
            return $supports;
        });
        add_action('load-edit-tags.php', [$this, 'load_edit_tags'], PHP_INT_MAX);
        add_action('load-term.php', [$this, 'load_term']);
    }
    public function wp_admin()
    {
        if (!$object = get_queried_object()) {
            return;
        }
        if (!$taxonomy = $object->taxonomy) {
            return;
        }
        if (!taxonomy_supports($taxonomy, 'editor')) {
            return;
        }
        if (!$description = trim(strip_tags(term_description($object->term_id)))) {
            return;
        }
        $description = wp_trim_words($description, 25);
        echo '<meta property="og:description" content="' . esc_attr($description) . '" />'."\n";
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
            $this->load_postbox();
            add_action('admin_head', [$this, 'admin_head']);
            add_action('add_termmeta_boxes_'.$this->taxonomy, [$this, 'catch_callbacks'], PHP_INT_MAX); //hijack existing pseudo metaboxes at the very end
        		add_action($this->taxonomy."_term_add_form_top", [$this, 'form_top'], PHP_INT_MAX); //catch_fields start
        		add_action($this->taxonomy."_add_form_fields", [$this, 'add_form_fields'], PHP_INT_MAX); //catch the fields
        		add_screen_option('layout_columns', array('max' => 2, 'default' => 2));
            if (wp_is_mobile()) {
                wp_enqueue_script('jquery-touch-punch');
            }
            $this->term_new();
            exit;
        }

        if (taxonomy_supports($this->taxonomy, 'editor')) {
            add_filter('terms_clauses', [$this, 'search_description'], 10, 3);
            add_action('admin_head', [$this, 'admin_head_edit_tags']);
            add_action('admin_footer', [$this, 'admin_footer_edit_tags']);
        } else {
            add_action($this->taxonomy."_add_form_fields", [$this, 'description_field_remove']);
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

    public function search_description($clauses, $taxonomies, $args)
    {
        if (empty($args['search']) || strpos($clauses['where'], 'tt.description') !== false) {
            return $clauses;
        }
        global $wpdb;
        //	if(!preg_match('/\(t\.name LIKE \'({[^}]+})?([^{\']+)({[^}]+})?\'\)/', $clauses['where'], $match)) return $clauses;
        //	$like = '%' . $wpdb->esc_like( $match[2] ) . '%';
        if (!preg_match('/\(t\.name LIKE \'[^\']+\'\)/', $clauses['where'], $match)) {
            return $clauses;
        }
        $like = '%' . $wpdb->esc_like($args['search']) . '%';
        $sql = $wpdb->prepare('(tt.description LIKE %s)', $like);
        $clauses['where'] = str_replace($match[0], $match[0].' OR '.$sql, $clauses['where']);
        return $clauses;
    }

    //term.php
    public function load_term()
    {
        $this->taxonomy = $_REQUEST['taxonomy'];
        if (!taxonomy_supports($this->taxonomy, 'editor')) {
            add_action($this->taxonomy."_edit_form_fields", [$this, 'description_field_remove']);
        } else {
            $this->term = get_term($_REQUEST['tag_ID'], $this->taxonomy);
            $this->load_postbox();
            add_action('admin_head', [$this, 'admin_head']);
            add_action('add_termmeta_boxes_'.$this->taxonomy, [$this, 'catch_callbacks'], PHP_INT_MAX); //hijack existing pseudo metaboxes at the very end
        		add_action($this->taxonomy."_term_edit_form_top", [$this, 'form_top'], PHP_INT_MAX); //catch_fields start
        		add_action($this->taxonomy."_edit_form_fields", [$this, 'edit_form_fields'], PHP_INT_MAX, 2); //catch the fields
        		add_screen_option('layout_columns', array('max' => 2, 'default' => 2));
            if (wp_is_mobile()) {
                wp_enqueue_script('jquery-touch-punch');
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
        //delete_user_option( get_current_user_id(), "meta-box-order_edit-".$this->taxonomy, true );
    }

    public function admin_head()
    {
      add_meta_box('editor-content', __('Description'), [$this, 'description_metabox'], null, 'normal', 'core'); //wysiwyg editor description
			add_meta_box('submitdiv', __('Publish'), [$this, 'submitdiv_metabox'], null, 'side', 'core'); //Submit box
			add_meta_box('fieldbox-slug', __('Slug'), '__return_false', null, 'side', 'core');
			if(is_taxonomy_hierarchical($this->taxonomy)) add_meta_box('fieldbox-parent', get_taxonomy($this->taxonomy)->labels->parent_item, '__return_false', null, 'side', 'core');

      $fixdir = __DIR__.'/editor-fixes';
    	if (is_dir($fixdir)) {
        foreach (scandir($fixdir) as $file) {
            if (substr($file, -4)=='.php') {
                require_once($fixdir.'/'.$file);
            }
        }
    	}
      do_action('add_termmeta_boxes', $this->taxonomy, $this->term);
      do_action("add_termmeta_boxes_".$this->taxonomy, $this->term);
    } //admin_head


    public function form_top()
    {
        wp_nonce_field('meta-box-order', 'meta-box-order-nonce', false);
        wp_nonce_field('closedpostboxes', 'closedpostboxesnonce', false);
        ob_start();
    }

    public function catch_fields()
    {
        $content = ob_get_clean();
        echo $content;
        preg_match_all('@form-field([^>]+)?>(.*)</'.(isset($_REQUEST['new']) ? 'div' : 'tr').'>@siU', $content, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if (preg_match('@name=[\'"]([^\'"]+)[\'"]@isU', $match[2], $m)) {
                switch ($m[1]) {
                case 'name':
                case 'tag-name':
								case 'description':
								case 'slug':
								case 'parent':
                    break;
                default:
                    $args = array(
                        'id' => 'fieldbox-'.$m[1],
                        'title' => ucfirst($m[1]),
                        'callback' => '__return_false',
                        'context' => 'side',
                        'priority' => 'core',
                    );
                    $this->add_meta_box($args);
            }
            }
        }
    }

    public function edit_form_fields()
    {
        $this->catch_callbacks(); //hijack any leftover pseudo metaboxes
        add_action($this->taxonomy."_edit_form", [$this, 'catch_fields'], 9);
        add_action($this->taxonomy."_edit_form", [$this, 'do_metaboxes'], 10, 2);
    }
    public function add_form_fields()
    {
        $this->catch_callbacks(); //hijack any leftover pseudo metaboxes
        add_action($this->taxonomy."_add_form", [$this, 'catch_fields'], 9);
        add_action($this->taxonomy."_add_form", [$this, 'do_metaboxes'], 10, 2);
    }

    //hijack existing pseudo metaboxes
    public function catch_callbacks()
    {
        if (isset($_REQUEST['new'])) {
            $tags = [
                'add_tag_form_fields',
                $this->taxonomy."_add_form_fields"
                ];
            switch ($this->taxonomy) {
                case 'category':
                case 'link_category':
                    $tags[] = 'edit_'.$this->taxonomy.'_form';
                    break;
                default:
                    $tags[] = 'add_tag_form';
            }
            $tags[] = $this->taxonomy.'_add_form';
        } else {
            $tags = [$this->taxonomy."_edit_form_fields"];
            switch ($this->taxonomy) {
                case 'category':
                case 'link_category':
                    $tags[] = 'edit_'.$this->taxonomy.'_form';
                    $tags[] = 'edit_'.$this->taxonomy.'form_fields';
                    break;
                default:
                    $tags[] = 'edit_tag_form';
                    $tags[] = 'edit_tag_form_fields';
            }
            $tags[] = $this->taxonomy.'_edit_form';
        }
        global $wp_filter;
        foreach ($tags as $tag) {
            if (empty($wp_filter[$tag])) {
                continue;
            }
            if (empty($wp_filter[$tag]->callbacks)) {
                continue;
            }
            foreach ($wp_filter[$tag]->callbacks as $priority=>$callback) {
                foreach ($callback as $id=>$the_) {
                    if (!is_callable($the_['function'])) {
                        continue;
                    } //wtf not callable
                    if (is_a($the_['function'][0], __CLASS__)) {
                        continue;
                    } //do not mess with own hooks
                    unset($wp_filter[$tag]->callbacks[$priority][$id]); //remove pseudo metabox callback
                    if (!is_array($the_['function'])) {
                        $class_or_function = is_object($the_['function']) ? get_class($the_['function']) : $the_['function'];
                    } //guess a human readable title
                    else {
                        $class_or_function = is_object($the_['function'][0]) ? get_class($the_['function'][0]) : $the_['function'][0];
                    }
                    $box_id = $class_or_function.(($this->psudeocount[$class_or_function]++) ? '-'.$this->psudeocount[$class_or_function] : '');
                    $args = array(
                        'id' => $box_id,
                        'title' => $class_or_function,
                        'callback' => $the_['function'],
                        'context' => 'normal',
                        'priority' => 'default',
                        'callback_args' => null
                    );
                    $args = apply_filters('termmeta_box_args', $args, $this->taxonomy, $this->term);
                    $args = apply_filters('termmeta_box_args_'. $class_or_function, $args, $this->taxonomy, $this->term);
                    if ($args['callback'] == $the_['function']) {
                        $args['callback'] = [$this,'_termmeta_box'];
                        $args['callback_args'] = $the_['function'];
                    }
                    $this->add_meta_box($args);
                }
            }
        }
    }

    public function add_meta_box($args)
    {
        if (empty($args)) {
            return;
        }
        if (empty($args['id']) || empty($args['title']) || !is_callable($args['callback'])) {
            return;
        }
        add_meta_box($args['id'], $args['title'], $args['callback'], $args['screen'], $args['context'], $args['priority'], $args['callback_args']);
    }

    //wrapper metabox callback for existing pseudo metaboxes
    public function _termmeta_box($term, $box)
    {
        call_user_func($box['args'], $term, $term->taxonomy);
    }

    public function description_metabox($term)
    {
        wp_editor('', 'content', array('editor_class' => 'term_meta_boxes', 'textarea_name' => 'description')); ?>
<table id="post-status-info"><tbody><tr>
	<td id="wp-word-count" class="hide-if-no-js"><?php printf(__('Word count: %s'), '<span class="word-count">0</span>'); ?></td>
	<td class="autosave-info">
	<span class="autosave-message">&nbsp;</span>
<?php
    if ($modified = get_term_meta($term->term_id, '_term_modified', true)) {
        echo '<span id="last-edit">';
        printf(__('Last edited on %1$s at %2$s'), mysql2date(__('F j, Y'), $modified), mysql2date(__('g:i a'), $modified));
        echo '</span>';
    } ?>
	</td>
	<td id="content-resize-handle" class="hide-if-no-js"><br /></td>
</tr></tbody></table>
<script>
    jQuery('#content').val(jQuery('.form-field.term-description-wrap textarea').val());
</script>
<?php
    }

    public function submitdiv_metabox($term)
    {
        ?>
<div class="submitbox" id="submitpost">

<div id="minor-publishing">
<?php do_action('term_submitbox_minor_actions', $term) ?>
<div class="clear"></div>
</div><!-- #minor-publishing-actions -->

<div id="misc-publishing-actions">
<?php do_action('term_submitbox_misc_actions', $term) ?>
<div class="clear"></div>
</div><!-- #misc-publishing-actions -->

<div id="major-publishing-actions">
<?php do_action('term_submitbox_start', $term) ?>
<div id="edit-tag-actions"></div>
<div class="clear"></div>
</div><!-- #major-publishing-actions -->

</div>
<?php
    }

    //print out metaboxes
    public function do_metaboxes()
    {
        ?>
<div id="poststuff">
<div id="post-body" class="metabox-holder columns-<?php echo 1 == get_current_screen()->get_columns() ? '1' : '2'; ?>">
<div id="post-body-content">

<div id="titlediv">
<div id="titlewrap">
<?php $title_placeholder = apply_filters('enter_title_here', __('Enter title here'), $post); ?>
	<label class="screen-reader-text" id="title-prompt-text" for="title"><?php echo $title_placeholder; ?></label>
	<input type="text" name="name" size="30" value="" id="title" spellcheck="true" autocomplete="off" />
</div>
</div><!-- /titlediv -->
</div><!-- /post-body-content -->

<div id="postbox-container-1" class="postbox-container">
<?php do_meta_boxes(null, 'side', $this->term) ?>
<?php do_action('do_termmeta_boxes', $this->taxonomy, 'side', $this->term) ?>
</div>
<div id="postbox-container-2" class="postbox-container">
<?php do_meta_boxes(null, 'normal', $this->term) ?>
<?php do_action('do_termmeta_boxes', $this->taxonomy, 'normal', $this->term) ?>
<?php do_meta_boxes(null, 'advanced', $this->term) ?>
<?php do_action('do_termmeta_boxes', $this->taxonomy, 'advanced', $this->term) ?>
</div>

</div><!-- /post-body -->
<br class="clear" />
</div><!-- /poststuff -->
<?php
    }


    public function load_postbox()
    {
        add_action('admin_print_styles', function () {
            wp_enqueue_script(['postbox','utils','word-count']);
        });

        add_action('admin_head', function () {
            ?>
<style>
table.form-table, .form-wrap, .edit-tag-actions {
	display:none;
}
.postbox .inside input[type=text] {
	width:100%;
}
#edittag, #addtag {
	max-width:none;
    padding-bottom: 65px;
    float: left;
    width: 100%;
    overflow: visible!important;
}
</style>
<?php
        });


        add_action('admin_footer', function () {
            ?>
<script>
jQuery(document).ready( function($) {
	postboxes.add_postbox_toggles(pagenow);

//return;
	$("div[id^=fieldbox-]").each(function() {
		self = $(this);
		name = self.attr('id').substring(9);
		tr = $('.form-field [name="' + name + '"]').closest('.form-field');
		title = tr.find('label').html();
		if( title.length ) self.find('h2 span:first-child').html(title);
		tr.find('label').remove();
		if(tr.find('td').length) content = tr.find('td').html();
		else content = tr.html();
		self.find('div.inside').html(content);
		tr.remove();
	});
	submitter = $('input[type="submit"].button-primary').parent();
	submitter.closest('form').submit(function(){
		$("input[type='submit']", this).attr("disabled", "disabled").before('<span class="spinner is-active"></span>');
		return true;
	});
	$('#edit-tag-actions').html(submitter.html());
	submitter.remove();

    $('#title').val($('.form-field.term-name-wrap input[name="name"]').val());
    $('#title').prop('name',$('.form-field.term-name-wrap input').prop('name'));
	$('.form-field.term-name-wrap').remove();
	$('.form-field.term-description-wrap').remove();

    table = $('table.form-table').length ? $('table.form-table') : $('.form-wrap');
    wraptag = $('#edittag').length ? $('#edittag') : $('#addtag');
	table.detach().appendTo(wraptag).show(); //just in case show any accidental leftover fields at the bottom

	wptitlehint = function(id) {
		id = id || 'title';

		var title = $('#' + id), titleprompt = $('#' + id + '-prompt-text');

		if ( '' === title.val() )
			titleprompt.removeClass('screen-reader-text');
		titleprompt.click(function(){
			$(this).addClass('screen-reader-text');
			title.focus();
		});

		title.blur(function(){
			if ( '' === this.value )
				titleprompt.removeClass('screen-reader-text');
		}).focus(function(){
			titleprompt.addClass('screen-reader-text');
		}).keydown(function(e){
			titleprompt.addClass('screen-reader-text');
			$(this).unbind(e);
		});
	};

	wptitlehint();

/**
 * TinyMCE word count display
 */
( function( $, counter ) {
	$( function() {
		var $content = $( '#content' ),
			$count = $( '#wp-word-count' ).find( '.word-count' ),
			prevCount = 0,
			contentEditor;

		/**
		 * Get the word count from TinyMCE and display it
		 */
		function update() {
			var text, count;

			if ( ! contentEditor || contentEditor.isHidden() ) {
				text = $content.val();
			} else {
				text = contentEditor.getContent( { format: 'raw' } );
			}

			count = counter.count( text );

			if ( count !== prevCount ) {
				$count.text( count );
			}

			prevCount = count;
		}

		/**
		 * Bind the word count update triggers.
		 *
		 * When a node change in the main TinyMCE editor has been triggered.
		 * When a key has been released in the plain text content editor.
		 */
		$( document ).on( 'tinymce-editor-init', function( event, editor ) {
			if ( editor.id !== 'content' ) {
				return;
			}

			contentEditor = editor;

			editor.on( 'nodechange keyup', _.debounce( update, 1000 ) );
		} );

		$content.on( 'input keyup', _.debounce( update, 1000 ) );

		update();
	} );
} )( jQuery, new wp.utils.WordCounter() );




});
</script>
<?php
        });
    } //load_postbox



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
<div class="form-field term-description-wrap">
	<label for="tag-description"><?php _e('Description'); ?></label>
	<textarea name="description" id="tag-description" rows="5" cols="40"></textarea>
	<p><?php _e('The description is not prominent by default; however, some themes may show it.'); ?></p>
</div>

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
term_editor_support::instance();
