<?php
class term_custom_fields_support
{
    private static $_instance;
		private $taxonomy;
    public function instance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance =  new self;
        }
        return self::$_instance;
    }
    public function __construct()
    {
        add_action('load-term.php', [$this, 'load']);
        add_action('load-edit-tags.php', function () {
            if ($GLOBALS['pagenow'] !== 'edit-tags.php') {
                return;
            } //prevent running on term.php!
            $this->taxonomy = $_REQUEST['taxonomy'];
            if (!taxonomy_supports($this->taxonomy, 'custom-fields')) {
                return;
            }
            add_action("edit_term", [$this, 'update_meta']);
        });
        add_action('wp_ajax_delete-termmeta', [$this, 'ajax_delete_termmeta']);
        add_action('wp_ajax_add-termmeta', [$this, 'ajax_add_termmeta']);
        add_filter('taxonomy_supports_defaults', function ($supports, $taxonomy, $taxonomy_object) {
            if ($taxonomy_object['hierarchical']) {
                $supports[] = 'custom-fields';
            }
            return $supports;
        }, 10, 3);
        add_filter('taxonomy_supports_options', function ($supports) {
            $supports[] = 'custom-fields';
            return $supports;
        });
    }
    public function load()
    {
        $this->taxonomy = $_REQUEST['taxonomy'];
        if (empty($_REQUEST['tag_ID']) || !taxonomy_supports($this->taxonomy, 'custom-fields')) {
            return;
        }
        add_action('admin_print_styles', function () {
            wp_enqueue_script(['postbox', 'wp-lists']);
        });
        add_action("add_termmeta_boxes", function () {
            add_meta_box('postcustom', __('Custom Fields'), [$this, 'term_custom_meta_box'], null, 'normal', 'core'); //add custom fields metabox
        });
        if (!taxonomy_supports($this->taxonomy, 'editor')) {
            add_action($this->taxonomy."_edit_form", function ($term) {
                do_action("add_termmeta_boxes", $this->taxonomy, $term);
                do_meta_boxes(null, 'normal', $term);
                do_meta_boxes(null, 'advanced', $term);
                do_meta_boxes(null, 'side', $term);
            });
        }
        add_action('admin_footer', [$this, 'admin_footer']);
    }

    public function admin_footer()
    {
        ?>
    <script>
//from post.js
jQuery(document).ready( function($) {
	// Custom Fields postbox.
	if ( $('#postcustom').length ) {
		$( '#the-list' ).wpList( {
			addBefore: function( s ) {
				s.data += '&term_id=' + $("[name='tag_ID']").val();
				return s;
			},
			addAfter: function() {
				$('table#list-table').show();
			}
		});
	}
});

</script>
    <?php
    }

    public function term_custom_meta_box($term)
    {
        ?>
<div id="postcustomstuff">
<script>jQuery('#ajax-response').remove();</script>
<div id="ajax-response"></div>
<?php
$metadata = $this->has_meta($term->term_id);
        foreach ($metadata as $key => $value) {
            if (is_protected_meta($metadata[ $key ][ 'meta_key' ], 'term') || ! current_user_can('edit_term_meta', $term->term_id, $metadata[ $key ][ 'meta_key' ])) {
                unset($metadata[ $key ]);
            }
        }

        $this->list_meta($metadata);
        $this->meta_form($term); ?>
</div>
<?php
    }

    public function meta_form($term = null)
    {
        global $wpdb;
        //	$post = get_post( $post );

        /**
         * Filters values for the meta key dropdown in the Custom Fields meta box.
         *
         * Returning a non-null value will effectively short-circuit and avoid a
         * potentially expensive query against termmeta.
         *
         * @since 4.4.0
         *
         * @param array|null $keys Pre-defined meta keys to be used in place of a termmeta query. Default null.
         * @param WP_Post    $post The current post object.
         */
        $keys = apply_filters('termmeta_form_keys', null, $term);

        if (null === $keys) {
            /**
             * Filters the number of custom fields to retrieve for the drop-down
             * in the Custom Fields meta box.
             *
             * @since 2.1.0
             *
             * @param int $limit Number of custom fields to retrieve. Default 30.
             */
            $limit = apply_filters('termmeta_form_limit', 30);

            $sql = "SELECT DISTINCT m.meta_key
				FROM $wpdb->termmeta m
				JOIN $wpdb->term_taxonomy tt ON m.term_id = tt.term_id
				WHERE m.meta_key NOT BETWEEN '_' AND '_z'
				AND tt.taxonomy = %s
				ORDER BY m.meta_key
				LIMIT %d";
            $keys = $wpdb->get_col($wpdb->prepare($sql, $term->taxonomy, $limit));
        }

        if ($keys) {
            natcasesort($keys);
            $meta_key_input_id = 'metakeyselect';
        } else {
            $meta_key_input_id = 'metakeyinput';
        } ?>
<p><strong><?php _e('Add New Custom Field:') ?></strong></p>
<table id="newmeta">
<thead>
<tr>
<th class="left"><label for="<?php echo $meta_key_input_id; ?>"><?php _ex('Name', 'meta name') ?></label></th>
<th><label for="metavalue"><?php _e('Value') ?></label></th>
</tr>
</thead>

<tbody>
<tr>
<td id="newmetaleft" class="left">
<?php if ($keys) {
            ?>
<select id="metakeyselect" name="metakeyselect">
<option value="#NONE#"><?php _e('&mdash; Select &mdash;'); ?></option>
<?php

    foreach ($keys as $key) {
        if (is_protected_meta($key, 'term') || ! current_user_can('add_term_meta', $term->term_id, $key)) {
            continue;
        }
        echo "\n<option value='" . esc_attr($key) . "'>" . esc_html($key) . "</option>";
    } ?>
</select>
<input class="hide-if-js" type="text" id="metakeyinput" name="metakeyinput" value="" />
<a href="#postcustomstuff" class="hide-if-no-js" onclick="jQuery('#metakeyinput, #metakeyselect, #enternew, #cancelnew').toggle();return false;">
<span id="enternew"><?php _e('Enter new'); ?></span>
<span id="cancelnew" class="hidden"><?php _e('Cancel'); ?></span></a>
<?php
        } else {
            ?>
<input type="text" id="metakeyinput" name="metakeyinput" value="" />
<?php
        } ?>
</td>
<td><textarea id="metavalue" name="metavalue" rows="2" cols="25"></textarea></td>
</tr>

<tr><td colspan="2">
<div class="submit">
<input type="button" name="addtermmeta" id="newmeta-submit" class="button" value="<?php echo esc_attr(__('Add Custom Field')) ?>" data-wp-lists="add:the-list:newmeta">
<?php //submit_button( __( 'Add Custom Field' ), '', 'addtermmeta', false, array( 'id' => 'newmeta-submit', 'data-wp-lists' => 'add:the-list:newmeta' ) );?>
</div>
<?php wp_nonce_field('add-termmeta', '_ajax_nonce-add-termmeta', false); ?>
</td></tr>
</tbody>
</table>
<?php
    }

    public function list_meta($meta)
    {
        // Exit if no meta
        if (! $meta) {
            echo '
<table id="list-table" style="display: none;">
	<thead>
	<tr>
		<th class="left">' . _x('Name', 'meta name') . '</th>
		<th>' . __('Value') . '</th>
	</tr>
	</thead>
	<tbody id="the-list" data-wp-lists="list:termmeta">
	<tr><td></td></tr>
	</tbody>
</table>'; //TBODY needed for list-manipulation JS
            return;
        }
        $count = 0; ?>
<table id="list-table">
	<thead>
	<tr>
		<th class="left"><?php _ex('Name', 'meta name') ?></th>
		<th><?php _e('Value') ?></th>
	</tr>
	</thead>
	<tbody id='the-list' data-wp-lists='list:termmeta'>
<?php
    foreach ($meta as $entry) {
        echo $this->_list_meta_row($entry, $count);
    } ?>
	</tbody>
</table>
<?php
    }

    public function _list_meta_row($entry, &$count)
    {
        static $update_nonce = '';

        if (is_protected_meta($entry['meta_key'], 'term')) {
            return '';
        }

        if (! $update_nonce) {
            $update_nonce = wp_create_nonce('add-termmeta');
        }

        $r = '';
        ++ $count;

        if (is_serialized($entry['meta_value'])) {
            if (is_serialized_string($entry['meta_value'])) {
                // This is a serialized string, so we should display it.
                $entry['meta_value'] = maybe_unserialize($entry['meta_value']);
            } else {
                // This is a serialized array/object so we should NOT display it.
                --$count;
                return '';
            }
        }

        $entry['meta_key'] = esc_attr($entry['meta_key']);
        $entry['meta_value'] = esc_textarea($entry['meta_value']); // using a <textarea />
        $entry['meta_id'] = (int) $entry['meta_id'];

        $delete_nonce = wp_create_nonce('delete-termmeta_' . $entry['meta_id']);

        $r .= "\n\t<tr id='termmeta-{$entry['meta_id']}'>";
        $r .= "\n\t\t<td class='left'><label class='screen-reader-text' for='termmeta-{$entry['meta_id']}-key'>" . __('Key') . "</label><input name='termmeta[{$entry['meta_id']}][key]' id='termmeta-{$entry['meta_id']}-key' type='text' size='20' value='{$entry['meta_key']}' />";

        $r .= "\n\t\t<div class='submit'>";
        //	$r .= get_submit_button( __( 'Delete' ), 'deletetermmeta small', "deletetermmeta[{$entry['meta_id']}]", false, array( 'data-wp-lists' => "delete:the-list:termmeta-{$entry['meta_id']}::_ajax_nonce=$delete_nonce" ) );
        $r .= '<input type="button" name="deletetermmeta['.$entry['meta_id'].']" id="deletetermmeta['.$entry['meta_id'].']" class="button deletemeta small" value="'.esc_attr(__('Delete')).'" data-wp-lists="delete:the-list:termmeta-'.$entry['meta_id'].'::_ajax_nonce='.$delete_nonce.'">';
        $r .= "\n\t\t";
        //	$r .= get_submit_button( __( 'Update' ), 'updatetermmeta small', "termmeta-{$entry['meta_id']}-submit", false, array( 'data-wp-lists' => "add:the-list:termmeta-{$entry['meta_id']}::_ajax_nonce-add-termmeta=$update_nonce" ) );
        $r .= '<input type="button" name="termmeta['.$entry['meta_id'].']" id="termmeta-'.$entry['meta_id'].'-submit" class="button updatemeta small" value="'.esc_attr(__('Update')).'" data-wp-lists="add:the-list:termmeta-'.$entry['meta_id'].'::_ajax_nonce-add-termmeta='.$update_nonce.'">';
        $r .= "</div>";
        $r .= wp_nonce_field('change-termmeta', '_ajax_nonce', false, false);
        $r .= "</td>";

        $r .= "\n\t\t<td><label class='screen-reader-text' for='termmeta-{$entry['meta_id']}-value'>" . __('Value') . "</label><textarea name='termmeta[{$entry['meta_id']}][value]' id='termmeta-{$entry['meta_id']}-value' rows='2' cols='30'>{$entry['meta_value']}</textarea></td>\n\t</tr>";
        return $r;
    }

    public function has_meta($term_id)
    {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare("SELECT meta_key, meta_value, meta_id, term_id
            FROM $wpdb->termmeta WHERE term_id = %d
            ORDER BY meta_key,meta_id", $term_id), ARRAY_A);
    }

    public function update_meta()
    {
        if (!$tag_ID = (int) $_POST['tag_ID']) {
            return;
        }
        if (isset($_POST['termmeta']) && $_POST['termmeta']) {
            foreach ($_POST['termmeta'] as $key => $value) {
                if (!$meta = get_metadata_by_mid('term', $key)) {
                    continue;
                }
                if ($meta->term_id != $tag_ID) {
                    continue;
                }
                if (is_protected_meta($meta->meta_key, 'term') || ! current_user_can('edit_term_meta', $tag_ID, $meta->meta_key)) {
                    continue;
                }
                if (is_protected_meta($value['key'], 'term') || ! current_user_can('edit_term_meta', $tag_ID, $value['key'])) {
                    continue;
                }
                //			if(!empty($value['key']) && !empty($value['value'])) update_metadata_by_mid( 'term', $key, $value['value'], $value['key'] );
                if (!empty($value['key'])) {
                    update_metadata_by_mid('term', $key, wp_unslash($value['value']), $value['key']);
                } else {
                    delete_metadata_by_mid('term', $key);
                }
            }
        }

        if (isset($_POST['deletetermmeta']) && $_POST['deletetermmeta']) {
            foreach ($_POST['deletetermmeta'] as $key => $value) {
                if (!$meta = get_metadata_by_mid('term', $key)) {
                    continue;
                }
                if ($meta->term_id != $tag_ID) {
                    continue;
                }
                if (is_protected_meta($meta->meta_key, 'term') || ! current_user_can('delete_term_meta', $tag_ID, $meta->meta_key)) {
                    continue;
                }
                delete_metadata_by_mid('term', $key);
            }
        }
    }

    //ajax stuff
    public function ajax_delete_termmeta()
    {
        $id = isset($_POST['id']) ? (int) $_POST['id'] : 0;

        check_ajax_referer("delete-termmeta_$id");
        if (!$meta = get_metadata_by_mid('term', $id)) {
            wp_die(1);
        }

        if (is_protected_meta($meta->meta_key, 'term') || ! current_user_can('delete_term_meta', $meta->term_id, $meta->meta_key)) {
            wp_die(-1);
        }
        if (delete_metadata_by_mid('term', $meta->meta_id)) {
            wp_die(1);
        }
        wp_die(0);
    }

    public function ajax_add_termmeta()
    {
        check_ajax_referer('add-termmeta', '_ajax_nonce-add-termmeta');
        $c = 0;
        $term_id = (int) $_POST['term_id'];
        $term = get_term($term_id);

        if (isset($_POST['metakeyselect']) || isset($_POST['metakeyinput'])) {
            if (!current_user_can('edit_term', $term_id)) {
                wp_die(-1);
            }
            if (isset($_POST['metakeyselect']) && '#NONE#' == $_POST['metakeyselect'] && empty($_POST['metakeyinput'])) {
                wp_die(1);
            }

            if (! $mid = $this->add_meta($term_id)) {
                wp_die(__('Please provide a custom field value.'));
            }

            $meta = get_metadata_by_mid('term', $mid);
            $term_id = (int) $meta->term_id;
            $meta = get_object_vars($meta);
            $x = new WP_Ajax_Response(array(
            'what' => 'termmeta',
            'id' => $mid,
            'data' => $this->_list_meta_row($meta, $c),
            'position' => 1,
            'supplemental' => array('termid' => $term_id)
        ));
        } else { // Update?
            $mid = (int) key($_POST['termmeta']);
            $key = wp_unslash($_POST['termmeta'][$mid]['key']);
            $value = wp_unslash($_POST['termmeta'][$mid]['value']);
            if ('' == trim($key)) {
                wp_die(__('Please provide a custom field name.'));
            }
            if ('' == trim($value)) {
                wp_die(__('Please provide a custom field value.'));
            }
            if (! $meta = get_metadata_by_mid('term', $mid)) {
                wp_die(0);
            } // if meta doesn't exist
            if (is_protected_meta($meta->meta_key, 'term') || is_protected_meta($key, 'term') ||
            ! current_user_can('edit_term_meta', $meta->term_id, $meta->meta_key) ||
            ! current_user_can('edit_term_meta', $meta->term_id, $key)) {
                wp_die(-1);
            }
            if ($meta->meta_value != $value || $meta->meta_key != $key) {
                if (!$u = update_metadata_by_mid('term', $mid, $value, $key)) {
                    wp_die(0);
                } // We know meta exists; we also know it's unchanged (or DB error, in which case there are bigger problems).
            }

            $x = new WP_Ajax_Response(array(
            'what' => 'termmeta',
            'id' => $mid, 'old_id' => $mid,
            'data' => $this->_list_meta_row(array(
                'meta_key' => $key,
                'meta_value' => $value,
                'meta_id' => $mid
            ), $c),
            'position' => 0,
            'supplemental' => array('termid' => $meta->term_id)
        ));
        }
        $x->send();
    }

    public function add_meta($term_id)
    {
        $term_id = (int) $term_id;

        $metakeyselect = isset($_POST['metakeyselect']) ? wp_unslash(trim($_POST['metakeyselect'])) : '';
        $metakeyinput = isset($_POST['metakeyinput']) ? wp_unslash(trim($_POST['metakeyinput'])) : '';
        $metavalue = isset($_POST['metavalue']) ? $_POST['metavalue'] : '';
        if (is_string($metavalue)) {
            $metavalue = trim(wp_unslash($metavalue));
        }

        if (('0' === $metavalue || ! empty($metavalue)) && ((('#NONE#' != $metakeyselect) && !empty($metakeyselect)) || !empty($metakeyinput))) {
            /*
             * We have a key/value pair. If both the select and the input
             * for the key have data, the input takes precedence.
             */
            if ('#NONE#' != $metakeyselect) {
                $metakey = $metakeyselect;
            }

            if ($metakeyinput) {
                $metakey = $metakeyinput;
            } // default

            if (is_protected_meta($metakey, 'term') || ! current_user_can('add_term_meta', $term_id, $metakey)) {
                return false;
            }

            $metakey = wp_slash($metakey);

            return add_term_meta($term_id, $metakey, $metavalue);
        }

        return false;
    } // add_meta
} //class
term_custom_fields_support::instance();
