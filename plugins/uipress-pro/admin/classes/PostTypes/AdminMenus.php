<?php
namespace UipressPro\Classes\PostTypes;
use UipressLite\Classes\Utils\Ajax;

!defined('ABSPATH') ? exit() : '';

class AdminMenus
{
  /**
   * Adds hooks for admin menus post type
   *
   * @since 3.0.0
   */
  public static function start()
  {
    add_action('init', ['UipressPro\Classes\PostTypes\AdminMenus', 'create_cpt']);
  }

  /**
   * Creates admin menus custom post type
   *
   * @since 3.2.0
   */
  public static function create_cpt()
  {
    $labels = [
      'name' => _x('Admin Menu', 'post type general name', 'uipress-pro'),
      'singular_name' => _x('admin menu', 'post type singular name', 'uipress-pro'),
      'menu_name' => _x('Admin Menus', 'admin menu', 'uipress-pro'),
      'name_admin_bar' => _x('Admin Menu', 'add new on admin bar', 'uipress-pro'),
      'add_new' => _x('Add New', 'Admin Menu', 'uipress-pro'),
      'add_new_item' => __('Add New Admin Menu', 'uipress-pro'),
      'new_item' => __('New Admin Menu', 'uipress-pro'),
      'edit_item' => __('Edit Admin Menu', 'uipress-pro'),
      'view_item' => __('View Admin Menu', 'uipress-pro'),
      'all_items' => __('All Admin Menus', 'uipress-pro'),
      'search_items' => __('Search Admin Menus', 'uipress-pro'),
      'not_found' => __('No Admin Menus found.', 'uipress-pro'),
      'not_found_in_trash' => __('No Admin Menus found in Trash.', 'uipress-pro'),
    ];
    $args = [
      'labels' => $labels,
      'description' => __('Description.', 'Add New Admin Menu'),
      'public' => false,
      'publicly_queryable' => false,
      'show_ui' => false,
      'show_in_menu' => false,
      'query_var' => false,
      'has_archive' => false,
      'hierarchical' => false,
      'supports' => ['title'],
    ];
    register_post_type('uip-admin-menu', $args);
  }

  /**
   * Lists all admin menus for ajax call
   *
   * @since 3.2.0
   */
  public static function remote_list()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $args = [
      'post_type' => 'uip-admin-menu',
      'post_status' => ['publish', 'draft'],
      'posts_per_page' => -1,
    ];

    $query = new \WP_Query($args);
    $menus = [];
    foreach ($query->get_posts() as $menu) {
      $temp['id'] = $menu->ID;
      $temp['label'] = get_the_title($menu->ID);
      $menus[] = $temp;
    }

    $returnData['menus'] = $menus;
    $returnData['message'] = __('Menus fetched', 'uipress-lite');
    wp_send_json($returnData);
  }
}
