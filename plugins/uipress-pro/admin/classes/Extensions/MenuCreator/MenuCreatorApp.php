<?php

namespace UipressPro\Classes\Extensions\MenuCreator;
use UipressLite\Classes\Utils\Sanitize;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\App\UserPreferences;
use UipressLite\Classes\App\UipOptions;
use UipressLite\Classes\Utils\Objects;
use UipressLite\Classes\Utils\Dates;

!defined('ABSPATH') ? exit() : '';

class MenuCreatorApp
{
  private static $uipMasterMenu = false;
  private static $discoveredItems = false;
  private static $unedited_menu = false;
  private static $unedited_submenu = false;

  /**
   * Loads menu editor actions
   *
   * @since 3.2.0
   */
  public static function start()
  {
    // Exit early if we are on network admin
    if (function_exists('is_network_admin') && is_network_admin()) {
      return;
    }

    // Adds menu creator item to menu
    add_action('admin_menu', ['UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp', 'add_menu_item']);

    // Load page specific actions
    self::load_page_specific_actions();

    // Queries menus and checks if current user has menu
    add_action('admin_menu', ['UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp', 'fetch_custom_menu'], 999);

    // Ajax functions
    add_action('wp_ajax_uipress_get_menus', ['UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp', 'list_menus']);
    add_action('wp_ajax_uip_duplicate_admin_menu', ['UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp', 'duplicate_admin_menu']);
    add_action('wp_ajax_uip_delete_admin_menus', ['UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp', 'delete_admin_menus']);
    add_action('wp_ajax_uipress_get_menu', ['UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp', 'get_menu']);
    add_action('wp_ajax_uip_create_new_admin_menu', ['UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp', 'create_new_admin_menu']);
    add_action('wp_ajax_uipress_save_menu', ['UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp', 'save_menu']);
  }

  /**
   * Adds menu editor page to settings
   *
   * @since 1.4
   */
  public static function add_menu_item()
  {
    add_options_page(__('UIP Menu Builder', 'uipress-pro'), __('Menu Builder', 'uipress-pro'), 'manage_options', 'uip-menu-creator', [
      'UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp',
      'start_menu_creator_app',
    ]);
  }

  /**
   * Adds actions specific to the menu builder appp
   *
   * @return void
   * @since 3.2.0
   */
  private static function load_page_specific_actions()
  {
    $pageName = uip_plugin_shortname . '-menu-creator';

    // Exit early if we are not on the menu builder page
    if (!isset($_GET['page']) || $_GET['page'] != $pageName) {
      return;
    }

    // Capture the menu for the editor
    add_action('admin_menu', ['UipressPro\Classes\Extensions\MenuCreator\MenuCreatorApp', 'capture_wp_menu_for_editor'], 998);
  }

  /**
   * Creates menu editor page
   *
   * @since 1.4
   */
  public static function start_menu_creator_app()
  {
    $styleSRC = uip_plugin_url . 'assets/css/uip-app.css';
    $styleSRCRTL = uip_plugin_url . 'assets/css/uip-app-rtl.css';

    $styleSRC = is_rtl() ? $styleSRCRTL : $styleSRC;

    $appContainer = '
    <div id="uip-menu-creator-app" class="uip-text-normal uip-background-default"></div>';

    echo Sanitize::clean_input_with_code($appContainer);

    $styles = "
      <style>
        @import '{$styleSRC}';
        #wpcontent{padding-left: 0}#wpfooter{display: none}#wpbody-content{padding:0}
      </style>";

    echo Sanitize::clean_input_with_code($styles);

    $ajaxURL = admin_url('admin-ajax.php');
    $security = wp_create_nonce('uip-security-nonce');

    $variableFormatter = "
        var uip_ajax = {
          ajax_url: '{$ajaxURL}',
          security: '{$security}',
        };";
    wp_print_inline_script_tag($variableFormatter, ['id' => 'uip-format-vars']);

    $url = uip_pro_plugin_url;
    $version = uip_pro_plugin_version;
    $folderScript = "{$url}/app/dist/menucreator.build.js?ver={$version}";
    $isMultisite = is_multisite() ? 'true' : 'false';
    $isPrimarySite = is_main_site() ? 'true' : 'false';

    wp_print_script_tag([
      'id' => 'uip-menu-creator-app-data',
      'src' => $folderScript,
      'type' => 'module',
      'uipress-lite' => plugins_url('uipress-lite/'),
      'async' => true,
      'ajax_url' => $ajaxURL,
      'security' => $security,
      'isMultisite' => $isMultisite,
      'isPrimarySite' => $isPrimarySite,
      'mastermenu' => json_encode(self::$uipMasterMenu),
    ]);
  }

  /**
   * Gets WordPress menu and process it, stores in variable for front end app
   *
   * @since 3.0.9
   */
  public static function fetch_custom_menu()
  {
    // Fetch templates from primary multisite installation Multisite
    $multiSiteActive = false;
    if (is_multisite() && is_plugin_active_for_network(uip_plugin_path_name . '/uipress-lite.php') && !is_main_site()) {
      $multiSiteActive = true;
    }

    $metaQuery = self::build_custom_menu_query($multiSiteActive);

    // Switch to main blog to run query
    if ($multiSiteActive) {
      $mainSiteId = get_main_site_id();
      switch_to_blog($mainSiteId);
    }

    //Build query
    $args = [
      'post_type' => 'uip-admin-menu',
      'posts_per_page' => 1,
      'post_status' => 'publish',
      'meta_query' => $metaQuery,
    ];

    $query = new \WP_Query($args);
    $totalFound = $query->found_posts;
    $foundPosts = $query->get_posts();

    // No menus so abort
    if ($totalFound == 0) {
      if ($multiSiteActive) {
        restore_current_blog();
      }
      return;
    }

    $menuID = $foundPosts[0]->ID;
    $menuSettings = get_post_meta($menuID, 'uip_menu_settings', true);

    if ($multiSiteActive) {
      restore_current_blog();
    }

    // Exit early if menu is not an object
    if (!is_object($menuSettings)) {
      return;
    }

    // Handles custom menu application
    $menuHandler = function ($mastermenu) use ($menuSettings, $menuID) {
      $mastermenu['menu'] = Objects::convertObjectsToArrays($menuSettings->menu->menu);
      $mastermenu['submenu'] = $menuSettings->menu->submenu;
      $mastermenu['custom'] = true;
      $mastermenu['menu_id'] = $menuID;
      return $mastermenu;
    };

    add_filter('uipress/admin/menus/update', $menuHandler);
  }

  /**
   * Builds custom meta query for finding menus applied to user
   *
   * @param boolean - $multiSiteActive
   * @return object - meta query object
   * @since 3.2.0
   */
  private static function build_custom_menu_query($multiSiteActive)
  {
    // Get user details
    $current_user = wp_get_current_user();
    $id = $current_user->ID;
    $username = $current_user->user_login;

    $roles = $id == 1 ? ['Super Admin'] : [];

    //Get current roles
    $user = new \WP_User($id);

    if (!empty($user->roles) && is_array($user->roles)) {
      $roles = array_merge($roles, $user->roles);
    }

    $idAsString = strval($id);

    // Loop through roles and build query
    $roleQuery = [];
    $roleQuery['relation'] = 'AND';

    // Check user id is not excluded
    $roleQuery[] = [
      'key' => 'uip-menu-excludes-users',
      'value' => serialize($idAsString),
      'compare' => 'NOT LIKE',
    ];

    // Check rolename is not excluded
    foreach ($roles as $role) {
      $roleQuery[] = [
        'key' => 'uip-menu-excludes-roles',
        'value' => serialize($role),
        'compare' => 'NOT LIKE',
      ];
    }

    // Multisite Only
    // Push a check to see if the template is multisite enabled
    if ($multiSiteActive) {
      $roleQuery[] = [
        'key' => 'uip-menu-subsites',
        'value' => 'uiptrue',
        'compare' => '==',
      ];
    }

    // Check at least one option (roles or users) has a value
    $secondLevel = [];
    $secondLevel['relation'] = 'OR';
    $secondLevel[] = [
      'key' => 'uip-menu-for-users',
      'value' => serialize([]),
      'compare' => '!=',
    ];
    $secondLevel[] = [
      'key' => 'uip-menu-for-roles',
      'value' => serialize([]),
      'compare' => '!=',
    ];

    // Check user if user id is in selected
    $thirdLevel = [];
    $thirdLevel['relation'] = 'OR';
    $thirdLevel[] = [
      'key' => 'uip-menu-for-users',
      'value' => serialize($idAsString),
      'compare' => 'LIKE',
    ];

    foreach ($roles as $role) {
      $thirdLevel[] = [
        'key' => 'uip-menu-for-roles',
        'value' => serialize($role),
        'compare' => 'LIKE',
      ];
    }

    // Push to meta query
    $roleQuery[] = $secondLevel;
    $roleQuery[] = $thirdLevel;

    return $roleQuery;
  }

  /**
   * Captures the WordPress menu and processes it, storing it in a variable for the front-end app.
   *
   * @since 2.2
   */
  public static function capture_wp_menu_for_editor()
  {
    global $menu, $submenu;

    $processedMenu = [];
    $processedSubmenu = [];

    foreach ($menu as $key => $item) {
      $item['order'] = $key;
      $item['type'] = strpos($item[4] ?? '', 'wp-menu-separator') !== false ? 'sep' : 'item';
      $item['uip_uid'] = hash('ripemd160', $item[2] . ($item['type'] === 'sep' ? $item[4] : $item[5]));
      $item['cleanName'] = explode('<', wptexturize($item[0]))[0];
      $item['custom'] = new \stdClass();

      $processedMenu[] = $item;
    }

    foreach ($submenu as $key => $subItems) {
      foreach ($subItems as $sub) {
        $sub['type'] = strpos($sub[4] ?? '', 'wp-menu-separator') !== false ? 'sep' : 'item';
        $sub['uip_uid'] = hash('ripemd160', $sub[2] . ($sub['type'] === 'sep' ? $sub[4] : $sub[1]));
        $sub['cleanName'] = explode('<', wptexturize($sub[0]))[0] ?? '';
        $sub['custom'] = new \stdClass();

        $processedSubmenu[$key][] = $sub;
      }
    }

    usort($processedMenu, fn($a, $b) => $a['order'] <=> $b['order']);

    self::$uipMasterMenu = [
      'menu' => $processedMenu,
      'submenu' => $processedSubmenu,
    ];
  }

  /**
   * Fetches users and roles
   *
   * @since 2.0.8
   */
  public static function list_menus()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    //Get template
    $args = [
      'post_type' => 'uip-admin-menu',
      'posts_per_page' => -1,
    ];

    $query = new \WP_Query($args);
    $foundPosts = $query->get_posts();

    $formattedmenus = [];

    foreach ($query->get_posts() as $menu) {
      $humandate = Dates::getHumanDate($menu->ID);
      $status = get_post_status($menu->ID) === 'publish' ? 'uiptrue' : 'uipfalse';

      $temp = [];
      $temp['name'] = get_the_title($menu->ID);
      $temp['modified'] = $humandate;
      $temp['status'] = $status;

      $options = get_post_meta($menu->ID, 'uip_menu_settings', true);
      if (is_object($options)) {
        $optionsArray = (array) $options;
        $temp = array_merge($temp, $optionsArray);
      } else {
        $temp['appliesTo'] = [];
        $temp['excludes'] = [];
      }

      $temp['id'] = $menu->ID;
      $formattedmenus[] = $temp;
    }

    $returndata['menus'] = $formattedmenus;
    $returndata['totalFound'] = $query->found_posts;
    $returndata['totalPages'] = $query->max_num_pages;
    wp_send_json($returndata);
  }

  /**
   * Deletes menus: accepts either single id or array of ids
   *
   * @since 3.0.9
   */
  public static function delete_admin_menus()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $menuids = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['menuids'])));

    if (!current_user_can('manage_options')) {
      $message = __('You don\'t have the correct permissions to delete menus', 'uipress-pro');
      Ajax::error($message);
    }

    if (!is_array($menuids) && is_numeric($menuids)) {
      wp_delete_post($menuids, true);
      $returndata = [];
      $returndata['success'] = true;
      $returndata['message'] = __('Menu deleted', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (is_array($menuids)) {
      foreach ($menuids as $id) {
        wp_delete_post($id, true);
      }

      $returndata = [];
      $returndata['success'] = true;
      $returndata['message'] = __('Menus deleted', 'uipress-pro');
      wp_send_json($returndata);
    }
  }

  /**
   * Gets menu for editing
   * @since 3.0.9
   */
  public static function get_menu()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $id = sanitize_text_field($_POST['id']);

    if (!$id || $id == '') {
      $message = __('Uanble to load menu', 'uipress-pro');
      Ajax::error($message);
    }

    $menuOptions = get_post_meta($id, 'uip_menu_settings', true);

    if (is_object($menuOptions)) {
      $menuOptions->name = get_the_title($id);
    } else {
      $menuOptions = new \stdClass();
      $menuOptions->menu = [];
      $menuOptions->appliesTo = [];
      $menuOptions->excludes = [];
      $menuOptions->name = get_the_title($id);
      $menuOptions->autoUpdate = 'uipfalse';
      $menuOptions->status = 'uipfalse';
    }

    $returndata = [];
    $returndata['menuOptions'] = $menuOptions;
    $returndata['success'] = true;
    $returndata['message'] = __('Menu fetched', 'uipress-pro');
    wp_send_json($returndata);
  }

  /**
   * Deletes menus: accepts either single id or array of ids
   *
   * @since 3.0.9
   */
  public static function duplicate_admin_menu()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $copyID = sanitize_text_field($_POST['id']);

    if (!current_user_can('manage_options')) {
      $message = __('You don\'t have the correct permissions to edit this menu', 'uipress-pro');
      Akax::error($message);
    }

    $updateArgs = [
      'post_title' => wp_strip_all_tags(get_the_title($copyID) . ' ' . __('copy')),
      'post_status' => 'draft',
      'post_type' => 'uip-admin-menu',
    ];

    $updatedID = wp_insert_post($updateArgs);

    //Update meta
    update_post_meta($updatedID, 'uip_menu_settings', get_post_meta($copyID, 'uip_menu_settings', true));
    update_post_meta($updatedID, 'uip-menu-for-roles', get_post_meta($copyID, 'uip-menu-for-roles', true));
    update_post_meta($updatedID, 'uip-menu-for-users', get_post_meta($copyID, 'uip-menu-for-users', true));
    update_post_meta($updatedID, 'uip-menu-excludes-roles', get_post_meta($copyID, 'uip-menu-excludes-roles', true));
    update_post_meta($updatedID, 'uip-menu-excludes-users', get_post_meta($copyID, 'uip-menu-excludes-users', true));

    $returndata = [];
    $returndata['success'] = true;
    $returndata['message'] = __('Menu duplicated', 'uipress-pro');
    wp_send_json($returndata);
  }

  /**
   * Saves menu
   *
   * @since 3.0.9
   */
  public static function save_menu()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $menu = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['menu'])));

    if (!is_object($menu)) {
      $message = __('Unable to save menu', 'uipress-pro');
      Ajax::error($message);
    }

    if (!current_user_can('manage_options')) {
      $message = __('You don\'t have the correct permissions to edit this menu', 'uipress-pro');
      Ajax::error($message);
    }

    $status = property_exists($menu, 'status') ? $menu->status : 'draft';
    $status = $status == 'uiptrue' ? 'publish' : 'draft';
    $id = $menu->id;

    $updateArgs = [
      'ID' => $menu->id,
      'post_title' => wp_strip_all_tags($menu->name),
      'post_status' => $status,
    ];

    $updated = wp_update_post($updateArgs);

    update_post_meta($id, 'uip_menu_settings', $menu);

    // Template for
    $rolesAndUsers = is_array($menu->appliesTo) ? $menu->appliesTo : [];
    $roles = [];
    $users = [];
    foreach ($rolesAndUsers as $item) {
      switch ($item->type) {
        case 'User':
          $users[] = $item->id;
          break;

        case 'Role':
          $roles[] = $item->name;
          break;
      }
    }

    // Template not for
    $excludeRolesAndUsers = is_array($menu->excludes) ? $menu->excludes : [];
    $excludeRoles = [];
    $excludeUsers = [];
    foreach ($excludeRolesAndUsers as $item) {
      switch ($item->type) {
        case 'User':
          $excludeUsers[] = $item->id;
          break;

        case 'Role':
          $excludeRoles[] = $item->name;
          break;
      }
    }

    $applyToSubs = property_exists($menu, 'multisite') ? $menu->multisite : false;

    update_post_meta($id, 'uip-menu-for-roles', $roles);
    update_post_meta($id, 'uip-menu-for-users', $users);
    update_post_meta($id, 'uip-menu-excludes-roles', $excludeRoles);
    update_post_meta($id, 'uip-menu-excludes-users', $excludeUsers);
    update_post_meta($id, 'uip-menu-subsites', $applyToSubs);

    $returndata = [];
    $returndata['success'] = true;
    $returndata['message'] = __('Menu saved', 'uipress-pro');
    wp_send_json($returndata);
  }

  /**
   * Creates new admin menu
   *
   * @since 3.0.8
   */
  public static function create_new_admin_menu()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $my_post = [
      'post_title' => __('Admin menu (Draft)', 'uipress-pro'),
      'post_status' => 'draft',
      'post_type' => 'uip-admin-menu',
    ];

    // Insert the post into the database.
    $postID = wp_insert_post($my_post);

    if ($postID) {
      $returndata = [];
      $returndata['success'] = true;
      $returndata['id'] = $postID;
      $returndata['message'] = __('Menu created', 'uipress-pro');
      wp_send_json($returndata);
    } else {
      $message = __('Unable to create menu', 'uipress-pro');
      Ajax::error($message);
    }
  }
}
