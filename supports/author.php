<?php
//3.8
class term_author_support
{
    private static $_instance;
    const META_KEY = '_term_author';
    public static function instance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance =  new self;
        }
        return self::$_instance;
    }
    public function __construct()
    {
        add_action('admin_init', [$this, 'admin_init']);
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
    public function admin_init()
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
        new term_meta_columns(['meta' => ['key' => self::META_KEY, 'label' => __('Author')], 'taxonomy' => $_REQUEST['taxonomy'], 'sortable' => true, 'quick_edit' => true, 'bulk_edit' => true, 'dropdown' => true]);
        add_filter('term_meta_columns_quick_edit_'.self::META_KEY, [$this, 'quick_edit'], 10, 2);
        add_filter('term_meta_columns_bulk_edit_'.self::META_KEY, [$this, 'bulk_edit'], 10, 2);
        add_filter('term_meta_columns_data_'.self::META_KEY, [$this, 'columns_data'], 10, 3);
    }

    //term.php
    public function created_term($term_id)
    {
        $term = get_term($term_id);
        if (!taxonomy_supports($term->taxonomy, 'author')) {
            return;
        }
        if (!empty($_REQUEST['author'])) {
            add_term_meta($term_id, self::META_KEY, $_REQUEST['author']);
        } elseif ($user_id = get_current_user_id()) {
            add_term_meta($term_id, self::META_KEY, $user_id);
        }
    }
    public function edited_terms($term_id)
    {
        if(defined('DOING_AJAX')) return;
        $term = get_term($term_id);
        if (!taxonomy_supports($term->taxonomy, 'author')) {
            return;
        }
        if (!empty($_REQUEST['author'])) {
                update_term_meta($term_id, self::META_KEY, $_REQUEST['author']);
        } elseif (!get_term_meta($term_id, self::META_KEY, true)) {
            if ($user_id = get_current_user_id()) {
                update_term_meta($term_id, self::META_KEY, $user_id);
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

    public function add_author_field()
    {
        ?>
<div class="form-field term-author-wrap">
	<label for="author"><?php _e('Author'); ?></label>
<?php $this->author_meta_box() ?>
</div>
<?php
    }

    public function author_meta_box($term = null)
    {
        $user_id = $term ? get_term_meta($term->term_id, self::META_KEY, true) : 0;
        $users_opt = array(
//        'who' => 'authors',
        'name' => 'author',
        'selected' => $user_id ?  $user_id : get_current_user_id(),
        'include_selected' => true,
        'show' => 'display_name_with_login',
        );
        if($term) {
            $taxonomy_object = get_taxonomy($term->taxonomy);
            $users_opt['capability' ] = array( $taxonomy_object->cap->manage_terms );
        }
        wp_dropdown_users($users_opt);
    }

    //edit-tags.php
    public function columns_data($output, $value, $args = null) {
        if($user = get_userdata($value)) {
            $meta_name = $user->display_name ? $user->display_name : $user->nicename;
            $selector = self::META_KEY.'_selector';
            if (empty($_GET[$selector]) && !defined('DOING_AJAX')) {
                $output = '<a href="'.add_query_arg($selector, $value).'">'.$meta_name.'</a>';
            } else {
                $output = $meta_name;
            }
        }
        return $output;
    }
    public function quick_edit($output, $args) {
        $taxonomy_object = get_taxonomy($_REQUEST['taxonomy']);
        $users_opt = array(
        'name' => self::META_KEY,
        'show' => 'display_name_with_login',
//        'show_option_none' => __('None' ),
//        'option_none_value' => null,
        'capability' => array( $taxonomy_object->cap->manage_terms ),
        'echo'  => 0,
        );
        $output = wp_dropdown_users($users_opt);
        return $output;
    }
    public function bulk_edit($output, $args) {
        $taxonomy_object = get_taxonomy($_REQUEST['taxonomy']);
        $users_opt = array(
        'name' => self::META_KEY,
        'show' => 'display_name_with_login',
        'show_option_none' => __( "&mdash; No Change &mdash;" ), 
        'option_none_value' => null,
        'capability' => array( $taxonomy_object->cap->manage_terms ),
        'echo'  => 0,
        );
        $output = wp_dropdown_users($users_opt);
        return $output;
    }
} //term_author_support
term_author_support::instance();
