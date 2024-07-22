<?php
namespace UipressPro\Classes\Blocks;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\Utils\Sanitize;

!defined('ABSPATH') ? exit() : '';

class PluginActions
{
  /**
   * Gets plugin updates
   *
   * @since 3.0.0
   */
  public static function get_plugin_updates()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $updates = get_plugin_updates();

    $returndata = [];
    $returndata['updates'] = $updates;
    $returndata['success'] = true;
    wp_send_json($returndata);
  }

  /**
   * Updates plugins from the plugin update block
   * @since 3.0.0
   */
  public static function update_plugin()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $slug = sanitize_text_field($_POST['slug']);

    if (!current_user_can('update_plugins')) {
      $message = __("You don't have necessary permissions to update plugins", 'uipress-pro');
      $returndata['error'] = true;
      $returndata['message'] = $message;
      wp_send_json($returndata);
    }

    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    ob_start();
    $upgrader = new Plugin_Upgrader();
    $upgraded = $upgrader->upgrade($slug);
    $list = ob_get_contents();
    ob_end_clean();

    if (!$upgraded) {
      $message = __('Unable to upgrade this plugin', 'uipress-pro');
      $returndata['error'] = true;
      $returndata['message'] = $message;
      wp_send_json($returndata);
    }
    $returndata['message'] = __('Plugin updated', 'uipress-pro');
    wp_send_json($returndata);
  }

  /**
   * Searches plugins from the wp directory
   *
   * @since 3.0.0
   */
  public static function search_directory()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $search = sanitize_text_field($_POST['search']);
    $page = sanitize_text_field($_POST['page']);

    include_once ABSPATH . 'wp-admin/includes/plugin-install.php';

    if (!function_exists('get_plugins')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }

    $plugins = plugins_api('query_plugins', [
      'per_page' => 10,
      'search' => $search,
      'page' => $page,
      'fields' => [
        'short_description' => true,
        'description' => true,
        'sections' => false,
        'tested' => true,
        'requires' => true,
        'requires_php' => true,
        'rating' => true,
        'ratings' => false,
        'downloaded' => true,
        'downloadlink' => true,
        'last_updated' => true,
        'added' => false,
        'tags' => false,
        'slug' => true,
        'compatibility' => false,
        'homepage' => true,
        'versions' => false,
        'donate_link' => false,
        'reviews' => false,
        'banners' => true,
        'icons' => true,
        'active_installs' => true,
        'group' => false,
        'contributors' => false,
        'screenshots' => true,
      ],
    ]);

    $returndata['message'] = __('Plugins found', 'uipress-pro');
    $returndata['plugins'] = Sanitize::clean_input_with_code($plugins->plugins);
    $returndata['totalFound'] = Sanitize::clean_input_with_code($plugins->info['results']);
    $returndata['totalPages'] = Sanitize::clean_input_with_code($plugins->info['pages']);
    wp_send_json($returndata);
  }

  /**
   * Updates plugins from the plugin update block
   *
   * @since 3.0.0
   */
  public static function install_plugin()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $downloadLink = sanitize_text_field($_POST['downloadLink']);

    if (!current_user_can('install_plugins')) {
      $message = __("You don't have necessary permissions to install plugins", 'uipress-pro');
      $returndata['error'] = true;
      $returndata['message'] = $message;
      wp_send_json($returndata);
    }

    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    $skin = new WP_Ajax_Upgrader_Skin();
    $upgrader = new Plugin_Upgrader($skin);
    $status = $upgrader->install($downloadLink);

    if (!$status) {
      $message = __('Unable to install this plugin', 'uipress-pro');
      $returndata['error'] = true;
      $returndata['message'] = $message;
      wp_send_json($returndata);
    }
    $returndata['message'] = __('Plugin installed', 'uipress-pro');
    wp_send_json($returndata);
  }

  /**
   * Activates plugins from the plugin update block
   *
   * @since 3.0.0
   */
  public static function activate_plugin()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $slug = sanitize_text_field($_POST['slug']);

    if (!current_user_can('activate_plugins')) {
      $message = __("You don't have necessary permissions to activate plugins", 'uipress-pro');
      $returndata['error'] = true;
      $returndata['message'] = $message;
      wp_send_json($returndata);
    }

    if (!function_exists('get_plugins')) {
      require_once ABSPATH . 'wp-admin/includes/plugin.php';
    }
    $all_plugins = get_plugins();
    foreach ($all_plugins as $key => $value) {
      if (strpos($key, $slug) !== false) {
        $slug = $key;
        break;
      } else {
        continue;
      }
    }

    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    ob_start();
    $status = activate_plugins($slug);
    ob_get_clean();

    if (!$status) {
      $message = __('Unable to activate this plugin', 'uipress-pro');
      $returndata['error'] = true;
      $returndata['message'] = $message;
      wp_send_json($returndata);
    }
    $returndata['message'] = __('Plugin activated', 'uipress-pro');
    wp_send_json($returndata);
  }
}
