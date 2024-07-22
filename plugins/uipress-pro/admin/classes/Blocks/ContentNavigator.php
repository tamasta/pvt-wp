<?php
namespace UipressPro\Classes\Blocks;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\Utils\Sanitize;

!defined('ABSPATH') ? exit() : '';

class ContentNavigator
{
  /**
   * Builds default folders for post content
   *
   * @since 3.0.93
   */
  public static function get_navigator_defaults()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $types = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['postTypes'])));
    $authorLimit = sanitize_text_field($_POST['limitToauthor']);

    if (!is_array($types) || empty($types)) {
      $types = false;
    }

    //No limit on specific post types so let's fetch all public ones
    $args = ['public' => true];
    $output = 'objects';
    $operator = 'and';
    $post_types = get_post_types($args, $output, $operator);
    //Build array of post types with nice name
    $formatted = [];
    foreach ($post_types as $type) {
      if ($types) {
        if (!in_array($type->name, $types)) {
          continue;
        }
      }

      $temp = [];
      $temp['name'] = $type->labels->singular_name;
      $temp['label'] = $type->labels->name;
      $temp['type'] = $type->name;
      $temp['count'] = 0;
      $temp['content'] = [];
      $temp['new_href'] = admin_url('post-new.php?post_type=' . $type->name);

      //Count posts
      if ($authorLimit == 'true') {
        $args = [
          'author' => get_current_user_id(),
          'post_type' => $type->name,
          'post_status' => ['publish', 'pending', 'draft', 'future'],
        ];
        $postCount = new WP_Query($args);
        $temp['count'] = $postCount->found_posts;
      } else {
        $allposts = wp_count_posts($type->name);

        if (isset($allposts->publish)) {
          $temp['count'] = $allposts->publish;
        }
        if (isset($allposts->draft)) {
          $temp['count'] += $allposts->draft;
        }
        if (isset($allposts->inherit)) {
          $temp['count'] += $allposts->inherit;
        }
      }

      $formatted[] = $temp;
    }

    ////
    ///Get base folders
    ////
    $args = [
      'post_type' => 'uip-ui-folder',
      'posts_per_page' => -1,
      'post_status' => 'publish',
      'orderby' => 'title',
      'order' => 'ASC',
      'meta_query' => [
        [
          'key' => 'uip-folder-parent',
          'value' => 'uipfalse',
          'compare' => '=',
        ],
      ],
    ];

    if ($authorLimit == 'true') {
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
      $temp['count'] = self::get_folder_content_count($folder->ID, $types, $authorLimit);
      $temp['color'] = get_post_meta($folder->ID, 'uip-folder-color', true);
      $temp['type'] = 'uip-ui-folder';
      $temp['content'] = [];
      $temp['canDelete'] = $canDelete;
      $formattedFolders[] = $temp;
    }

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['postTypes'] = $formatted;
    $returndata['baseFolders'] = $formattedFolders;

    wp_send_json($returndata);
  }

  /**
   * Gets default post type content for the content navigator
   *
   * @since 3.0.93
   */
  public static function get_default_content()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $postType = sanitize_text_field($_POST['postType']);
    $page = sanitize_text_field($_POST['page']);
    $search = sanitize_text_field($_POST['search']);
    $authorLimit = sanitize_text_field($_POST['limitToauthor']);

    if (!$postType || $postType == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('No post type to fetch content for', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (!post_type_exists($postType)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Post type does not exist', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (!$page || $page == '') {
      $page = 1;
    }
    //Get template
    $args = [
      'post_type' => $postType,
      'posts_per_page' => 10,
      'paged' => $page,
      'post_status' => ['publish', 'draft', 'inherit'],
    ];

    if ($authorLimit == 'true') {
      $args['author'] = get_current_user_id();
    }

    if ($search && $search != '' && $search != 'undefined') {
      $args['s'] = $search;
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

      $formatted[] = $temp;
    }

    if (empty($formatted)) {
      $formatted = [];
    }

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['content'] = $formatted;
    $returndata['totalFound'] = $totalFound;

    wp_send_json($returndata);
  }

  /**
   * Creates new folder
   *
   * @since 3.0.93
   */
  public static function create_folder()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $folderParent = sanitize_text_field($_POST['folderParent']);
    $folderName = sanitize_text_field($_POST['folderName']);
    $folderColor = sanitize_text_field($_POST['folderColor']);

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
   * Gets content for given folder
   *
   * @since 3.0.93
   */
  public static function get_folder_content()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $postTypes = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['postTypes'])));
    $page = sanitize_text_field($_POST['page']);
    $search = sanitize_text_field($_POST['search']);
    $folderID = sanitize_text_field($_POST['id']);
    $authorLimit = sanitize_text_field($_POST['limitToauthor']);

    if (!$folderID || $folderID == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('No folder given to fetch content for', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (!$page || $page == '') {
      $page = 1;
    }

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
      'posts_per_page' => 10,
      'paged' => $page,
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

    if ($authorLimit == 'true') {
      $args['author'] = get_current_user_id();
    }

    if ($search && $search != '' && $search != 'undefined') {
      $args['s'] = $search;
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
        $temp['count'] = self::get_folder_content_count($post->ID, $postTypes, $authorLimit);
        $temp['color'] = get_post_meta($post->ID, 'uip-folder-color', true);
      }

      $formatted[] = $temp;
    }

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['content'] = $formatted;
    $returndata['totalFound'] = $totalFound;

    wp_send_json($returndata);
  }

  /**
   * Updates item folder after drag and drop
   *
   * @since 3.0.93
   */
  public static function update_item_folder()
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

      //If old parent is in current parent, remove it
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
   * Deletes folder
   *
   * @since 3.0.93
   */
  public static function delete_folder()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $folderID = sanitize_text_field($_POST['folderId']);
    $postTypes = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['postTypes'])));

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

    self::removeFromFolder($folderID, $postTypes);

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;

    wp_send_json($returndata);
  }

  /**
   * Updates folder
   *
   * @since 3.0.93
   */
  public static function update_folder()
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
   * Duplicates post
   *
   * @since 3.0.93
   */
  public static function duplicate_post()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    global $wpdb;
    $post_id = sanitize_text_field($_POST['postID']);

    //No folder id
    if (!$post_id || $post_id == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('No post to duplicate', 'uipress-pro');
      wp_send_json($returndata);
    }
    //Folder does not exist
    if (!get_post_status($post_id)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Post does not exist', 'uipress-pro');
      wp_send_json($returndata);
    }

    $post = get_post($post_id);

    $current_user = wp_get_current_user();
    $new_post_author = $current_user->ID;
    $updatedTitle = $post->post_title . ' (' . __('copy', 'uipress-pro') . ')';

    $args = [
      'comment_status' => $post->comment_status,
      'ping_status' => $post->ping_status,
      'post_author' => $new_post_author,
      'post_content' => $post->post_content,
      'post_excerpt' => $post->post_excerpt,
      'post_name' => $post->post_name,
      'post_parent' => $post->post_parent,
      'post_password' => $post->post_password,
      'post_status' => 'draft',
      'post_title' => $updatedTitle,
      'post_type' => $post->post_type,
      'to_ping' => $post->to_ping,
      'menu_order' => $post->menu_order,
    ];

    $new_post_id = wp_insert_post($args);

    if (!$new_post_id) {
      return false;
    }

    $taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
    foreach ($taxonomies as $taxonomy) {
      $post_terms = wp_get_object_terms($post_id, $taxonomy, ['fields' => 'slugs']);
      wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
    }

    $post_meta_infos = $wpdb->get_results("SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id=$post_id");
    if (count($post_meta_infos) != 0) {
      $sql_query = "INSERT INTO $wpdb->postmeta (post_id, meta_key, meta_value) ";
      foreach ($post_meta_infos as $meta_info) {
        $meta_key = $meta_info->meta_key;
        if ($meta_key == '_wp_old_slug') {
          continue;
        }
        $meta_value = addslashes($meta_info->meta_value);
        $sql_query_sel[] = "SELECT $new_post_id, '$meta_key', '$meta_value'";
      }

      $sql_query .= implode(' UNION ALL ', $sql_query_sel);
      $wpdb->query($sql_query);
    }

    $postobject = get_post($new_post_id);

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['newID'] = $new_post_id;
    $returndata['newTitle'] = $updatedTitle;
    wp_send_json($returndata);
  }

  /**
   * Deletes post from folder
   *
   * @since 3.0.93
   */
  public static function delete_post_from_folder()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $postID = sanitize_text_field($_POST['postID']);

    //No folder id
    if (!$postID || $postID == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('No post to delete', 'uipress-pro');
      wp_send_json($returndata);
    }
    //Folder does not exist
    if (!get_post_status($postID)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Post does not exist', 'uipress-pro');
      wp_send_json($returndata);
    }
    //Incorrect caps
    if (!current_user_can('delete_post', $postID)) {
      $returndata['error'] = true;
      $returndata['message'] = __('You do not have the correct capabilities to delete this post', 'uipress-pro');
      wp_send_json($returndata);
    }

    //Delete but leave in the trash just in case
    $status = wp_delete_post($postID, false);

    //Something went wrong
    if (!$status) {
      $returndata['error'] = true;
      $returndata['message'] = __('Unable to delete the post right now', 'uipress-pro');
      wp_send_json($returndata);
    }

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;

    wp_send_json($returndata);
  }

  /**
   * Counts folder content
   *
   * @since 3.0.92
   */
  private static function get_folder_content_count($folderID, $postTypes, $authorLimit)
  {
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

    if ($authorLimit == 'true') {
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
  private static function removeFromFolder($folderID, $postTypes)
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

      //Recursively remove folders inside folders
      $type = get_post_type($post->ID);
      if ($type == 'uip-ui-folder') {
        if (current_user_can('delete_post', $post->ID)) {
          wp_delete_post($post->ID, true);
        }
        self::removeFromFolder($post->ID, $postTypes);
      }
    }
  }
}
