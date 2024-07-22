<?php
namespace UipressPro\Classes\uipApp;

!defined('ABSPATH') ? exit() : '';

class PluginUpdate
{
  private static $version = uip_pro_plugin_version;
  private static $transient = 'uip-pro-update-transient';
  private static $transientFailed = 'uip-pro-failed-transient';
  private static $updateURL = 'https://api.uipress.co/latest/v3/';
  private static $expiry = 12 * HOUR_IN_SECONDS;

  /**
   * Adds actions and filters to update hooks
   *
   * @since 2.2.0
   */
  public static function mount()
  {
    add_filter('plugins_api', ['UipressPro\Classes\uipApp\PluginUpdate', 'plugin_info'], 20, 3);
    add_filter('site_transient_update_plugins', ['UipressPro\Classes\uipApp\PluginUpdate', 'push_update']);
    add_action('upgrader_process_complete', ['UipressPro\Classes\uipApp\PluginUpdate', 'after_update'], 10, 2);
  }

  /**
   * Fetches plugin update info
   *
   * @since 2.2.0
   */
  public static function plugin_info($res, $action, $args)
  {
    // do nothing if this is not about getting plugin information
    if ('plugin_information' !== $action) {
      return $res;
    }

    if (true == get_transient(self::$transientFailed)) {
      return $res;
    }

    $plugin_slug = 'uipress-pro'; // we are going to use it in many places in this function

    // do nothing if it is not our plugin
    if ($plugin_slug !== $args->slug) {
      return $res;
    }

    // trying to get from cache first
    if (false == ($remote = get_transient(self::$transient))) {
      $remote = wp_remote_get(self::$updateURL, [
        'timeout' => 10,
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);

      if (self::is_response_clean($remote)) {
        set_transient(self::$transient, $remote, self::$expiry); // 12 hours cache
        $latest = $remote;
      } else {
        set_transient(self::$transientFailed, true, self::$expiry); // 12 hours cache
        return $res;
      }
    } else {
      $remote = get_transient(self::$transient);
      if (self::is_response_clean($remote)) {
        $latest = $remote;
      } else {
        set_transient(self::$transientFailed, true, self::$expiry);
        return $res;
      }
    }

    $remote = json_decode($latest['body']);

    $res = new \stdClass();

    $res->name = $remote->name;
    $res->slug = $plugin_slug;
    $res->version = $remote->version;
    $res->tested = $remote->tested;
    $res->requires = $remote->requires;
    $res->download_link = $remote->download_url;
    $res->trunk = $remote->download_url;
    $res->trunk = $remote->download_url;
    $res->requires_php = '5.3';
    $res->last_updated = $remote->last_updated;
    $res->sections = [
      'description' => $remote->sections->description,
      'installation' => $remote->sections->installation,
      'changelog' => $remote->sections->changelog,
      // you can add your custom sections (tabs) here
    ];

    if (!empty($remote->sections->screenshots)) {
      $res->sections['screenshots'] = $remote->sections->screenshots;
    }

    $res->banners = [
      'low' => $remote->banners->low,
      'high' => $remote->banners->high,
    ];

    return $res;
  }

  /**
   * Ensures response is clean
   *
   * @param object $status
   *
   * @since 3.2.0
   */
  private static function is_response_clean($status)
  {
    if (isset($status->errors)) {
      return false;
    }

    if (isset($status['response']['code']) && $status['response']['code'] != 200) {
      return false;
    }

    if (is_wp_error($status)) {
      return false;
    }

    return true;
  }

  /**
   * Pushes plugin update to plugin table
   * @since 1.4
   */

  public static function push_update($transient)
  {
    if (empty($transient->checked)) {
      return $transient;
    }

    if (true == get_transient(self::$transientFailed)) {
      return $transient;
    }

    // trying to get from cache first, to disable cache comment 10,20,21,22,24
    if (false == ($remote = get_transient(self::$transient))) {
      // info.json is the file with the actual plugin information on your server
      $remote = wp_remote_get(self::$updateURL, [
        'timeout' => 10,
        'headers' => [
          'Accept' => 'application/json',
        ],
      ]);

      if (self::is_response_clean($remote)) {
        set_transient(self::$transient, $remote, self::$expiry); // 12 hours cache
      } else {
        set_transient(self::$transientFailed, true, self::$expiry); // 12 hours cache
        return $transient;
      }
    }

    if ($remote && !is_wp_error($remote)) {
      $remote = json_decode($remote['body']);

      // your installed plugin version should be on the line below! You can obtain it dynamically of course
      if ($remote && version_compare(self::$version, $remote->version, '<')) {
        $res = new \stdClass();
        $res->slug = 'uipress-pro';
        $res->plugin = 'uipress-pro/uipress-pro.php'; // it could be just YOUR_PLUGIN_SLUG.php if your plugin doesn't have its own directory
        $res->new_version = $remote->version;
        $res->tested = $remote->tested;
        $res->package = $remote->download_url;
        $transient->response[$res->plugin] = $res;
      }
    }

    return $transient;
  }

  /**
   * Cleans cache after update
   * @since 1.4
   */

  public static function after_update($upgrader_object, $options)
  {
    if ($options['action'] == 'update' && $options['type'] === 'plugin') {
      // just clean the cache when new plugin version is installed
      if (isset(self::$upgrade_transient)) {
        delete_transient(self::$upgrade_transient);
      }
    }
  }
}
