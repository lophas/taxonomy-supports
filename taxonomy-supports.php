<?php
/*
    Plugin Name: Taxonomy Supports
    Description: Adds taxonomy supports for date, author, thumbnail, editor and meta_boxes
    Version: 3.6
    Plugin URI: https://github.com/lophas/taxonomy-supports
    GitHub Plugin URI: https://github.com/lophas/taxonomy-supports
    Author: Attila Seres
    Author URI:

Features:
- Same UI for editing terms as the builtin post editor
- Utilizes the standard Wordpress metabox system with postbox drag&drop, open/close functionality
- 3rd party plugin fields and addons automatically converted to metaboxes
- Custom Fields metabox for Terms provides same meta field management as the builtin metabox for posts
- Admin menu to select applicable taxonomies

Howto register your own metaboxes:
use the builtin add_meta_box function, you may use following hooks:
do_action( 'add_termmeta_boxes', string $taxonomy, WP_Term $term )
do_action( "add_termmeta_boxes_{$taxonomy}", WP_Term $term )

More hooks described in the README file

*/
if (!class_exists('taxonomy_supports')) :
class taxonomy_supports
{
    private static $_instance;
    public static function instance()
    {
        if (!isset(self::$_instance)) {
            self::$_instance =  new self;
        }
        return self::$_instance;
    }
    public $support_dir;
    public function __construct()
    {
        $this->support_dir = __DIR__.'/'.substr(basename(__FILE__), 0, -4);
        if (!file_exists($this->support_dir)) {
            $this->support_dir = __DIR__.'/supports';
        }
        if (is_dir($this->support_dir)) {
            foreach (scandir($this->support_dir) as $file) {
                if (substr($file, -4) == '.php') {
                    require_once($this->support_dir.'/'.$file);
                }
            }
        }
    }
}
taxonomy_supports::instance();


add_action('plugins_loaded', function () {
    if (function_exists('add_taxonomy_support')) {
        return;
    }
    function add_taxonomy_support($taxonomy, $features)
    {
        global $_wp_taxonomy_features;
        foreach ((array)$features as $feature) {
            if (func_num_args() == 2) {
                $_wp_taxonomy_features[$taxonomy][$feature] = true;
            } else {
                $_wp_taxonomy_features[$taxonomy][$feature] = array_slice(func_get_args(), 2);
            }
        }
    }
    function remove_taxonomy_support($taxonomy, $features)
    {
        global $_wp_taxonomy_features;
        foreach ((array)$features as $feature) {
            unset($_wp_taxonomy_features[ $taxonomy ][ $feature ]);
        }
    }
    function taxonomy_supports($taxonomy, $feature = null)
    {
        global $_wp_taxonomy_features;
        return isset($feature) ? (isset($_wp_taxonomy_features[$taxonomy][$feature]) ? $_wp_taxonomy_features[$taxonomy][$feature] : false) : array_keys((array)$_wp_taxonomy_features[$taxonomy]);
    }
    add_action('registered_taxonomy', function ($taxonomy, $object_type, array $taxonomy_object) {
        if(!$taxonomy_object['show_ui']) return;
        global $_wp_taxonomy_features;
        if (isset($taxonomy_object['supports'])) {
            $supports = (array)$taxonomy_object['supports'];
        } else {
            $supports = array_unique(apply_filters('taxonomy_supports_defaults', array(), $taxonomy, $taxonomy_object));
        }
        if (!empty($supports)) {
            $_wp_taxonomy_features[$taxonomy] = array_fill_keys($supports, true);
        }
    }, 10, 3);
    add_action( 'unregistered_taxonomy', function($taxonomy) {
      global $_wp_taxonomy_features;
      unset($_wp_taxonomy_features[$taxonomy]);
    } );
});
endif;
