<?php
class taxonomy_supports_settings
{
  const OPTIONS = __CLASS__;
  const ADMINSLUG =__CLASS__;
  private $taxonomies;
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
    public function __construct()
    {
//delete_option(self::OPTIONS);
      if (is_admin()) {
          add_action('admin_menu', array($this, 'admin_menu'));
      }
      add_action('init',[$this, 'overrides'],PHP_INT_MAX);
    }
    public function overrides() {
      $overrides = $this->get_option('overrides');
//echo '<pre>'.var_export($overrides,true).'</pre>';//die();
      if(empty($overrides)) return;
      $existing_features = apply_filters('taxonomy_supports_options',[]);
      foreach($overrides as $taxonomy=>$features) {
        foreach($features as $feature=>$value) {
          if(in_array($feature, $existing_features)) {
            if($value) add_taxonomy_support($taxonomy, $feature);
            else remove_taxonomy_support($taxonomy, $feature);
          }
        }
      }
    }

    //admin stuff
    public function admin_menu()
    {
        add_filter('plugin_action_links_'.self::ADMINSLUG, array($this, 'add_settings_link'));
        $plugin_page = add_options_page(__('Taxonomy supports'), __('Taxonomy supports'), 'manage_options', self::ADMINSLUG, array($this, 'options_page'));
        if (class_exists('download_plugin')) new download_plugin($plugin_page);
        register_setting(self::ADMINSLUG, self::OPTIONS, array($this, 'validate_options'));
//        add_settings_section('default', '', false, self::ADMINSLUG);
//        add_settings_field(__CLASS__, __('Defaults'), array($this, 'print_fields'), self::ADMINSLUG, 'default');
    }

    public function add_settings_link($links)
    {
        $url = admin_url('options-general.php?page='.self::ADMINSLUG);
        $links[] = '<a href="' . $url . '">' . __('Settings') . '</a>';
        return $links;
    }

    public function options_page()
    {
        ?>
 <div class="wrap">
   <?php screen_icon(); ?>
   <h1><?php _e('Taxonomy supports settings'); ?></h1>
   <form method="POST" action="options.php"><?php
        settings_fields(self::ADMINSLUG);
//        do_settings_sections(self::ADMINSLUG);
        $name =self::OPTIONS.'[overrides]';
        global $_wp_taxonomy_features;
        $existing_features = apply_filters('taxonomy_supports_options',[]);
        foreach(array_keys($_wp_taxonomy_features) as $slug) {
          $taxonomy = get_taxonomy($slug);
//          echo '<pre>'.var_export($taxonomy,true).'</pre>';
          ?><p><b><?php echo __($taxonomy->label) ?></b> (<?php echo __($taxonomy->name) ?>): &nbsp;<?php
          foreach($existing_features as $feature) {
            ?><input class="checkbox" type="checkbox"<?php checked($_wp_taxonomy_features[$slug][$feature]) ?> name="<?php echo $name ?>[<?php echo $slug ?>][<?php echo $feature ?>]" value="true" /><?php echo $feature ?>&nbsp;<?php
          }
          ?></p><?php
        }
        submit_button(); ?>
   </form>
 </div><?php
//echo '<pre>'.var_export(apply_filters('taxonomy_supports_options',[]),true).'</pre>';
//echo '<pre>'.var_export($_wp_taxonomy_features,true).'</pre>';
//echo '<pre>'.var_export($this->get_option(),true).'</pre>';

//echo '<pre>'.var_export($this->get_all_taxonomies(),true).'</pre>';
    }


    public function validate_options($options)
    {
//echo '<pre>'.var_export($options,true).'<hr>';
      $overrides = [];
      global $_wp_taxonomy_features;
      $features = apply_filters('taxonomy_supports_options',[]);
      foreach(array_keys($_wp_taxonomy_features) as $taxonomy) {
        foreach($features as $feature) {
          $overrides[$taxonomy][$feature] = isset($options['overrides'][$taxonomy][$feature]) ? $options['overrides'][$taxonomy][$feature] : false;
        }
      }
      $options['overrides'] = $overrides;
//return [];
//echo '<pre>'.var_export($options,true).'</pre>';die();
        return  $options;
    }

    public function get_option($field=null, $default=null)
    {
//delete_option(self::OPTIONS);
      $options = get_option(self::OPTIONS,['overrides'=>[]]);
      return $field ? (isset($options[$field]) ? $options[$field] : $default) : $options;
    }
} //class
taxonomy_supports_settings::instance();
