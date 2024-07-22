<?php
namespace UipressPro\Classes\uiBuilder;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\App\UserPreferences;
use UipressLite\Classes\App\UipOptions;
// Exit if accessed directly
!defined('ABSPATH') ? exit() : '';

class uipProApp
{
  /**
   * Starts main hooks for uiBuilder
   *
   * @since 3.0.0
   */
  public static function start()
  {
    add_action('admin_head', ['UipressPro\Classes\uiBuilder\uipProApp', 'load_scripts']);
  }

  /**
   * Loads builder scripts
   *
   * @return
   */
  public static function load_scripts()
  {
    $path = plugins_url('uipress-pro/');
    $version = uip_pro_plugin_version;

    wp_print_inline_script_tag("const uipProPath = '{$path}'", ['id' => 'uip-pro-path']);

    wp_print_script_tag([
      'id' => 'uip-pro-app-js',
      'src' => "{$path}/app/dist/proapp.build.js?ver={$version}",
      'type' => 'module',
    ]);
  }

  /**
   * Fetches pro app data
   *
   * @since 3.0.0
   */
  public static function get_app_data()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $data = UipOptions::get('uip_pro');

    $returndata = [];
    $returndata['uip_pro'] = $data;

    wp_send_json($returndata);
  }

  /**
   * Updates pro app data
   *
   * @since 3.0.0
   */
  public static function update_app_data()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $key = sanitize_text_field($_POST['key']);
    $instance = sanitize_text_field($_POST['instance']);
    //Get current data
    $data = UipOptions::get('uip_pro');

    if (!is_array($data)) {
      $data = [];
    }

    $data['key'] = $key;
    $data['instance'] = $instance;

    UipOptions::update('uip_pro', $data);

    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }

  /**
   * Removes pro app data
   *
   * @since 3.0.0
   */
  public static function remove_app_data()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    UipOptions::update('uip_pro', false);
    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }
}
