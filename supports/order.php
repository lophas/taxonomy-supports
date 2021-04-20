<?php
class term_order_support
{
    private static $_instance;
    const META_KEY = '_term_order';
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
        add_action('created_term', [$this, 'edited_terms']);
        add_action('edited_terms', [$this, 'edited_terms']);
        add_filter('taxonomy_supports_defaults', function ($supports, $taxonomy, $taxonomy_object) {
            if ($taxonomy_object['hierarchical']) {
                $supports[] = 'order';
            }
            return $supports;
        }, 10, 3);
        add_filter('taxonomy_supports_options', function ($supports) {
            $supports[] = 'order';
            return $supports;
        });
    }
    public function admin_init()
    {
        if (!taxonomy_supports($_REQUEST['taxonomy'], 'order')) {
            return;
        }
        if (taxonomy_supports($_REQUEST['taxonomy'], 'editor')) {
            add_action("add_termmeta_boxes", function () {
                add_meta_box('orderbox', __('Order'), [$this, 'order_meta_box'], null, 'side', 'core');
            });
        } else {
            add_action($_REQUEST['taxonomy']."_add_form_fields", [$this, 'add_order_field']);
        }
        new term_meta_columns(['meta' => ['key' => self::META_KEY, 'label' => __('Order')], 'taxonomy' => $_REQUEST['taxonomy'], 'sortable' => true, 'quick_edit' => true, 'bulk_edit' => true, 'dropdown' => false]);
        add_filter('term_meta_columns_data_'.self::META_KEY, function($output){return strip_tags($output);}, 10, 3);
        add_filter('term_meta_columns_quick_edit_'.self::META_KEY, [$this, 'quick_edit'], 10, 4);
        add_filter('term_meta_columns_bulk_edit_'.self::META_KEY, [$this, 'bulk_edit'], 10, 2);
      	add_action('bulk_edit_update', [$this, 'bulk_edit_update']);
    }

    //term.php
    public function edited_terms($term_id)
    {
        if(defined('DOING_AJAX')) return;
        $term = get_term($term_id);
        if (!taxonomy_supports($term->taxonomy, 'order')) {
            return;
        }
        if (is_numeric($_REQUEST['order'])) {
                update_term_meta($term_id, self::META_KEY, $_REQUEST['order']);
        }
    }

    public function load_term()
    {
        if (!taxonomy_supports($_REQUEST['taxonomy'], 'order')) {
            return;
        }
        if (taxonomy_supports($_REQUEST['taxonomy'], 'editor')) {
            add_action("add_termmeta_boxes", function () {
                add_meta_box('orderbox', __('Order'), [$this, 'order_meta_box'], null, 'side', 'core');
            });
        } else {
            add_action($_REQUEST['taxonomy']."_edit_form_fields", [$this, 'edit_order_field']);
        }
    }

    public function edit_order_field($term)
    {
        ?>
		<tr class="form-field term-order-wrap">
			<th scope="row"><label for="order"><?php _e('Order'); ?></label></th>
			<td>
            <?php $this->order_meta_box($term) ?>
			</td>
		</tr>

<?php
    }

    public function add_order_field()
    {
        ?>
<div class="form-field term-order-wrap">
	<label for="order"><?php _e('Order'); ?></label>
    <?php $this->order_meta_box() ?>
</div>
<?php
    }

    public function order_meta_box($term = null)
    {
?>
            <input name="order" id="order" class="text" type="number" step="1" value="<?php echo $term ? intval(get_term_meta($term->term_id, self::META_KEY, true)) : 0 ?>">
<?php
    }

    //edit-tags.php
    public function quick_edit($output, $value, $term_id, $args) {
		$output = str_replace('type="text"','type="number" step="1"', $output);
        return $output;
    }
    public function bulk_edit($output, $args) {
		ob_start();
?>
              <input id="term_reorder" name="term_reorder" class="text" type="number" step="1" value="" placeholder="<?php _e("&mdash; No Change &mdash;") ?>"> <?php _e('From') ?>
              <input id="term_steporder" name="term_steporder" class="text" type="number" step="1" value="1"> <?php _e('Step') ?>

<?php
		$output = ob_get_clean();
        return $output;
    }
 function bulk_edit_update($term_ids) {
   if(empty($term_ids) || !is_numeric($_REQUEST['term_reorder'])) return;
  	$term_order = intval($_REQUEST['term_reorder']);
  	$step_order = intval($_REQUEST['term_steporder']);
	global $wpdb;
   foreach($term_ids as $term_id) {
      update_term_meta($term_id, self::META_KEY, $term_order);
      $term_order += $step_order;
    }
 }
} //term_order_support
term_order_support::instance();
