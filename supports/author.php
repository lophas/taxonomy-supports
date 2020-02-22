<?php
class term_author_support
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
        add_action('load-term.php', [$this, 'load_term']);
        add_action('created_term', [$this, 'created_term']);
        add_action('edited_terms', [$this, 'edited_terms']);
        add_filter('taxonomy_supports_defaults', function ($supports, $taxonomy, $taxonomy_object) {
            if ($taxonomy_object['hierarchical']) {
                $supports[] = 'author';
            }
            return $supports;
        }, 10, 3);
        add_filter('taxonomy_supports_options', function ($supports) {
            $supports[] = 'author';
            return $supports;
        });
    }

    //term.php
    public function created_term($term_id)
    {
        $term = get_term($term_id);
        if (!taxonomy_supports($term->taxonomy, 'author')) {
            return;
        }
        if (!empty($_REQUEST['author'])) {
            add_term_meta($term_id, '_term_author', $_REQUEST['author']);
        } elseif ($user_id = get_current_user_id()) {
            add_term_meta($term_id, '_term_author', $user_id);
        }
    }
    public function edited_terms($term_id)
    {
        $term = get_term($term_id);
        if (!taxonomy_supports($term->taxonomy, 'author')) {
            return;
        }
        if (!empty($_REQUEST['author'])) {
            update_term_meta($term_id, '_term_author', $_REQUEST['author']);
        } elseif (!get_term_meta($term_id, '_term_author', true)) {
            if ($user_id = get_current_user_id()) {
                update_term_meta($term_id, '_term_author', $user_id);
            }
        }
    }

    public function load_term()
    {
        if (!taxonomy_supports($_REQUEST['taxonomy'], 'author')) {
            return;
        }
        if (taxonomy_supports($_REQUEST['taxonomy'], 'editor')) {
            add_action("add_termmeta_boxes", function () {
                add_meta_box('authorbox', __('Author'), [$this, 'author_meta_box'], null, 'side', 'core');
            });
        } else {
            add_action($_REQUEST['taxonomy']."_edit_form_fields", [$this, 'edit_author_field']);
        }
    }

    public function edit_author_field($term)
    {
        ?>
		<tr class="form-field term-author-wrap">
			<th scope="row"><label for="author"><?php _e('Author'); ?></label></th>
			<td>
<?php $this->author_meta_box($term) ?>
			</td>
		</tr>

<?php
    }

    public function author_meta_box($term = null)
    {
        $user_id = $term ? get_term_meta($term->term_id, '_term_author', true) : 0;
        wp_dropdown_users(array(
        'who' => 'authors',
        'name' => 'author',
        'selected' => $user_id ?  $user_id : get_current_user_id(),
        'include_selected' => true,
        'show' => 'display_name_with_login',
    ));
    }

    //edit-tags.php
    public function load_edit_tags()
    {
        if (!taxonomy_supports($_REQUEST['taxonomy'], 'author')) {
            return;
        }
        if (taxonomy_supports($_REQUEST['taxonomy'], 'editor')) {
            add_action("add_termmeta_boxes", function () {
                add_meta_box('authorbox', __('Author'), [$this, 'author_meta_box'], null, 'side', 'core');
            });
        } else {
            add_action($_REQUEST['taxonomy']."_add_form_fields", [$this, 'add_author_field']);
        }
        if ($GLOBALS['pagenow'] !== 'edit-tags.php' && !defined('DOING_AJAX')) {
            return;
        } //prevent running on term.php!
        add_action('admin_head', function () {
            ?><style>.column-author {width: 10%}</style><?php
        });
        add_filter('manage_edit-'.$_REQUEST['taxonomy'].'_columns', [$this, 'manage_edit_columns']);
        add_filter('manage_'.$_REQUEST['taxonomy'].'_custom_column', [$this, 'manage_custom_column'], 10, 3);
        add_filter('terms_clauses', [$this, 'terms_author_selector']);
    } //load_edit_tags

    public function add_author_field()
    {
        ?>
<div class="form-field term-author-wrap">
	<label for="author"><?php _e('Author'); ?></label>
<?php $this->author_meta_box() ?>
</div>
<?php
    }

    public function manage_edit_columns($columns)
    {
        $columns['author'] = __('Author');
        return $columns;
    }
    public function manage_custom_column($content, $column_name, $term_id)
    {
        if ($column_name == 'author') {
            if ($user_id = get_term_meta($term_id, '_term_author', true)) {
                //			echo get_userdata($user_id)->display_name;
                $args = array(
            'taxonomy' => $_REQUEST['taxonomy'],
            'author' => $user_id
        );
                echo $this->get_edit_link($args, get_userdata($user_id)->display_name);
            }
        }
    }
    public function get_edit_link($args, $label, $class = '')
    {
        $url = add_query_arg($args, 'edit-tags.php');

        $class_html = $aria_current = '';
        if (! empty($class)) {
            $class_html = sprintf(
                ' class="%s"',
                esc_attr($class)
            );

            if ('current' === $class) {
                $aria_current = ' aria-current="page"';
            }
        }

        return sprintf(
            '<a href="%s"%s%s>%s</a>',
            esc_url($url),
            $class_html,
            $aria_current,
            $label
        );
    }
    public function terms_author_selector($clauses)
    {
        if (empty($_GET['author'])) {
            return $clauses;
        }
        global $wpdb;
        $clauses['join']  .= ' INNER JOIN ' . $wpdb->termmeta . ' AS ta ON t.term_id = ta.term_id ';
        $clauses['where'] .= ' AND ta.meta_key = "_term_author" AND ta.meta_value = '.$_GET['author'];
        return $clauses;
    }
} //term_author_support
term_author_support::instance();
