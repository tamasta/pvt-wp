<?php

namespace UipressPro\Classes\Extensions\Folders;
use UipressPro\Classes\PostTypes\Folders;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\Utils\Sanitize;
use UipressLite\Classes\App\UserPreferences;
use UipressLite\Classes\App\UipOptions;
use UipressLite\Classes\Utils\Objects;

!defined('ABSPATH') ? exit() : '';

class FoldersApp
{
  private static $limitToAuthor = false;
  private static $limitToType = false;
  private static $enabledFor = false;
  private static $settingsObject = false;

  /**
   * Starts the folder features and adds actions into relevant hooks / filters
   *
   * @since 3.0.9
   */
  public static function start()
  {
    self::formatOptions();
    self::add_media_folders();

    // Add folders to post types
    add_action('current_screen', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'start_post_folders'], 10);

    // Ajax
    add_action('wp_ajax_uip_folders_get_base_folders', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'get_base_folders']);
    add_action('wp_ajax_uip_folders_create_folder', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'create_folder']);
    add_action('wp_ajax_uip_folders_get_folder_content', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'get_folder_content']);
    add_action('wp_ajax_uip_folders_add_item_to_folder', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'add_item_to_folder']);
    add_action('wp_ajax_uip_folders_remove_item_from_folder', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'remove_item_from_folder']);
    add_action('wp_ajax_uip_folders_update_item_folder', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'update_folder_parent']);
    add_action('wp_ajax_uip_folders_delete_folder', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'delete_folder']);
    add_action('wp_ajax_uip_folders_update_folder_details', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'update_folder_details']);

    // Media modals prepare attachments
    add_filter('ajax_query_attachments_args', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'legacy_media_filter']);
    add_filter('wp_prepare_attachment_for_js', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'pull_meta_to_attachments'], 10, 3);
  }

  /**
   * Formats folder app options to class
   *
   * @return void
   * @since 3.2.0
   */
  private static function formatOptions()
  {
    // Set main settings object
    self::$settingsObject = json_decode(uip_site_settings);

    $limitToAuthor = Objects::get_nested_property(self::$settingsObject, ['contentFolders', 'limitToAuthor']);
    $foldersPerType = Objects::get_nested_property(self::$settingsObject, ['contentFolders', 'perType']);
    $enabledFor = Objects::get_nested_property(self::$settingsObject, ['contentFolders', 'enabledForTypes']);

    $enabledFor = is_array($enabledFor) ? $enabledFor : [];

    self::$limitToAuthor = $limitToAuthor;
    self::$limitToType = $foldersPerType;
    self::$enabledFor = $enabledFor;
  }

  /**
   * Checks if folders should be running on setup and adss actions if so
   *
   * @return void
   * @since 3.2.0
   */
  private static function add_media_folders()
  {
    // Exit early if user does not have folders enabled for folders
    if (!in_array('attachment', self::$enabledFor)) {
      return;
    }

    add_action('wp_enqueue_media', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'enqueue_media_functions']);
    add_action('add_attachment', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'add_to_current_folder']);
  }

  /**
   * Adds actions hooked to the wp_enqueue_media action
   *
   * @return void
   * @since 3.2.0
   */
  public static function enqueue_media_functions()
  {
    // Media template
    add_action('admin_footer', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'build_media_template'], 10);
    add_action('wp_footer', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'build_media_template'], 10);
  }

  /**
   * Adds folders to posts and pages
   * @since 3.1.1
   */

  public static function start_post_folders()
  {
    // exit early if not logged in or post types are not defined
    if (!is_array(self::$enabledFor) || !is_user_logged_in()) {
      return;
    }

    // Add hook to pre get posts to filter by folder
    add_action('pre_get_posts', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'filter_posts_by_folder']);

    // Check we are on an edit screen
    $screen = get_current_screen();
    if ($screen->base != 'edit' && $screen->base != 'upload') {
      return;
    }

    // Checks if we are on media grid page, if so then abort
    if (self::is_page_media_grid($screen)) {
      return;
    }

    self::add_post_page_actions($screen);
  }

  /**
   * Adds required hooks depending on page
   *
   * @param object $screen
   *
   * @since 3.2.0
   */
  private static function add_post_page_actions($screen)
  {
    $currentPostType = property_exists($screen, 'post_type') ? $screen->post_type : false;

    // Exit early if post type is not enabled by user
    if (!in_array($currentPostType, self::$enabledFor)) {
      return;
    }

    add_action('all_admin_notices', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'build_post_folders']);
    add_filter('manage_' . $currentPostType . '_posts_columns', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'add_drag_column']);
    add_action('manage_posts_custom_column', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'add_drag_icon_to_column'], 10, 2);

    if ($currentPostType == 'page') {
      add_action('manage_pages_custom_column', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'add_drag_icon_to_column'], 10, 2);
    }

    if ($currentPostType == 'attachment') {
      add_filter('manage_media_columns', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'add_drag_column']);
      add_action('manage_media_custom_column', ['UipressPro\Classes\Extensions\Folders\FoldersApp', 'add_drag_icon_to_column'], 10, 2);
    }
  }

  /**
   * Checks if we are on media page and it's in grid mode
   *
   * @param object $screen
   *
   * @return boolean  - whether the page is media grid
   * @since 3.2.0
   */
  private static function is_page_media_grid($screen)
  {
    // Only load folders for media this way if the mode is list
    if ($screen->post_type != 'attachment') {
      return false;
    }

    $mode = isset($_GET['mode']) ? sanitize_text_field($_GET['mode']) : get_user_option('media_library_mode', get_current_user_id());
    return $mode == 'grid' ? true : false;
  }

  /**
   * Filters posts by folder id from pre_get_posts hook
   *
   * @since 2.2
   */
  public static function filter_posts_by_folder($query)
  {
    $folder_id = isset($_GET['uip_folder']) ? sanitize_text_field($_GET['uip_folder']) : false;

    // Exit early if not set
    if ($folder_id == '' || $folder_id == 'all') {
      return;
    }

    // Get original meta query
    $meta_query = $query->get('meta_query');
    $meta_query = is_array($meta_query) ? $meta_query : [];

    // Add our meta query to the original meta queries
    $meta_query[] = [
      'key' => 'uip-folder-parent',
      'value' => serialize(strval($folder_id)),
      'compare' => 'LIKE',
    ];

    $query->set('meta_query', $meta_query);
  }

  /**
   * Adds draggable column to posts for folders
   *
   * @since 2.2
   */
  public static function add_drag_column($columns)
  {
    $newcolumns['uip_draggable'] = '';
    $result = array_merge($newcolumns, $columns);
    return $result;
  }

  /**
   * Adds draggable icon to posts
   *
   * @since 2.2
   */
  public static function add_drag_icon_to_column($column_id, $post_id)
  {
    switch ($column_id) {
      case 'uip_draggable':
        $data = "
		<div class='uip-flex uip-padding-xxs uip-border-round hover:uip-background-grey uip-cursor-drag uip-border-round uip-ratio-1-1 uip-post-drag' data-id='{$post_id}' draggable='true'>
        	<span class='uip-inline-drag-icon uip-text-xl'></span>
        </div>";
        echo $data;
        break;
    }
  }

  /**
   * Adds folder id to default wp media views
   * @since 1.4
   */
  public static function pull_meta_to_attachments($response, $attachment, $meta)
  {
    $response['imageID'] = $attachment->ID;
    $response['properties']['imageID'] = $attachment->ID;

    if (isset($_REQUEST['query']['uip_folder_id'])) {
      $folderID = sanitize_text_field($_REQUEST['query']['uip_folder_id']);
      $response['current_folder'] = $folderID;
      $response['properties']['current_folder'] = $folderID;
    }

    return $response;
  }

  /**
   * Adds item to current folder
   *
   * @param number $attachment_id
   *
   * @since 3.2.0
   */
  public static function add_to_current_folder($attachment_id)
  {
    if (isset($_REQUEST['uip_folder_id'])) {
      $folderID = sanitize_text_field($_REQUEST['uip_folder_id']);
      if (is_numeric($folderID) && $folderID != 'all') {
        update_post_meta($attachment_id, 'uip-folder-parent', [$folderID]);
      }
    }
  }

  /**
   * Filters media by folder
   * @since 1.4
   */
  public static function legacy_media_filter($args)
  {
    if (isset($_REQUEST['query']['uip_folder_id'])) {
      $folderID = sanitize_text_field($_REQUEST['query']['uip_folder_id']);

      if ($folderID != '' || $folderID != 'all') {
        $args['meta_query'] = [
          [
            'key' => 'uip-folder-parent',
            'value' => serialize(strval($folderID)),
            'compare' => 'LIKE',
          ],
        ];
      }
      if ($folderID == 'all') {
        $key = array_search('uip-folder-parent', array_column($args['meta_query'], 'key'));
        if ($key != '') {
          unset($args['meta_query'][$key]);
        }
      }
    }

    return $args;
  }

  /**
   * Updates folder
   * @since 3.0.93
   */
  public static function update_folder_details()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $folderID = sanitize_text_field($_POST['folderId']);
    $title = sanitize_text_field($_POST['title']);
    $color = sanitize_text_field($_POST['color']);

    //No folder id
    if (!$folderID || $folderID == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('No folder to update', 'uipress-pro');
      wp_send_json($returndata);
    }
    //Folder does not exist
    if (!get_post_status($folderID)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Folder does not exist', 'uipress-pro');
      wp_send_json($returndata);
    }
    //Incorrect caps
    if (!current_user_can('edit_post', $folderID)) {
      $returndata['error'] = true;
      $returndata['message'] = __('You do not have the correct capabilities to update this folder', 'uipress-pro');
      wp_send_json($returndata);
    }

    //Tittle is blank
    if (!$title || $title == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('Folder title is required', 'uipress-pro');
      wp_send_json($returndata);
    }

    $my_post = [
      'ID' => $folderID,
      'post_title' => wp_strip_all_tags($title),
    ];

    //Update the post into the database
    $status = wp_update_post($my_post);

    //Something went wrong
    if (!$status) {
      $returndata['error'] = true;
      $returndata['message'] = __('Unable to update the folder right now', 'uipress-pro');
      wp_send_json($returndata);
    }

    if ($color && $color != '') {
      update_post_meta($folderID, 'uip-folder-color', $color);
    }

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;

    wp_send_json($returndata);
  }

  /**
   * Deletes folder
   * @since 3.0.93
   */
  public static function delete_folder()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $folderID = sanitize_text_field($_POST['folderId']);
    $postType = sanitize_text_field($_POST['postType']);

    //No folder id
    if (!$folderID || $folderID == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('No folder to delete', 'uipress-pro');
      wp_send_json($returndata);
    }
    //Folder does not exist
    if (!get_post_status($folderID)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Folder does not exist', 'uipress-pro');
      wp_send_json($returndata);
    }
    //Incorrect caps
    if (!current_user_can('delete_post', $folderID)) {
      $returndata['error'] = true;
      $returndata['message'] = __('You do not have the correct capabilities to delete this folder', 'uipress-pro');
      wp_send_json($returndata);
    }

    $status = wp_delete_post($folderID, true);

    //Something went wrong
    if (!$status) {
      $returndata['error'] = true;
      $returndata['message'] = __('Unable to delete the folder right now', 'uipress-pro');
      wp_send_json($returndata);
    }

    self::removeFromFolder($folderID, [$postType]);

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;

    wp_send_json($returndata);
  }

  /**
   * Creates new folder
   * @since 3.0.93
   */
  public static function create_folder()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $folderParent = sanitize_text_field($_POST['folderParent']);
    $folderName = sanitize_text_field($_POST['folderName']);
    $folderColor = sanitize_text_field($_POST['folderColor']);
    $postType = sanitize_text_field($_POST['postType']);

    if (!$folderParent || $folderParent == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('Unable to create content folder right now', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (!$folderName || $folderName == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('Folder name is required', 'uipress-pro');
      wp_send_json($returndata);
    }

    $updateArgs = [
      'post_title' => wp_strip_all_tags($folderName),
      'post_status' => 'publish',
      'post_type' => 'uip-ui-folder',
    ];

    $updatedID = wp_insert_post($updateArgs);

    if (!$updatedID || $updatedID == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('Unable to create content folder right now', 'uipress-pro');
      wp_send_json($returndata);
    }

    if ($folderParent != 'uipfalse') {
      $folderParent = [$folderParent];
    }

    update_post_meta($updatedID, 'uip-folder-parent', $folderParent);
    update_post_meta($updatedID, 'uip-folder-color', $folderColor);
    update_post_meta($updatedID, 'uip-folder-for', $postType);

    $temp = [];
    $temp['id'] = $updatedID;
    $temp['title'] = $folderName;
    $temp['parent'] = $folderParent;
    $temp['count'] = 0;
    $temp['color'] = $folderColor;
    $temp['content'] = [];
    $temp['canDelete'] = true;
    $temp['type'] = 'uip-ui-folder';

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['folder'] = $temp;

    wp_send_json($returndata);
  }

  /**
   * Updates item folder after drag and drop
   * @since 3.0.93
   */
  public static function update_folder_parent()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $item = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['item'])));
    $newParent = sanitize_text_field($_POST['newParent']);

    if (!$item || empty($item)) {
      $returndata['error'] = true;
      $returndata['message'] = __('No item to update', 'uipress-pro');
      wp_send_json($returndata);
    }

    if ($item->type == 'uip-ui-folder') {
      if ($newParent != 'uipfalse') {
        $newParent = [$newParent];
      }
      update_post_meta($item->id, 'uip-folder-parent', $newParent);
    } else {
      $current = get_post_meta($item->id, 'uip-folder-parent', true);

      if (!$current || !is_array($current)) {
        $current = [];
      }

      // If old parent is in current parent, remove it
      if (in_array($item->parent, $current)) {
        $currentid = $item->parent;

        $new = [];
        foreach ($current as $fol) {
          if ($fol == $currentid) {
            $fol = $newParent;
          }
          $new[] = $fol;
        }
        $current = array_values(array_unique($new));
      } else {
        array_push($current, $newParent);
      }
      update_post_meta($item->id, 'uip-folder-parent', $current);
    }

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;

    wp_send_json($returndata);
  }

  /**
   * Removes item to folder
   *
   * @since 3.2.09
   */
  public static function remove_item_from_folder()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $ids = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['IDS'])));
    $currentFolder = sanitize_text_field($_POST['currentFolder']);

    if (!$ids || !is_array($ids)) {
      $returndata['error'] = true;
      $returndata['message'] = __('No items to update', 'uipress-pro');
      wp_send_json($returndata);
    }

    $returndata = [];

    //Loop through item ids
    foreach ($ids as $itemid) {
      $currentPostType = get_post_type($itemid);

      $current = get_post_meta($itemid, 'uip-folder-parent', true);

      if (!$current || !is_array($current)) {
        $current = [];
      }

      // Find folder id in list
      $key = array_search($currentFolder, $current);

      // Remove folder if found
      if ($key !== false) {
        unset($current[$key]);
      }

      update_post_meta($itemid, 'uip-folder-parent', $current);
    }

    $message = __('Item removed from folder', 'uipress-pro');
    if (count($ids) > 1) {
      $message = __('Items removed from folder', 'uipress-pro');
    }

    //Return data to app
    $returndata['success'] = true;
    $returndata['message'] = $message;

    wp_send_json($returndata);
  }

  /**
   * Adds item to folder
   *
   * @since 3.0.93
   */
  public static function add_item_to_folder()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $ids = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['IDS'])));
    $newParent = sanitize_text_field($_POST['newParent']);
    $parentFolder = sanitize_text_field($_POST['parentFolder']);
    $limitToType = sanitize_text_field($_POST['limitToType']);
    $postType = sanitize_text_field($_POST['postType']);

    if (!$ids || !is_array($ids)) {
      $returndata['error'] = true;
      $returndata['message'] = __('No items to update', 'uipress-pro');
      wp_send_json($returndata);
    }

    $returndata = [];

    //Loop through item ids
    foreach ($ids as $itemid) {
      $currentPostType = get_post_type($itemid);

      if ($itemid == $newParent) {
        continue;
      }

      //Update for folder
      if ($currentPostType == 'uip-ui-folder') {
        if ($newParent != 'uipfalse') {
          $newParent = [$newParent];
        }
        update_post_meta($itemid, 'uip-folder-parent', $newParent);
        $returndata['folder'] = self::format_folder_for_app($itemid, $limitToType, $newParent, [$postType]);
      }
      //Update for other post types
      else {
        $current = get_post_meta($itemid, 'uip-folder-parent', true);

        if (!$current || !is_array($current)) {
          $current = [];
        }

        $current[] = $newParent;
        $current = array_values(array_unique($current));

        //If moving out of folder
        if ($parentFolder && $parentFolder != 'all' && $parentFolder > 0) {
          $current = array_diff($current, [$parentFolder]);
        }

        update_post_meta($itemid, 'uip-folder-parent', $current);
      }
    }

    $message = __('Item moved to folder', 'uipress-pro');
    if (count($ids) > 1) {
      $message = __('Items moved to folder', 'uipress-pro');
    }

    //Return data to app
    $returndata['success'] = true;
    $returndata['message'] = $message;

    wp_send_json($returndata);
  }

  /**
   * Formats a folder for the frontend app
   * @since 3.0.9
   */
  public static function format_folder_for_app($id, $limitToType, $parent, $postTypes)
  {
    $link = get_permalink($id);
    $editLink = get_edit_post_link($id, '&');
    $type = get_post_type($id);
    $canDelete = current_user_can('delete_post', $id);

    $temp = [];
    $temp['id'] = $id;
    $temp['title'] = get_the_title($id);
    $temp['status'] = get_post_status($id);
    $temp['edit_href'] = $editLink;
    $temp['view_href'] = $link;
    $temp['type'] = $type;
    $temp['canDelete'] = $canDelete;
    $temp['parent'] = $parent;

    if ($type == 'uip-ui-folder') {
      $temp['count'] = $this->get_folder_content_count($id, $postTypes, $authorLimit, $limitToType);
      $temp['color'] = get_post_meta($id, 'uip-folder-color', true);
    }

    return $temp;
  }

  /**
   * Gets content for give folder
   * @since 3.0.9
   */
  public static function get_folder_content()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $postType = sanitize_text_field($_POST['postType']);
    $folderID = sanitize_text_field($_POST['id']);
    $authorLimit = sanitize_text_field($_POST['limitToAuthor']);
    $limitToType = sanitize_text_field($_POST['limitToType']);

    if (!$folderID || $folderID == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('No folder given to fetch content for', 'uipress-pro');
      wp_send_json($returndata);
    }

    //Get folder contents
    $args = [
      'post_type' => 'uip-ui-folder',
      'posts_per_page' => -1,
      'post_status' => ['publish', 'draft', 'inherit'],
      'orderby' => 'title',
      'order' => 'ASC',
      'meta_query' => [
        [
          'key' => 'uip-folder-parent',
          'value' => serialize(strval($folderID)),
          'compare' => 'LIKE',
        ],
      ],
    ];

    if ($limitToType == true && $limitToType != 'uipfalse') {
      $args['meta_query'][] = [
        'relation' => 'OR',
        [
          'key' => 'uip-folder-for',
          'value' => $postType,
          'compare' => '=',
        ],
        [
          'key' => 'uip-folder-for',
          'compare' => 'NOT EXISTS',
        ],
      ];
    }

    if ($authorLimit == 1) {
      $args['author'] = get_current_user_id();
    }

    $query = new \WP_Query($args);
    $totalFound = $query->found_posts;
    $foundPosts = $query->get_posts();

    $formatted = [];
    foreach ($foundPosts as $post) {
      $link = get_permalink($post->ID);
      $editLink = get_edit_post_link($post->ID, '&');
      $type = get_post_type($post->ID);
      $canDelete = current_user_can('delete_post', $post->ID);

      $temp = [];
      $temp['id'] = $post->ID;
      $temp['title'] = $post->post_title;
      $temp['status'] = $post->post_status;
      $temp['edit_href'] = $editLink;
      $temp['view_href'] = $link;
      $temp['type'] = $type;
      $temp['canDelete'] = $canDelete;
      $temp['parent'] = $folderID;

      if ($type == 'uip-ui-folder') {
        $temp['count'] = self::get_folder_content_count($post->ID, [$postType], $authorLimit, $limitToType);
        $temp['color'] = get_post_meta($post->ID, 'uip-folder-color', true);
      }

      $formatted[] = $temp;
    }

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['content'] = $formatted;
    $returndata['totalFound'] = $totalFound;
    $returndata['folderCount'] = self::get_folder_content_count($folderID, [$postType], $authorLimit, $limitToType);

    wp_send_json($returndata);
  }

  /**
   * Gets user base level folders
   *
   * @since 3.0.9
   */
  public static function get_base_folders()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $authorLimit = sanitize_text_field($_POST['limitToAuthor']);
    $limitToType = sanitize_text_field($_POST['limitToType']);
    $postType = sanitize_text_field($_POST['postType']);

    // Get base folders

    $args = [
      'post_type' => 'uip-ui-folder',
      'posts_per_page' => -1,
      'post_status' => 'publish',
      'orderby' => 'title',
      'order' => 'ASC',
      'meta_query' => [
        [
          'relation' => 'OR',
          [
            'key' => 'uip-folder-parent',
            'value' => 'uipfalse',
            'compare' => '=',
          ],
        ],
      ],
    ];

    if ($limitToType == true && $limitToType != 'uipfalse') {
      $metaQuery[] = [
        'relation' => 'OR',
        [
          'key' => 'uip-folder-for',
          'value' => $postType,
          'compare' => '=',
        ],
        [
          'key' => 'uip-folder-for',
          'compare' => 'NOT EXISTS',
        ],
      ];
      $args['meta_query'][] = $metaQuery;
    }

    if ($authorLimit == 1) {
      $args['author'] = get_current_user_id();
    }

    $query = new \WP_Query($args);
    $foundFolders = $query->get_posts();

    $formattedFolders = [];
    foreach ($foundFolders as $folder) {
      $canDelete = current_user_can('delete_post', $folder->ID);

      $temp = [];
      $temp['id'] = $folder->ID;
      $temp['title'] = $folder->post_title;
      $temp['parent'] = 'uipfalse';
      $temp['count'] = self::get_folder_content_count($folder->ID, [$postType], $authorLimit, $limitToType);
      $temp['color'] = get_post_meta($folder->ID, 'uip-folder-color', true);
      $temp['type'] = 'uip-ui-folder';
      $temp['content'] = [];
      $temp['canDelete'] = $canDelete;
      $formattedFolders[] = $temp;
    }

    //Get total
    $totals = (array) wp_count_posts($postType);
    $total = 0;
    foreach ($totals as $key => $value) {
      if ($key != 'trash' && $key != 'auto-draft' && $key) {
        $total = $total + $value;
      }
    }

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['baseFolders'] = $formattedFolders;
    $returndata['total'] = $total;

    wp_send_json($returndata);
  }

  /**
   * Counts folder content
   * @since 3.0.92
   */
  public static function get_folder_content_count($folderID, $postTypes, $authorLimit, $limitToType)
  {
    $ogTypes = $postTypes;
    if (!$postTypes || empty($postTypes)) {
      $args = ['public' => true];
      $output = 'names';
      $operator = 'and';
      $types = get_post_types($args, $output, $operator);
      $postTypes = [];
      foreach ($types as $type) {
        $postTypes[] = $type;
      }
    }

    if (!in_array('uip-ui-folder', $postTypes)) {
      $postTypes[] = 'uip-ui-folder';
    }
    //Get folder count
    $args = [
      'post_type' => $postTypes,
      'posts_per_page' => -1,
      'post_status' => ['publish', 'draft', 'inherit'],
      'fields' => 'ids',
      'meta_query' => [
        [
          'key' => 'uip-folder-parent',
          'value' => serialize(strval($folderID)),
          'compare' => 'LIKE',
        ],
      ],
    ];

    if ($limitToType == true && $limitToType != 'uipfalse') {
      $args['meta_query'][] = [
        'relation' => 'OR',
        [
          'key' => 'uip-folder-for',
          'value' => $ogTypes[0],
          'compare' => '=',
        ],
        [
          'key' => 'uip-folder-for',
          'compare' => 'NOT EXISTS',
        ],
      ];
    }

    if ($authorLimit == 1) {
      $args['author'] = get_current_user_id();
    }

    $query = new \WP_Query($args);
    $totalInFolder = $query->found_posts;
    if ($totalInFolder == null) {
      $totalInFolder = 0;
    }
    return $totalInFolder;
  }

  /**
   * Removes folder from items
   * @since 3.0.93
   */
  public static function removeFromFolder($folderID, $postTypes)
  {
    //Get all posts in this folder and remove the id
    if (!$postTypes || empty($postTypes)) {
      $args = ['public' => true];
      $output = 'names';
      $operator = 'and';
      $types = get_post_types($args, $output, $operator);
      $postTypes = [];
      foreach ($types as $type) {
        $postTypes[] = $type;
      }
    }

    if (!in_array('uip-ui-folder', $postTypes)) {
      $postTypes[] = 'uip-ui-folder';
    }
    //Get folder contents
    $args = [
      'post_type' => $postTypes,
      'posts_per_page' => -1,
      'post_status' => ['publish', 'draft', 'inherit'],
      'meta_query' => [
        [
          'key' => 'uip-folder-parent',
          'value' => serialize(strval($folderID)),
          'compare' => 'LIKE',
        ],
      ],
    ];

    $query = new \WP_Query($args);
    $foundPosts = $query->get_posts();

    foreach ($foundPosts as $post) {
      $currentFolders = get_post_meta($post->id, 'uip-folder-parent', true);
      $type = get_post_type($post->ID);

      if (!is_array($currentFolders)) {
        $currentFolders = [];
      }

      if ($type != 'uip-ui-folder') {
        if (in_array($folderID, $currentFolders)) {
          $new = [];
          foreach ($current as $fol) {
            if ($fol != $folderID) {
              $new[] = $fol;
            }
          }
          $current = array_values(array_unique($new));
          update_post_meta($post->id, 'uip-folder-parent', $current);
        }
      }

      //Recursively remove folders inside folders

      if ($type == 'uip-ui-folder') {
        if (current_user_can('delete_post', $post->ID)) {
          wp_delete_post($post->ID, true);
        }
        self::removeFromFolder($post->ID, $postTypes);
      }
    }
  }

  /**
   * Builds media template
   * @since 3.0.9
   */
  public static function build_media_template()
  {
    $styleSRC = uip_plugin_url . 'assets/css/uip-app.css';
    $styleSRCRTL = uip_plugin_url . 'assets/css/uip-app-rtl.css';

    //Get post type
    $postType = 'attachment';

    $styleSRC = is_rtl() ? $styleSRCRTL : $styleSRC;

    $url = uip_pro_plugin_url;
    $version = uip_pro_plugin_version;
    $folderScript = "{$url}/app/dist/folders.build.js?ver={$version}";

    $styles = "
      <style>
        @import '{$styleSRC}';
        .uploader-window{ display:none !important;}
        .media-frame.mode-grid .uip-folders-inner{background:var(--uip-color-base-0);border-radius:var(--uip-border-radius-large);margin-top:12px;}
      </style>";
    ?>
	
	
	<script type="text/html" id="tmpl-media-frame_custom">
			
        <?php echo Sanitize::clean_input_with_code($styles); ?>
		  
		  
		  <div class="uip-flex uip-flex-wrap uip-h-100p uip-text-normal uip-flex-no-wrap uip-flex-wrap-mobile uip-gap-s">
		  
		  	
			<div class="uip-w-100p-mobile uip-body-font uip-position-relative" id="uip-folder-app" style="font-size:14px;"></div>
		  
			<div class="uip-flex-grow uip-position-relative">
		  
			  <div class="media-frame-title" id="media-frame-title"></div>
			  <h2 class="media-frame-menu-heading"><?php _ex('Actions', 'media modal menu actions'); ?></h2>
			  <button type="button" class="button button-link media-frame-menu-toggle" aria-expanded="false">
				<?php _ex('Menu', 'media modal menu'); ?>
				<span class="dashicons dashicons-arrow-down" aria-hidden="true"></span>
			  </button>
			  <div class="media-frame-menu"></div>
		  
			  <div class="media-frame-tab-panel">
				<div class="media-frame-router"></div>
				<div class="media-frame-content"></div>
			  </div>
			</div>
		  
		  </div>
		  
		  <div class="media-frame-toolbar"></div>
		  <div class="media-frame-uploader"></div>
		  
		  <?php wp_print_script_tag([
      'id' => 'uip-folder-app-data',
      'src' => $folderScript,
      'type' => 'module',
      'uipress-lite' => plugins_url('uipress-lite/'),
      'defer' => true,
      'ajax_url' => admin_url('admin-ajax.php'),
      'security' => wp_create_nonce('uip-security-nonce'),
      'postType' => $postType,
      'limitToAuthor' => '' . self::$limitToAuthor . '',
      'limitToType' => '' . self::$limitToType . '',
    ]); ?>
		  
		</script>
		
		<script>
		  document.addEventListener('DOMContentLoaded', function () {
		
			if( typeof wp.media.view.Attachment != 'undefined' ){
			  wp.media.view.MediaFrame.prototype.template = wp.media.template( 'media-frame_custom' );
			  
			  wp.media.view.Attachment.Library = wp.media.view.Attachment.Library.extend({
				attributes:  function () { 
					return {
						draggable: "true", 
						'data-id':  this.model.get( 'imageID' ), 
						'data-folder-id':  this.model.get( 'current_folder' )
						}
					},
				});
		
		
			} 
		  });
      
      
          jQuery(document).ready(function($) {
              $.extend(wp.Uploader.prototype, { init: function () {
                
                    this.uploader.bind('BeforeUpload', (uploader, file) => {
                        const event = new CustomEvent('uipress/mediamodal/upload/before', {detail: {uploader: uploader,file: file}});
                        document.dispatchEvent(event);
                    });
                    
                    this.uploader.bind('FileUploaded', (uploader, file) => {
                       const event = new CustomEvent('uipress/mediamodal/upload/added', {detail: {uploader: uploader,file: file}});
                       document.dispatchEvent(event);
                    });
                  
                    this.uploader.bind('UploadComplete', (uploader, file) => {
                        const event = new CustomEvent('uipress/mediamodal/upload/finished', {detail: {uploader: uploader,file: file}});
                        document.dispatchEvent(event);
                    });
                }
                
              });
          });
		  
		</script>
		<?php
  }

  /**
   * Builds folder app for post types
   *
   * @since 3.0.9
   */
  public static function build_post_folders()
  {
    //Get post type
    $screen = get_current_screen();
    $postType = $screen->post_type;

    $styleSRC = uip_plugin_url . 'assets/css/uip-app.css';
    $styleSRCRTL = uip_plugin_url . 'assets/css/uip-app-rtl.css';

    $styleSRC = is_rtl() ? $styleSRCRTL : $styleSRC;

    $appContainer = '
	  <div class="uip-folder-wrap uip-position-absolute uip-h-100p uip-flex uip-flex-column uip-background-default uip-left--20 uip-transition-all uip-border-right uip-shadow"
      style="min-height:100vh">
	  	<div class="uip-w-100p-mobile uip-body-font uip-position-relative uip-flex-grow uip-flex uip-flex-column" id="uip-folder-app" style="font-size:15px;">
	  	</div>
	  </div>';

    echo Sanitize::clean_input_with_code($appContainer);

    $styles = "
	<style>
	  @import '{$styleSRC}';
	  .column-uip_draggable{width:28px;}
      .check-column .label-covers-full-cell+input: z-index: inherit;
	</style>";

    echo Sanitize::clean_input_with_code($styles);

    $url = uip_pro_plugin_url;
    $version = uip_pro_plugin_version;
    $folderScript = "{$url}/app/dist/folders.build.js?ver={$version}";

    wp_print_script_tag([
      'id' => 'uip-folder-app-data',
      'src' => $folderScript,
      'type' => 'module',
      'uipress-lite' => plugins_url('uipress-lite/'),
      'defer' => true,
      'ajax_url' => admin_url('admin-ajax.php'),
      'security' => wp_create_nonce('uip-security-nonce'),
      'preferences' => json_encode(UserPreferences::get()),
      'postType' => $postType,
      'limitToAuthor' => '' . self::$limitToAuthor . '',
      'limitToType' => '' . self::$limitToType . '',
    ]);
  }
}
