<?php
class taxonomy_hierarchical_columns {
  private $action;
  private $selector;
  function __construct() {
    add_action('admin_init', function() {
      global $pagenow;
      if ($pagenow !== 'edit-tags.php' && !defined('DOING_AJAX')) return;
  	  if(!get_taxonomy($_REQUEST['taxonomy'])->hierarchical) return ;
        add_filter('manage_edit-'.$_REQUEST['taxonomy'].'_columns', [$this, 'columns'], 1);
        add_filter('manage_'.$_REQUEST['taxonomy'].'_custom_column', [$this, 'column_data'], 10, 3);
        if($pagenow) add_action('load-'.$pagenow, [$this, 'load']);
    });
    $this->selector = 'parent_selector';
  }
    public function load(){
//      add_filter( 'default_hidden_columns', function($hidden, $screen ) {return array_merge($hidden, ['parent']);}, 10, 2);
//      add_filter( 'hidden_columns', function($hidden, $screen, $use_defaults){return apply_filters('default_hidden_columns',[], $screen);}, 10, 3);
      add_action('quick_edit_custom_box_fields', [$this, 'quick_edit_custom_box_fields']);
      add_action('admin_print_footer_scripts', [$this, 'quick_edit_populate_fields']);
      add_action('bulk_edit_custom_box_fields', [$this, 'bulk_edit_custom_box_fields']);
      add_action('bulk_edit_update', [$this, 'bulk_edit_update']);
      add_action('restrict_manage_terms', [$this,'parentSelector'], 10, 2);

      add_filter('terms_clauses', [$this, 'terms_clauses'], 10, 2);
      add_filter('manage_edit-'.$_REQUEST['taxonomy'].'_sortable_columns', [$this, 'sortable_columns']);
      if (is_numeric($_GET[$this->selector]) && intval($_GET[$this->selector]) > 0) add_action('admin_print_footer_scripts', function(){
?><script>
  jQuery('#tag-<?php echo $_GET[$this->selector] ?> th').html('&nbsp;');
</script><?php
      });
    }
    public function terms_clauses($clauses, $args)
    {
      if(!in_array('WP_Terms_List_Table', array_map(function($i){return $i['class'];}, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)))) return $clauses;
//echo '<pre>'.var_export(debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10),true).'</pre>';
      if ($_GET[ 'orderby'] === 'parent') {
            global $wpdb;
            $clauses['join'] .= ' LEFT JOIN '.$wpdb->term_taxonomy.' AS ttparent ON tt.parent = ttparent.term_id
                                  LEFT JOIN '.$wpdb->terms.' AS tparent ON tparent.term_id = ttparent.term_id';
            $clauses['orderby'] = 'ORDER BY tparent.name';
            $clauses['order'] = $_GET['order'] ? $_GET['order'] : 'ASC';
      }
      if (is_numeric($_GET[$this->selector]) && intval($_GET[$this->selector]) !== -1) {
            $clauses['where'] .= ' AND (tt.parent = '.$_GET[$this->selector].' OR tt.term_id = '.$_GET[$this->selector].')';
      }
//echo '<pre>'.var_export([$args,$clauses],true).'</pre>';
      return $clauses;
    }
    public function columns($columns) {
      $columns['parent'] = __('Parent');
      return $columns;
    }
    public function sortable_columns($columns) {
      $columns['parent'] = ['parent', 0];
      return $columns;
    }
    public function column_data($content, $column_name, $term_id) {
        if ($column_name !== 'parent') return;
				$term = get_term($term_id);
				if($term->parent) {
          if(defined('DOING_AJAX') || $term->parent == $_GET[$this->selector]) {
            echo '<span data-parent_id="'.$term->parent.'">'.get_term( $term->parent )->name.'</span>';
          } else {
            echo '<a href="'.add_query_arg([$this->selector => $term->parent]).'"><span data-parent_id="'.$term->parent.'">'.get_term( $term->parent )->name.'</span></a>';
          }
        }
    }

  public function quick_edit_custom_box_fields( $taxonomy) {
$dropdown_args = array(
  'hide_empty'       => 0,
  'hide_if_empty'    => false,
  'taxonomy'         => $taxonomy,
  'name'             => 'parent',
  'orderby'          => 'name',
  'hierarchical'     => true,
//        'show_option_none' => __( 'None' ),
  'show_option_all' => __( 'None' ),
);

$dropdown_args = apply_filters( 'taxonomy_parent_dropdown_args', $dropdown_args, $taxonomy, 'edit' );

?><label class="inline-edit-parent">
<span class="parent"><?php _e('Parent') ?></span>
<?php wp_dropdown_categories( $dropdown_args ) ?>
</label>
<?php
  }

  public function quick_edit_populate_fields() {
  ?>
  <script type="text/javascript">
  var ajaxurl = '<?php echo admin_url('admin-ajax.php') ?>';
  (function($) {
     var wp_inline_edit = inlineEditTax.edit;
     inlineEditTax.edit = function( id ) {
      wp_inline_edit.apply( this, arguments );
<?php if(!current_user_can('manage_options')) : ?>
$('input[name="slug"]').closest('label').hide(); //hide quickedit slug row
<?php endif ?>
	  var term_id = 0;
      if ( typeof( id ) == 'object' ) {
          term_id = parseInt( this.getId( id ) );
	  }
	  if ( term_id > 0 ) {
		  var this_field = $( '#edit-' + term_id ).find( '[name="parent"]' );
      var this_value = $( '#inline_' + term_id).find('.parent').text();
      this_field.val(this_value);
       }
     };
  })(jQuery);
  </script>
  <?php
  }
 function bulk_edit_custom_box_fields( $taxonomy ) {
//   if (in_array('parent', get_hidden_columns(get_current_screen()))) return;
   ?>
               <label>
                   <span class="title"><?php _e('Parent') ?></span>
 <?php
				$dropdown_args = array(
					'hide_empty'       => 0,
					'hide_if_empty'    => false,
					'taxonomy'         => $taxonomy,
					'name'             => 'parent',
					'orderby'          => 'name',
					'selected'         => -1,
					'hierarchical'     => true,
//					'show_option_none' => __( 'None' ),
					'show_option_none' => __("&mdash; No Change &mdash;"),
          'show_option_all' => __( 'None' ),//__('Main Page (no parent)'),
 					);
            wp_dropdown_categories( $dropdown_args );
?>
               </label>
   <?php
 }
 function bulk_edit_update($term_ids) {
   if(empty($term_ids) || !is_numeric($_REQUEST['parent']) || intval($_REQUEST['parent']) === -1) return;
   foreach($term_ids as $term_id) {
     if(intval($term_id) !== intval($_REQUEST['parent'])) wp_update_term( intval($term_id), $_REQUEST['taxonomy'], ['parent' => intval($_REQUEST['parent'])] );
   }
 }
 function parentSelector() {
   if (in_array('parent', get_hidden_columns(get_current_screen()))) {
       return;
   }
   global $taxnow;
   global $wpdb;
   $sql = 'SELECT DISTINCT parent FROM '.$wpdb->term_taxonomy.' WHERE taxonomy="'.$taxnow.'" AND parent > 0';
   $parents = $wpdb->get_col($sql);
//echo '<pre>'.var_export($parents, true).'</pre>';
   $termtree = [];
   foreach($parents as $parent) {
     $term = get_term($parent);
     $termtree[intval($term->parent)][] = $term;
   }
//echo '<pre>'.var_export($termtree, true).'</pre>';
   echo '<select name="parent_selector">';
   echo '<option value="">'.__('Parent').'</option>';
   echo '<option value=0 '.selected(0, $_GET['parent_selector']).'>'.__( 'None' ).'</option>';
   $this->parentSelector_dropdown($termtree);
   echo '</select>';
 }
 function parentSelector_dropdown($termtree, $parent = 0, $indent = 0) {
  if(empty($termtree[$parent])) return;
  foreach($termtree[$parent] as $term) :
    ?><option value=<?php echo $term->term_id ?> <?php selected($term->term_id,$_GET['parent_selector']); ?>><?php echo str_repeat('&nbsp;&nbsp;', $indent).($term->name ? esc_attr($term->name) : __('Untitled')) ?></option><?php
  $this->parentSelector_dropdown($termtree, $term->term_id, $indent + 1);
  endforeach;
}

} //class
new taxonomy_hierarchical_columns;
