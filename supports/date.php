<?php
class term_date_support
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
        add_action('admin_init', [$this, 'admin_init']);
        add_action('load-term.php', [$this, 'load_term']);
        add_action('created_term', [$this, 'created_term']);
        add_action('edited_terms', [$this, 'edited_terms']);
        add_filter('taxonomy_supports_defaults', function ($supports, $taxonomy, $taxonomy_object) {
            $supports[] = 'date';
            return $supports;
        }, 10, 3);
        add_filter('taxonomy_supports_options', function ($supports) {
            $supports[] = 'date';
            return $supports;
        });
    }
    public function admin_init()
    {
        if ($GLOBALS['pagenow'] !== 'edit-tags.php' && !defined('DOING_AJAX')) {
            return;
        } //prevent running on term.php!
        if (!taxonomy_supports($_REQUEST['taxonomy'], 'date')) {
            return;
        }
        new term_meta_columns(['meta' => ['key' => '_term_date', 'label' => __('Published')], 'taxonomy' => $_REQUEST['taxonomy'], 'sortable' => true, 'quick_edit' => false, 'bulk_edit' => false, 'dropdown' => false]);
        new term_meta_columns(['meta' => ['key' => '_term_modified', 'label' => __('Last Modified')], 'taxonomy' => $_REQUEST['taxonomy'], 'sortable' => true, 'quick_edit' => false, 'bulk_edit' => false, 'dropdown' => false]);
        add_filter('term_meta_columns_data', [$this, 'columns_data'], 10, 3);
    } 
    public function columns_data($output, $date, $args) {
        $column_name = $args['meta']['key'];
        if(!in_array($column_name, ['_term_date', '_term_modified'])) return $output;
        if (!empty($date)) {
            $term_time = mysql2date('U', $date, false);
            $time = current_time('timestamp', false);
            $time_diff = $time - $term_time;

            if ($time_diff >= 0 && $time_diff < DAY_IN_SECONDS) {
                $date = sprintf(__('%s ago'), human_time_diff($term_time, $time));
            } else {
//                $format = get_option( 'date_format' );
                $format = 'Y-m-d H:i:s';
                $date = date_i18n($format, $term_time);
            }
//            $date .= ' '.$ori.' '.$time_diff.' '.date('H:i',  $term_time).' '.date('H:i',  $time);
        }
        return $date;
    }

    public function created_term($term_id)
    {
        $term = get_term($term_id);
        if (!taxonomy_supports($term->taxonomy, 'date')) {
            return;
        }
        add_term_meta($term_id, '_term_date', $this->mysql_date());
        add_term_meta($term_id, '_term_date_gmt', $this->mysql_date(null, true));
        add_term_meta($term_id, '_term_modified', $this->mysql_date());
        add_term_meta($term_id, '_term_modified_gmt', $this->mysql_date(null, true));
    }
    public function edited_terms($term_id)
    {
        $term = get_term($term_id);
        if (!taxonomy_supports($term->taxonomy, 'date')) {
            return;
        }
        update_term_meta($term_id, '_term_modified', $this->mysql_date());
        update_term_meta($term_id, '_term_modified_gmt', $this->mysql_date(null, true));
        if (!get_term_meta($term_id, '_term_date', true)) {
            add_term_meta($term_id, '_term_date', $this->mysql_date());
            add_term_meta($term_id, '_term_date_gmt', $this->mysql_date(null, true));
        }
    }
    public function mysql_date($timestamp = null, $gmt = null)
    {
        if (!isset($timestamp)) {
            $timestamp = time();
        }
        return $gmt ? gmdate('Y-m-d H:i:s', $timestamp) : gmdate('Y-m-d H:i:s', $timestamp + (get_option('gmt_offset') * HOUR_IN_SECONDS));
    }

    //term.php
    public function load_term()
    {
        if (!taxonomy_supports($_REQUEST['taxonomy'], 'date')) {
            return;
        }
        if (taxonomy_supports($_REQUEST['taxonomy'], 'editor')) {
            add_action('term_submitbox_misc_actions', [$this, 'submit_meta_box']);
        } else {
            add_action($_REQUEST['taxonomy']."_edit_form_fields", [$this, 'date_field']);
        }
    }

    public function date_field($term)
    {
        if (!$date = get_term_meta($term->term_id, '_term_date', true)) {
            return;
        } ?>
				<tr class="form-field term-date-wrap">
					<th scope="row"><label for="date"><?php _e( 'Published' ); ?></label></th>
					<td>
				<span id="timestamp"><?php
					$datestr = date_i18n(
						__( 'M j, Y @ H:i' ),
						strtotime( $date )
					);
					echo '<b>'.$datestr.'</b>';
				?></span>
				<?php if($modified = get_term_meta($term->term_id, '_term_modified', true)) if($modified != $date) : ?>
				<br />
				<span id="last-edit">
				<?php printf( __( 'Last edited on %1$s at %2$s' ), mysql2date( __( 'F j, Y' ), $modified ), mysql2date( __( 'g:i a' ), $modified ) ) ?>
				</span>
				<?php endif ?>
					</td>
				</tr>
<?php
    }

    public function submit_meta_box($term)
    {
        if (!$date = get_term_meta($term->term_id, '_term_date', true)) {
            return;
        } ?>
	<div class="misc-pub-section curtime misc-pub-curtime">
		<span id="timestamp"><?php
            $datestr = date_i18n(
                __('M j, Y @ H:i'),
                strtotime($date)
            );
        printf(
                __('Published on: <b>%1$s</b>'),
                 $datestr
            ); ?></span>
	</div><!-- .misc-pub-section -->
	<?php
    }

    //edit-tags.php

    public function manage_edit_columns($columns)
    {
        $columns['date'] = __('Published');
        $columns['modified'] = __('Last Modified');
        return $columns;
    }
    public function manage_custom_column($content, $column_name, $term_id)
    {
        switch ($column_name) {
            case 'date':
                $date = get_term_meta($term_id, '_term_date', true);
                $dategmt = get_term_meta($term_id, '_term_date_gmt', true);
                break;
            case 'modified':
                $date = get_term_meta($term_id, '_term_modified', true);
                $dategmt = get_term_meta($term_id, '_term_modified_gmt', true);
                break;
        }
        if (!empty($date)) {
            $t_time = mysql2date(__('Y/m/d g:i:s a'), $date, true);
            $m_time = $date;
            $time = mysql2date('G', $dategmt);

            $time_diff = time() - $time;

            if ($time_diff > 0 && $time_diff < DAY_IN_SECONDS) {
                $date = sprintf(__('%s ago'), human_time_diff($time));
            } else {
                $date = $t_time;
            }
        }
        echo $date;
    }
    public function manage_edit_sortable_columns($sortable)
    {
        $sortable[ 'date' ] = ['date',1];
        $sortable[ 'modified' ] = ['modified',1];
        return $sortable;
    }
    public function terms_clauses_sortable_columns($clauses)
    {
        if(!in_array('WP_Terms_List_Table', array_map(function($i){return $i['class'];}, debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10)))) return $clauses;
//        if(strpos($clauses['fields'],'t.*, tt.*') === false) return $clauses;
        if (isset($_GET['orderby'])) {
            $orderby = $_GET['orderby'];
            $order = $_GET['order'];
        } else {
            $orderby = 'date';
            $order = 'desc';
        }
        if (!in_array($orderby, ['date','modified'])) {
            return $clauses;
        }
        global $wpdb;
        $clauses['fields']  .= ', if(tdate.meta_key = "_term_'.$orderby.'", tdate.meta_value, "0000-00-00 00:00:00") as date';
        $clauses['join']  .= ' LEFT JOIN ' . $wpdb->termmeta . ' AS tdate ON t.term_id = tdate.term_id ';
//    $clauses['where'] .= ' AND (tdate.meta_key = "_term_'.$orderby.'" OR tdate.meta_id IS NULL)';
        $clauses['where'] .= ' AND (tdate.meta_key = "_term_'.$orderby.'" OR NOT EXISTS(SELECT meta_id FROM ' . $wpdb->termmeta . ' WHERE term_id = t.term_id AND meta_key = "_term_'.$orderby.'") )';
        $clauses['orderby']  = ' ORDER BY date '.$order.', t.name ASC';
        $clauses['order']  = '';
        $clauses['distinct'] = 'DISTINCT';
        return $clauses;
    }
} //term_date_support
term_date_support::instance();
