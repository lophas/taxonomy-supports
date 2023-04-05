<?php
/*
    Plugin Name: Meta term columns class
    Description:
    Version: 2.2
    Plugin URI:
    Author: Attila Seres
    Author URI:

new term_meta_columns(key|[
  'meta' => key|['label' => 'keylabel', 'key' => 'keyname'],
  'taxonomy' => 'post_tag'|['post_tag','category'],
  'dropdown' => false,
  'sortable' => false|true|'num',
  'quick_edit' => false,
  'bulk_edit' => false,
]);
apply_filters('term_meta_columns_data', $data, $this->args)
*/
if (!class_exists('term_meta_columns')) :
class term_meta_columns
{
    private $action;
    private $args;
    public function __construct($args)
    {
        if (!is_array($args)) {
            $args = ['meta' => ['key' => $args]];
        }
        $this->args = array_merge([
          'taxonomy' => ['post_tag'],
          'dropdown' => false,
          'sortable' => false,
          'quick_edit' => false,
          'bulk_edit' => false,
      ], $args);
        if (empty($this->args['meta'])) {
            return;
        }
        if (!is_array($this->args['meta'])) {
            $this->args['meta'] = ['key' => $this->args['meta']];
        }
        if (empty($this->args['meta']['label'])) {
            $this->args['meta']['label'] = ucfirst($this->args['meta']['key']);
        }
        if (!is_array($this->args['taxonomy'])) {
            $this->args['taxonomy'] = (array)$this->args['taxonomy'];
        }
        if ($this->args['quick_edit']) {
          $this->action = 'term_meta_columns_'.$this->args['meta']['key'];
          add_action('wp_ajax_'.$this->action, array($this,'do_ajax'));
        }
        add_action('admin_init', [$this, 'admin_init'], PHP_INT_MAX);
//echo '<pre>'.var_export($this->args, true).'</pre>';
    } //construct
    public function admin_init()
    {
      global $pagenow, $taxnow;
      if(!defined('DOING_AJAX') && $pagenow !== 'edit-tags.php') return;
        if (empty($taxnow)) {
            $taxnow = empty($_REQUEST['taxonomy']) ? 'post_tag' : $_REQUEST['taxonomy'];
        }
        if (!in_array($taxnow, $this->args['taxonomy'])) {
            return;
        }
        add_filter('manage_edit-'.$taxnow.'_columns', [$this, 'columnsname'], 1);
        add_action('manage_'.$taxnow.'_custom_column', [$this, 'columnsdata'], 10, 3);
        add_action('edited_terms', [$this, 'quick_edit_update']);

        if($pagenow) add_action('load-'.$pagenow, [$this, 'load']);
    }
    public function load()
    {
      global $taxnow;
        add_filter('terms_clauses', [$this, 'terms_clauses'], 10, 2);
        if ($this->args['sortable']) {
          add_filter('manage_edit-'.$taxnow.'_sortable_columns', [$this, 'sortable']);
        }
        if ($this->args['bulk_edit']) {
            add_action('bulk_edit_custom_box_fields', [$this, 'bulk_edit_custom_box_fields']);
            add_action('bulk_edit_update', [$this, 'bulk_edit_update']);
        }

        if ($this->args['quick_edit']) {
            add_action('quick_edit_custom_box_fields', [$this, 'quick_edit_custom_box_fields']);
            add_action('admin_print_footer_scripts', [$this, 'quick_edit_populate_fields'], 999);
        }
        if ($this->args['dropdown']) {
            add_action('restrict_manage_terms', [$this,'dropdown'], 10, 2);
        }
    }
    public function columnsname($columns)
    {
        $columns[$this->args['meta']['key']] = $this->args['meta']['label'];
        return $columns;
    }
    public function columnsdata($content, $column, $term_id)
    {
        if ($column !== $this->args['meta']['key']) {
            return;
        }
//echo '<pre>'.var_export([$term_id, $this->args['meta']['key']],true).'</pre>';
        $value = get_term_meta($term_id, $this->args['meta']['key'], true);
        if ($value === '') {
            return;
        }
        $selector = $this->args['meta']['key'].'_selector';
        if (empty($_GET[$selector]) && $this->args['dropdown'] && !defined('DOING_AJAX')) {
            $output = '<a href="'.add_query_arg($selector, $value).'">'.$value.'</a>';
        } else {
            $output = $value;
        }
        $output = apply_filters('term_meta_columns_data', $output, $value, $this->args);
        $output = apply_filters('term_meta_columns_data_'.$this->args['meta']['key'], $output, $value, $this->args);
        echo $output;
    }
    public function sortable($columns)
    {
        $columns[$this->args['meta']['key']] = [$this->args['meta']['key'], 0];
        return $columns;
    }
    public function terms_clauses($clauses, $query)
    {
        if(!in_array('WP_Terms_List_Table', array_map(function($i){return $i['class'];}, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)))) return $clauses;
        $selector = $this->args['meta']['key'].'_selector';
        if ($_GET[ 'orderby'] === $this->args['meta']['key'] || !empty($_GET[$selector])) {
            $key = str_replace('-','_',$this->args['meta']['key']);
            global $wpdb;
            $clauses['join'] .= ' LEFT JOIN '.$wpdb->termmeta.' AS '.$key.' ON t.term_id = '.$key.'.term_id AND '.$key.'.meta_key = "'.$this->args['meta']['key'].'"';
            if ($_GET[ 'orderby'] === $this->args['meta']['key']) {
              $clauses['where'] .= ' AND ('.$key.'.meta_key = "'.$this->args['meta']['key'].'" OR '.$key.'.term_id IS NULL)';
              $clauses['orderby'] = 'ORDER BY '.$key.'.'.($this->args['sortable'] === 'num' ? 'meta_value+0' : 'meta_value');
              $clauses['order'] = $_GET['order'] ? $_GET['order'] : 'ASC';
            }
            if (!empty($_GET[$selector])) {
              $clauses['where'] .= $wpdb->prepare(' AND '.$key.'.meta_value = %s', $_GET[$selector]);
            }
        }
        return $clauses;
    }
    public function dropdown($taxonomy, $which)
    {
        if (in_array($this->args['meta']['key'], get_hidden_columns(get_current_screen()))) {
            return;
        }
        global $wpdb;
        $sql = 'SELECT DISTINCT m.meta_value FROM '.$wpdb->termmeta.' m
				JOIN '.$wpdb->term_taxonomy.' tt ON tt.term_id = m.term_id
				WHERE m.meta_key="'.$this->args['meta']['key'].'" AND tt.taxonomy IN("'.implode('","', $this->args['taxonomy']).'") AND meta_value <> ""
        ORDER BY m.meta_value ASC';//.($this->args['sortable'] === 'num' ? 'meta_value_num' : 'meta_value');
        $values = $wpdb->get_col($sql);
        $selector = $this->args['meta']['key'].'_selector';
        echo '<select name="'.$selector.'">';
        echo '<option value="">'.__('All').' '.$this->args['meta']['label'].'</option>';
        foreach ($values as $value) {
            $meta_name = trim(strip_tags(apply_filters('term_meta_columns_data', $value, $value, $this->args)));
            $meta_name = trim(strip_tags(apply_filters('term_meta_columns_data_'.$this->args['meta']['key'], $meta_name, $value, $this->args)));
            echo '<option value="'.$value.'" '.selected($value, $_GET[$selector]).'>'.$meta_name.'</option>';
        }
        echo '</select>';
    }
    public function bulk_edit_custom_box_fields($taxonomy)
    {
//        if (in_array($this->args['meta']['key'], get_hidden_columns(get_current_screen()))) return;
 
        $key = $this->args['meta']['key'];
//        if ($column !== $key || !in_array($taxonomy, $this->args['taxonomy'])) return;
        ?>
            <label class="inline-edit-<?php echo $key ?>">
              <span class="title"><?php echo $this->args['meta']['label'] ?></span>
        <?php
			  $output = '<input id="'.$key.'" name="'.$key.'" type="text" value="" placeholder="'. __("&mdash; No Change &mdash;").'">';
              $output = apply_filters('term_meta_columns_bulk_edit_'.$key, $output, $this->args);
              $output = apply_filters('term_meta_columns_bulk_edit', $output, $this->args);
              echo $output;
        ?>
          </label>
  <?php
    }
    public function bulk_edit_update($term_ids)
    {
        $key = $this->args['meta']['key'];
        if (!isset($_REQUEST[$key])) {
            return;
        }
        $val = $_REQUEST[$key];
        if ($val === '') {
            return;
        }
        //return;
        if (empty($term_ids)) {
            return;
        }
        foreach ($term_ids as $term_id) {
            update_term_meta($term_id, $key, $val);
        }
    }
    public function quick_edit_custom_box_fields($taxonomy)
    {
        $key = $this->args['meta']['key'];
        if (!in_array($taxonomy, $this->args['taxonomy'])) return;
        ?><label class="inline-edit-<?php echo $key ?>">
            <span class="title"><?php echo $this->args['meta']['label'] ?></span>
			  <input id="<?php echo $key ?>" name="<?php echo $key ?>" type="text" value="" placeholder="<?php echo __("Loading&hellip;") ?>">
        </label><?php
    }
    public function quick_edit_populate_fields()
    {
        global $taxnow;
        if (!in_array($taxnow, $this->args['taxonomy'])) {
            return;
        }
        $key = $this->args['meta']['key']; ?>
<script type="text/javascript">
var ajaxurl = '<?php echo admin_url('admin-ajax.php') ?>';
(function($) {
   var wp_inline_edit = inlineEditTax.edit;
   inlineEditTax.edit = function( id ) {
      wp_inline_edit.apply( this, arguments );
      var term_id = 0;
      if ( typeof( id ) == 'object' ) term_id = parseInt( this.getId( id ) );
      if ( term_id > 0 ) {
          var edit_row = $( '#edit-' + term_id );
		      var this_field = edit_row.find( '.inline-edit-<?php echo $key ?>' );
		      if(this_field.length) {
//            this_value = $( '#tag-' + term_id).find('.column-<?php echo $key ?>').text();
//      			this_field.find('input').val(this_value); //instant value

            var data = {
            		term_id: term_id,
            		taxonomy: '<?php echo $taxnow ?>',
      			    action: '<?php echo $this->action ?>'
          	};
          	jQuery.post(ajaxurl, data, function(result) {
              this_field.find('input').replaceWith(result); //updated value from ajax
      		  }, 'html');

		      }
	     }
   };
})(jQuery);
</script>
<?php
    }
    public function do_ajax()
    {
        $output = __('Failed');
        if (($term_id = $_REQUEST['term_id'])) {
            $key = $this->args['meta']['key'];
            $value = get_term_meta($term_id, $key, true);
            $output = '<input id="'.$key.'" name="'.$key.'" type="text" value="'.esc_attr($value).'">';
            $output = apply_filters('term_meta_columns_quick_edit_'.$key, $output, $value, $term_id, $this->args);
            $output = apply_filters('term_meta_columns_quick_edit', $output, $value, $term_id, $this->args);
        }
        echo $output;
        exit;
    }
    public function quick_edit_update($term_id)
    {
        if (!defined('DOING_AJAX')) {
            return;
        }
        $term = get_term($term_id);
        if (!in_array($term->taxonomy, $this->args['taxonomy'])) {
            return;
        }
        $key = $this->args['meta']['key'];
        if (!isset($_REQUEST[$key])) {
              return;
        }
        $val = $_REQUEST[$key];
        update_term_meta($term_id, $key, $val);
      }
}
endif;
