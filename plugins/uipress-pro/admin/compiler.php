<?php
use UipressPro\Classes\Ajax\AjaxFunctions;
use UipressPro\Classes\uipApp\PluginUpdate;
use UipressPro\Classes\uipApp\SiteSettings;
use UipressPro\Classes\uiBuilder\uipProApp;

// Exit if accessed directly
!defined('ABSPATH') ? exit() : '';

class uipress_pro_compiler
{
  /**
   * Loads UiPress pro Classes and plugins
   *
   * @since 3.0.0
   */
  public function run()
  {
    //Uipress lite is not installed
    if (!class_exists('uip_site_settings')) {
      add_action('admin_head', [$this, 'flag_uipress_lite_error']);
      return;
    }

    require uip_pro_plugin_path . 'admin/vendor/autoload.php';
    add_action('uipress/app/start', ['UipressPro\Classes\uiBuilder\uipProApp', 'start']);

    AjaxFunctions::start();

    // Mount plugin updater
    PluginUpdate::mount();

    // Mount site settings
    SiteSettings::start();
  }

  /**
   * Outputs error if no uipress
   * @since 1.0
   */
  public function flag_uipress_lite_error()
  {
    $class = 'notice notice-error';
    $message = __('uiPress Pro requires UiPress lite to be installed before activating. uiPress lite can be downloaded from the WordPress repository', 'uipress-pro');
    $link = __('here', 'uipress-pro');

    printf('<div class="%1$s"><p>%2$s <a href="https://wordpress.org/plugins/uipress-lite/" target="_BLANK">%3$s</a></p></div>', esc_attr($class), esc_html($message), esc_html($link));
  }
}
