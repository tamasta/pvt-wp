<?php
namespace UipressPro\Classes\uipApp;
use UipressPro\Classes\PostTypes\Folders;
use UipressPro\Classes\PostTypes\AdminMenus;
use UipressLite\Classes\Utils\Sanitize;
use UipressPro\Classes\Extensions\Folders\FoldersApp;
use UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp;
use UipressPro\Classes\Extensions\UserManagement\UserManagementApp;
use UipressLite\Classes\Utils\Objects;

!defined('ABSPATH') ? exit() : '';

class SiteSettings
{
  private static $uip_site_settings_object = null;
  /**
   * Mounts main setting on plugins loaded to prevent loading early
   *
   * @since 3.2.0
   */
  public static function start()
  {
    add_action('plugins_loaded', ['UipressPro\Classes\uipApp\SiteSettings', 'set_site_settings'], 2);
  }

  /**
   * Calls appropriate functions if the settings are set and defined
   *
   * @since 3.0.92
   */
  public static function set_site_settings()
  {
    // Settings isn't defined so exit
    if (!defined('uip_site_settings')) {
      return;
    }

    // Set main settings object
    self::$uip_site_settings_object = json_decode(uip_site_settings);

    // Loads extensions
    self::load_extensions();

    add_action('admin_head', ['UipressPro\Classes\uipApp\SiteSettings', 'add_head_code'], 99);
    add_filter('admin_title', ['UipressPro\Classes\uipApp\SiteSettings', 'custom_admin_title'], 99, 2);
    add_action('all_plugins', ['UipressPro\Classes\uipApp\SiteSettings', 'remove_uip_plugin_table'], 10, 1);
    add_action('admin_enqueue_scripts', ['UipressPro\Classes\uipApp\SiteSettings', 'add_scripts_and_styles']);
    add_filter('admin_body_class', ['UipressPro\Classes\uipApp\SiteSettings', 'push_role_to_body_class']);
    add_action('pre_get_posts', ['UipressPro\Classes\uipApp\SiteSettings', 'limit_user_library'], 10, 1);
  }

  /**
   * Conditionally loads extensions
   *
   * @since 3.1.1
   */
  private static function load_extensions()
  {
    // Start folders app
    $foldersEnabled = Objects::get_nested_property(self::$uip_site_settings_object, ['extensions', 'foldersEnabled']) ?? false;
    $foldersEnabled === 'uiptrue' ? Folders::start() : false;
    $foldersEnabled === 'uiptrue' ? FoldersApp::start() : false;

    $menusEnabled = Objects::get_nested_property(self::$uip_site_settings_object, ['extensions', 'menuCreatorEnabled']) ?? false;
    $menusEnabled === 'uiptrue' ? AdminMenus::start() : false;
    $menusEnabled === 'uiptrue' ? MenuCreatorApp::start() : false;

    $userManagementEnabled = Objects::get_nested_property(self::$uip_site_settings_object, ['extensions', 'userManagementEnabled']) ?? false;
    $userManagementEnabled === 'uiptrue' ? UserManagementApp::start() : false;
  }

  /**
   * Safely retrieve nested property from an object.
   *
   * @param object $object The base object.
   * @param array  $keys   An array of keys in the order to access nested property.
   *
   * @return mixed The value of the nested property or null if not found.
   */
  private static function get_nested_property($object, array $keys)
  {
    foreach ($keys as $key) {
      if (!is_object($object) || !property_exists($object, $key)) {
        return false;
      }
      $object = $object->$key;
    }
    return $object;
  }

  /**
   * Modifies post tables and media for non admins
   *
   * @since 3.0.7
   */
  public static function limit_user_library($wp_query)
  {
    // Early return if not in admin or user is an administrator.
    if (!is_admin() || current_user_can('administrator')) {
      return;
    }

    $privateMedia = Objects::get_nested_property(self::$uip_site_settings_object, ['media', 'privateLibrary']) ?? false;
    $privatePosts = Objects::get_nested_property(self::$uip_site_settings_object, ['postsPages', 'privatePosts']) ?? false;

    if ($privateMedia != 'uiptrue' && $privatePosts != 'uiptrue') {
      return;
    }

    global $current_user;

    $query = $wp_query->query;
    if (!isset($query['post_type'])) {
      return;
    }
    $postType = $wp_query->query['post_type'];
    $containsMedia = false;
    $containsPosts = false;

    if (is_array($postType)) {
      if (in_array('attachment', $postType)) {
        $containsMedia = true;
      }
      if (in_array('post', $postType) || in_array('page', $postType)) {
        $containsPosts = true;
      }
    } else {
      if ($postType == 'attachment') {
        $containsMedia = true;
      }
      if ($postType == 'post' || $postType == 'page') {
        $containsPosts = true;
      }
    }

    if ($privateMedia == 'uiptrue' && $containsMedia) {
      $wp_query->set('author', $current_user->ID);
    }

    if ($privatePosts == 'uiptrue' && $containsPosts) {
      $wp_query->set('author', $current_user->ID);
      add_filter('views_edit-post', ['UipressPro\Classes\uipApp\SiteSettings', 'fix_post_table_counts'], 10, 1);
      add_filter('views_edit-page', ['UipressPro\Classes\uipApp\SiteSettings', 'fix_post_table_counts'], 10, 1);
    }
  }

  /**
   * Corrects post count for post tables when private library is enabled
   *
   * @since 3.0.7
   */
  public static function fix_post_table_counts($views)
  {
    global $current_user, $wp_query;

    $postType = $wp_query->query['post_type'];
    if (is_array($postType)) {
      if (in_array('post', $postType)) {
        $current = 'post';
      }
      if (in_array('page', $postType)) {
        $current = 'page';
      }
    } else {
      $current = $postType;
    }

    unset($views['mine']);
    $types = [['status' => null], ['status' => 'publish'], ['status' => 'draft'], ['status' => 'pending'], ['status' => 'trash']];
    foreach ($types as $type) {
      $query = [
        'author' => $current_user->ID,
        'post_type' => $current,
        'post_status' => $type['status'],
      ];
      $result = new \WP_Query($query);
      if ($type['status'] == null):
        $class = $wp_query->query_vars['post_status'] == null ? ' class="current"' : '';
        $views['all'] = sprintf('<a href="%1$s"%2$s>%4$s <span class="count">(%3$d)</span></a>', admin_url('edit.php?post_type=' . $current), $class, $result->found_posts, __('All'));
      elseif ($type['status'] == 'publish'):
        $class = $wp_query->query_vars['post_status'] == 'publish' ? ' class="current"' : '';
        $views['publish'] = sprintf(
          '<a href="%1$s"%2$s>%4$s <span class="count">(%3$d)</span></a>',
          admin_url('edit.php?post_status=publish&post_type=' . $current),
          $class,
          $result->found_posts,
          __('Publish')
        );
      elseif ($type['status'] == 'draft'):
        $class = $wp_query->query_vars['post_status'] == 'draft' ? ' class="current"' : '';
        $views['draft'] = sprintf(
          '<a href="%1$s"%2$s>%4$s <span class="count">(%3$d)</span></a>',
          admin_url('edit.php?post_status=draft&post_type=' . $current),
          $class,
          $result->found_posts,
          __('Draft')
        );
      elseif ($type['status'] == 'pending'):
        $class = $wp_query->query_vars['post_status'] == 'pending' ? ' class="current"' : '';
        $views['pending'] = sprintf(
          '<a href="%1$s"%2$s>%4$s <span class="count">(%3$d)</span></a>',
          admin_url('edit.php?post_status=pendingpost_type=' . $current),
          $class,
          $result->found_posts,
          __('Pending')
        );
      elseif ($type['status'] == 'trash'):
        $class = $wp_query->query_vars['post_status'] == 'trash' ? ' class="current"' : '';
        $views['trash'] = sprintf(
          '<a href="%1$s"%2$s>%4$s <span class="count">(%3$d)</span></a>',
          admin_url('edit.php?post_status=trash&post_type=' . $current),
          $class,
          $result->found_posts,
          __('Trash')
        );
      endif;
    }

    return $views;
  }

  /**
   * Adds current roles as body classes on the admin
   *
   * @since 3.0.3
   */
  public static function push_role_to_body_class($classes)
  {
    $addHead = Objects::get_nested_property(self::$uip_site_settings_object, ['advanced', 'addRoleToBody']) ?? false;

    // Exit if setting doesn't exist
    if (!$addHead || $addHead != 'uiptrue') {
      return $classes;
    }

    $user = new \WP_User(get_current_user_id());

    if (!empty($user->roles) && is_array($user->roles)) {
      foreach ($user->roles as $role) {
        $classes .= ' ' . strtolower($role);
      }
    }

    return $classes;
  }

  /**
   * Adds user enqueued scripts and styles
   *
   * @since 3.0.92
   */
  public static function add_scripts_and_styles()
  {
    $scripts = Objects::get_nested_property(self::$uip_site_settings_object, ['advanced', 'enqueueScripts']) ?? false;

    if (is_array($scripts)) {
      foreach ($scripts as $script) {
        wp_enqueue_script($script->id, $script->value, []);
      }
    }

    $styles = Objects::get_nested_property(self::$uip_site_settings_object, ['advanced', 'enqueueStyles']) ?? false;

    if (is_array($styles)) {
      foreach ($styles as $style) {
        wp_register_style($style->id, $style->value, []);
        wp_enqueue_style($style->id);
      }
    }
  }

  /**
   * Filters admin side page titles
   *
   * @param string $admin_title
   * @param string $title
   *
   * @since 3.2.0
   */
  public static function custom_admin_title($admin_title, $title)
  {
    $customtitle = Objects::get_nested_property(self::$uip_site_settings_object, ['whiteLabel', 'siteTitle']) ?? false;
    if ($customtitle && $customtitle !== 'uipblank') {
      $admin_title = str_replace('WordPress', $customtitle, $admin_title);
    }
    return $admin_title;
  }

  /**
   * Adds user code to the head of admin pages and adds favicon if set
   *
   * @since 3.0.92
   */
  public static function add_head_code()
  {
    $favicon = Objects::get_nested_property(self::$uip_site_settings_object, ['whiteLabel', 'siteFavicon', 'url']) ?? false;
    if ($favicon) {
      echo '<link rel="shortcut icon" type="image/x-icon" href="' . esc_url($favicon) . '" />';
    }

    // Exit if not on framed page
    if (!isset($_GET['uip-framed-page']) || $_GET['uip-framed-page'] != '1') {
      return;
    }

    $code = Objects::get_nested_property(self::$uip_site_settings_object, ['advanced', 'htmlHead']) ?? false;

    if (!$code || $code == '' || $code == 'uipblank') {
      return;
    }

    $clean_code = Sanitize::clean_input_with_code($code);

    echo html_entity_decode($clean_code);
  }

  /**
   * Hides uipress from plugins table
   *
   * @since 3.0.92
   */
  public static function remove_uip_plugin_table($all_plugins)
  {
    $hidden = Objects::get_nested_property(self::$uip_site_settings_object, ['whiteLabel', 'hidePlugins']) ?? false;

    if ($hidden == 'uiptrue') {
      unset($all_plugins['uipress-lite/uipress-lite.php']);
      unset($all_plugins['uipress-pro/uipress-pro.php']);
      return $all_plugins;
    }
    return $all_plugins;
  }
}
