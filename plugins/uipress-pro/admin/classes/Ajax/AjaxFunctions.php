<?php
namespace UipressPro\Classes\Ajax;
// Exit if accessed directly
!defined("ABSPATH") ? exit() : "";

class AjaxFunctions
{
  /**
   * Adds ajax functions for blocks and app
   *
   * @return void
   * @since 3.2.00
   */
  public static function start()
  {
    // Pro actions
    add_action('wp_ajax_uip_get_pro_app_data', ['UipressPro\Classes\uiBuilder\uipProApp', 'get_app_data']);
    add_action('wp_ajax_uip_save_uip_pro_data', ['UipressPro\Classes\uiBuilder\uipProApp', 'update_app_data']);
    add_action('wp_ajax_uip_remove_uip_pro_data', ['UipressPro\Classes\uiBuilder\uipProApp', 'remove_app_data']);

    // Google Analytics actions
    add_action('wp_ajax_uip_build_google_analytics_query', ['UipressPro\Classes\Blocks\GoogleAnalytics', 'build_query']);
    add_action('wp_ajax_uip_save_google_analytics', ['UipressPro\Classes\Blocks\GoogleAnalytics', 'save_account']);
    add_action('wp_ajax_uip_save_access_token', ['UipressPro\Classes\Blocks\GoogleAnalytics', 'save_access_token']);
    add_action('wp_ajax_uip_remove_analytics_account', ['UipressPro\Classes\Blocks\GoogleAnalytics', 'remove_account']);

    // Matomo analytics actions
    add_action('wp_ajax_uip_build_matomo_analytics_query', ['UipressPro\Classes\Blocks\MatomoAnalytics', 'build_query']);
    add_action('wp_ajax_uip_save_matomo_analytics', ['UipressPro\Classes\Blocks\MatomoAnalytics', 'save_account']);
    add_action('wp_ajax_uip_save_matomo_access_token', ['UipressPro\Classes\Blocks\MatomoAnalytics', 'save_access_token']);
    add_action('wp_ajax_uip_remove_matomo_analytics_account', ['UipressPro\Classes\Blocks\MatomoAnalytics', 'remove_account']);

    // Fathom analytics actions
    add_action('wp_ajax_uip_build_fathom_analytics_query', ['UipressPro\Classes\Blocks\FathomAnalytics', 'build_query']);
    add_action('wp_ajax_uip_save_fathom_analytics', ['UipressPro\Classes\Blocks\FathomAnalytics', 'save_account']);
    add_action('wp_ajax_uip_save_fathom_access_token', ['UipressPro\Classes\Blocks\FathomAnalytics', 'save_access_token']);
    add_action('wp_ajax_uip_remove_fathom_analytics_account', ['UipressPro\Classes\Blocks\FathomAnalytics', 'remove_account']);

    // Plugin actions
    add_action('wp_ajax_uip_get_plugin_updates', ['UipressPro\Classes\Blocks\PluginActions', 'get_plugin_updates']);
    add_action('wp_ajax_uip_update_plugin', ['UipressPro\Classes\Blocks\PluginActions', 'update_plugin']);
    add_action('wp_ajax_uip_search_directory', ['UipressPro\Classes\Blocks\PluginActions', 'search_directory']);
    add_action('wp_ajax_uip_install_plugin', ['UipressPro\Classes\Blocks\PluginActions', 'install_plugin']);
    add_action('wp_ajax_uip_activate_plugin', ['UipressPro\Classes\Blocks\PluginActions', 'activate_plugin']);

    // Shortcode
    add_action('wp_ajax_uip_get_shortcode', ['UipressPro\Classes\Blocks\ShortCode', 'get_shortcode']);

    // Content navigator
    add_action('wp_ajax_uip_get_navigator_defaults', ['UipressPro\Classes\Blocks\ContentNavigator', 'get_navigator_defaults']);
    add_action('wp_ajax_uip_get_default_content', ['UipressPro\Classes\Blocks\ContentNavigator', 'get_default_content']);
    add_action('wp_ajax_uip_create_folder', ['UipressPro\Classes\Blocks\ContentNavigator', 'create_folder']);
    add_action('wp_ajax_uip_get_folder_content', ['UipressPro\Classes\Blocks\ContentNavigator', 'get_folder_content']);
    add_action('wp_ajax_uip_update_item_folder', ['UipressPro\Classes\Blocks\ContentNavigator', 'update_item_folder']);
    add_action('wp_ajax_uip_delete_folder', ['UipressPro\Classes\Blocks\ContentNavigator', 'delete_folder']);
    add_action('wp_ajax_uip_update_folder', ['UipressPro\Classes\Blocks\ContentNavigator', 'update_folder']);
    add_action('wp_ajax_uip_duplicate_post', ['UipressPro\Classes\Blocks\ContentNavigator', 'duplicate_post']);
    add_action('wp_ajax_uip_delete_post_from_folder', ['UipressPro\Classes\Blocks\ContentNavigator', 'delete_post_from_folder']);

    // Woocommerce Analytics actions
    add_action('wp_ajax_uip_build_woocommerce_analytics_query', ['UipressPro\Classes\Blocks\WooCommerceAnalytics', 'build_woocommerce_analytics_query']);
    add_action('wp_ajax_uip_run_woocommerce_analytics_query', ['UipressPro\Classes\Blocks\WooCommerceAnalytics', 'run_woocommerce_analytics_query']);

    // Recent woo orders
    add_action('wp_ajax_uip_get_recent_orders', ['UipressPro\Classes\Blocks\RecentOrders', 'get_recent_orders']);

    // WooCommerce Kanban
    add_action('wp_ajax_uip_get_orders_for_kanban', ['UipressPro\Classes\Blocks\OrdersKanban', 'get_orders_for_kanban']);
    add_action('wp_ajax_uip_get_orders_for_kanban_by_state', ['UipressPro\Classes\Blocks\OrdersKanban', 'get_orders_for_kanban_by_state']);
    add_action('wp_ajax_uip_update_order_status', ['UipressPro\Classes\Blocks\OrdersKanban', 'update_order_status']);

    // Custom database
    add_action('wp_ajax_uip_test_remote_database', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'test_remote_database']);

    // Custom menus
    add_action('wp_ajax_uip_get_custom_menu_list', ['UipressPro\Classes\PostTypes\AdminMenus', 'remote_list']);
  }
}
