<?php
namespace UipressPro\Classes\PostTypes;

!defined("ABSPATH") ? exit() : "";

class Folders
{
  /**
   * Adds hooks for folders post type
   *
   * @since 3.0.0
   */
  public static function start()
  {
    add_action('init', ['UipressPro\Classes\PostTypes\Folders', 'create_cpt']);
  }

  /**
   * Creates folders custom post type
   *
   * @since 3.2.0
   */
  public static function create_cpt()
  {
    $labels = [
      'name' => _x('Content Folder', 'post type general name', 'uipress-pro'),
      'singular_name' => _x('Content Folder', 'post type singular name', 'uipress-pro'),
      'menu_name' => _x('Content Folders', 'admin menu', 'uipress-pro'),
      'name_admin_bar' => _x('Content Folder', 'add new on admin bar', 'uipress-pro'),
      'add_new' => _x('Add New', 'Template', 'uipress-pro'),
      'add_new_item' => __('Add New Content Folder', 'uipress-pro'),
      'new_item' => __('New Content Folder', 'uipress-pro'),
      'edit_item' => __('Edit Content Folders', 'uipress-pro'),
      'view_item' => __('View Content Folders', 'uipress-pro'),
      'all_items' => __('All Content Folders', 'uipress-pro'),
      'search_items' => __('Search Content Folders', 'uipress-pro'),
      'not_found' => __('No Content Folders found.', 'uipress-pro'),
      'not_found_in_trash' => __('No Content Folders found in Trash.', 'uipress-pro'),
    ];
    $args = [
      'labels' => $labels,
      'description' => __('Post type used for the uipress uipress folders', 'uipress-pro'),
      'public' => false,
      'publicly_queryable' => false,
      'show_ui' => false,
      'show_in_menu' => false,
      'query_var' => false,
      'has_archive' => false,
      'hierarchical' => false,
      'supports' => ['title'],
      'show_in_rest' => true,
    ];
    register_post_type('uip-ui-folder', $args);
  }
}
