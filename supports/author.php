<?php
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

    public function author_meta_box($term = null)
    {
        $user_id = $term ? get_term_meta($term->term_id, self::META_KEY, true) : 0;
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
        new term_meta_columns(['meta' => ['key' => self::META_KEY, 'label' => __('Author')], 'sortable' => true, 'quick_edit' => true, 'bulk_edit' => true, 'dropdown' => true]);
        add_filter('term_meta_columns_quick_edit_'.self::META_KEY, [$this, 'quick_edit'], 10, 4);
        add_filter('term_meta_columns_bulk_edit_'.self::META_KEY, [$this, 'bulk_edit'], 10, 2);
        add_filter('term_meta_columns_data_'.self::META_KEY, [$this, 'author_name'], 10, 2);
} //load_edit_tags
    public function author_name($value, $args = null) {
        if($user = get_userdata($value)) $value = $user->display_name ? $user->display_name : $user->nicename;
        return $value;
    }
    public function quick_edit($output, $value, $term_id, $args) {
        $output = $this->dropdown($value);
        return $output;
    }
    public function bulk_edit($output, $args) {
        $output = $this->dropdown();
        return $output;
    }
    public function dropdown($selected = null){
        global $wpdb;
        $output = '';
        $sql = 'SELECT DISTINCT m.meta_value FROM '.$wpdb->termmeta.' m
				JOIN '.$wpdb->term_taxonomy.' tt ON tt.term_id = m.term_id
				WHERE m.meta_key="'.self::META_KEY.'" AND tt.taxonomy = "'.$_REQUEST['taxonomy'].'" AND meta_value <> ""
        ORDER BY m.meta_value ASC';
        $values = $wpdb->get_col($sql);
        $output .= '<select name="'.self::META_KEY.'">';
        $output .= '<option value="">'.__('None').'</option>';
        foreach ($values as $value) {
            $meta_name = $this->author_name($value);
            $output .= '<option value="'.$value.'" '.(isset($selected) ? selected($value, $selected, false) : '').'>'.$meta_name.'</option>';
        }
        $output .= '</select>';
        return $output;
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
} //term_author_support
term_author_support::instance();
