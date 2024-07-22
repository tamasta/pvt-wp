<?php
namespace UipressPro\Classes\PostTypes;

!defined("ABSPATH") ? exit() : "";

class uipHistory
{
  /**
   * Adds hooks for admin menus post type
   *
   * @since 3.0.0
   */
  public static function start()
  {
    add_action('init', ['UipressPro\Classes\PostTypes\uipHistory', 'create_cpt']);
  }

  /**
   * Creates admin menus custom post type
   *
   * @since 3.2.0
   */
  public static function create_cpt()
  {
    $labels = [
      'name' => _x('History', 'post type general name', 'uipress'),
      'singular_name' => _x('history', 'post type singular name', 'uipress'),
      'menu_name' => _x('History', 'admin menu', 'uipress'),
      'name_admin_bar' => _x('History', 'add new on admin bar', 'uipress'),
      'add_new' => _x('Add New', 'history', 'uipress'),
      'add_new_item' => __('Add New History', 'uipress'),
      'new_item' => __('New History', 'uipress'),
      'edit_item' => __('Edit History', 'uipress'),
      'view_item' => __('View History', 'uipress'),
      'all_items' => __('All History', 'uipress'),
      'search_items' => __('Search History', 'uipress'),
      'not_found' => __('No History found.', 'uipress'),
      'not_found_in_trash' => __('No History found in Trash.', 'uipress'),
    ];
    $args = [
      'labels' => $labels,
      'description' => __('Description.', 'Add New History'),
      'public' => false,
      'publicly_queryable' => false,
      'show_ui' => true,
      'show_in_menu' => false,
      'query_var' => false,
      'has_archive' => false,
      'hierarchical' => false,
      'supports' => ['title', 'author'],
    ];
    register_post_type('uip-history', $args);
  }
}
