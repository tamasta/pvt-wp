<?php

namespace UipressPro\Classes\Extensions\UserManagement;
use UipressLite\Classes\Utils\Sanitize;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\App\UserPreferences;
use UipressLite\Classes\App\UipOptions;
use UipressLite\Classes\Utils\Objects;
use UipressLite\Classes\Utils\UserRoles;
use UipressPro\Classes\Extensions\UserManagement\UserManagementAjax;
use UipressPro\Classes\Extensions\UserManagement\HistoryApp;

!defined('ABSPATH') ? exit() : '';

class UserManagementApp
{
  /**
   * Starts user management actions
   *
   * @since 2.3.5
   */
  public static function start()
  {
    add_action('admin_menu', ['UipressPro\Classes\Extensions\UserManagement\UserManagementApp', 'add_menu_item']);

    // AVATAR FILTER
    add_filter('get_avatar', ['UipressPro\Classes\Extensions\UserManagement\UserManagementApp', 'uip_allow_custom_avatars'], 1, 5);
    add_filter('get_avatar_url', ['UipressPro\Classes\Extensions\UserManagement\UserManagementApp', 'uip_allow_custom_avatars_url'], 10, 3);

    // Login redirect
    add_filter('wp_login', ['UipressPro\Classes\Extensions\UserManagement\UserManagementApp', 'redirect_on_login'], 10, 2);
    add_filter('admin_init', ['UipressPro\Classes\Extensions\UserManagement\UserManagementApp', 'redirect_on_home'], 10);

    HistoryApp::start();
    UserManagementAjax::start();
  }

  /**
   * Adds user management to menu
   *
   * @since 2.3.5
   */
  public static function add_menu_item()
  {
    $hook_suffix = add_submenu_page('users.php', __('User Management', 'uipress-pro'), __('User Management', 'uipress-pro'), 'list_users', 'uip-user-management', [
      'UipressPro\Classes\Extensions\UserManagement\UserManagementApp',
      'build_user_page',
    ]);

    //add_action("admin_print_scripts-{$hook_suffix}", ['UipressLite\Classes\Pages\AdminPage', 'add_hooks']);
  }

  /**
   * Allow custom images as avatar
   *
   * @since 2.3.5
   */
  public static function uip_allow_custom_avatars($avatar, $id_or_email, $size, $default, $alt)
  {
    $user = false;

    if (is_numeric($id_or_email)) {
      $id = (int) $id_or_email;
      $user = get_user_by('id', $id);
    } elseif (is_object($id_or_email)) {
      if (!empty($id_or_email->user_id)) {
        $id = (int) $id_or_email->user_id;
        $user = get_user_by('id', $id);
      }
    } else {
      $user = get_user_by('email', $id_or_email);
    }

    if ($user && is_object($user)) {
      $thepath = get_user_meta($user->data->ID, 'uip_profile_image', true);

      if ($thepath) {
        $avatar = $thepath;
        $avatar = $avatar;
        $avatar = "<img alt='{$alt}' src='{$avatar}' class='avatar avatar-{$size} photo' height='{$size}' width='{$size}' />";
      }
    }

    return $avatar;
  }

  /**
   * Allow custom images as avatar
   *
   * @since 2.3.5
   */
  public static function uip_allow_custom_avatars_url($url, $id_or_email, $args)
  {
    $user = false;

    if (is_numeric($id_or_email)) {
      $id = (int) $id_or_email;
      $user = get_user_by('id', $id);
    } elseif (is_object($id_or_email)) {
      if (!empty($id_or_email->user_id)) {
        $id = (int) $id_or_email->user_id;
        $user = get_user_by('id', $id);
      }
    } else {
      $user = get_user_by('email', $id_or_email);
    }

    if ($user && is_object($user)) {
      $thepath = get_user_meta($user->data->ID, 'uip_profile_image', true);

      if ($thepath) {
        $url = $thepath;
      }
    }

    return $url;
  }

  /**
   * Redirects users on login
   *
   * @since 3.0.9
   */
  public static function redirect_on_login($user_login, $user)
  {
    $redirects = UipOptions::get('role_redirects');

    //Get current role
    $user_roles = $user->roles;
    $user_role = array_shift($user_roles);

    if (is_array($redirects)) {
      if (isset($redirects[$user_role])) {
        if ($redirects[$user_role] != '' && $redirects[$user_role] != 'uipblank' && $redirects[$user_role] != false) {
          //Check if absolute or relative
          $userRedirect = strtolower($redirects[$user_role]);
          if (strpos($userRedirect, 'https') !== false || strpos($userRedirect, 'http') !== false) {
            $url = $userRedirect;
          } else {
            $url = admin_url($userRedirect);
          }
          wp_safe_redirect($url);
          exit();
        }
      }
    }
  }

  /**
   * Redirects users on home 'wp-admin'
   *
   * @since 3.0.9
   */
  public static function redirect_on_home()
  {
    $currentURL = home_url(sanitize_url($_SERVER['REQUEST_URI']));

    $adminURL = get_admin_url();

    //Only redirect if we are on empty /wp-admin/
    if ($currentURL != $adminURL) {
      return;
    }

    $user = wp_get_current_user();
    $redirects = UipOptions::get('role_redirects');
    $adminURL = get_admin_url();

    //Get current role
    $user_roles = $user->roles;
    $user_role = array_shift($user_roles);

    if (is_array($redirects)) {
      if (isset($redirects[$user_role])) {
        if ($redirects[$user_role] != '' && $redirects[$user_role] != 'uipblank' && $redirects[$user_role] != false) {
          //Check if absolute or relative
          $userRedirect = strtolower($redirects[$user_role]);
          if (strpos($userRedirect, 'https') !== false || strpos($userRedirect, 'http') !== false) {
            $url = $userRedirect;
          } else {
            $url = admin_url($userRedirect);
          }
          wp_safe_redirect($url);
          exit();
        }
      }
    }
  }

  /**
   * Builds users page
   *
   * @since 2.3.5
   */
  public static function build_user_page()
  {
    $styleSRC = uip_plugin_url . 'assets/css/uip-app.css';
    $styleSRCRTL = uip_plugin_url . 'assets/css/uip-app-rtl.css';

    $styleSRC = is_rtl() ? $styleSRCRTL : $styleSRC;

    $appContainer = '
    <div id="uip-user-management" class="uip-text-normal uip-background-default"></div>';

    echo Sanitize::clean_input_with_code($appContainer);

    $styles = "
    <style>
      @import '{$styleSRC}';
      #wpcontent{padding-left: 0}#wpfooter{display: none}#wpbody-content{padding:0}
    </style>";

    echo Sanitize::clean_input_with_code($styles);

    $ajaxURL = admin_url('admin-ajax.php');
    $security = wp_create_nonce('uip-security-nonce');
    $restNonce = wp_create_nonce('wp_rest');
    $restURL = get_rest_url();

    $variableFormatter = "
      var uip_ajax = {
        ajax_url: '{$ajaxURL}',
        security: '{$security}',
        rest_url: '{$restURL}',
        rest_headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': '{$restNonce}',
        },
      };";
    wp_print_inline_script_tag($variableFormatter, ['id' => 'uip-format-vars']);

    $url = uip_pro_plugin_url;
    $version = uip_pro_plugin_version;
    $userManagementScript = "{$url}app/dist/usermanagement.build.js?ver={$version}";

    wp_print_script_tag([
      'id' => 'uip-user-management-data',
      'src' => $userManagementScript,
      'type' => 'module',
      'uipress-lite' => plugins_url('uipress-lite/'),
      'capabilities' => json_encode(UserRoles::get_all_role_capabilities()),
      'defer' => true,
      'ajax_url' => $ajaxURL,
      'security' => $security,
    ]);
  }

  /**
   * Gets user activity
   *
   * @since 2.3.5
   */
  public static function get_user_activity($activityPage, $userID = null)
  {
    if (!$userID) {
      return [];
    }

    // Get and prep database
    $database = HistoryApp::uip_history_get_database();
    HistoryApp::uip_history_prep_database($database);

    $author_ids_string = implode(',', array_map('intval', [$userID]));
    $author_filter_query = " AND `post_author` IN ({$author_ids_string})";

    //SET DIRECTION
    $perpage = '10';
    $offset = ($activityPage - 1) * $perpage;

    // Perform the paged query on the custom database.
    $all_history = $database->get_results(
      $database->prepare("SELECT * FROM `uip_history` WHERE `post_status` = 'publish' {$author_filter_query} ORDER BY `post_date` DESC LIMIT %d OFFSET %d", $perpage, $offset)
    );
    //Post count
    $total_history = $database->get_var("SELECT COUNT(*) FROM `uip_history` WHERE `post_status` = 'publish' {$author_filter_query}");

    //Get total pages
    $totalpages = 0;
    if ($total_history > 0) {
      $totalpages = ceil($total_history / $perpage);
    }

    $actions = [];
    if (is_array($all_history)) {
      foreach ($all_history as $action) {
        $temp = self::format_user_activity($action);
        $actions[] = $temp;
      }
    }

    $data['list'] = $actions;
    $data['totalFound'] = $total_history;
    $data['totalPages'] = $totalpages;

    return $data;
  }

  /**
   * Formats user activity
   *
   * @param string $action
   *
   * @since 3.2.0
   */
  public static function format_user_activity($action)
  {
    $action = (array) $action;
    $returnData = [];
    $type = $action['uip_history_type'];
    $context = (array) json_decode($action['uip_history_context']);
    $ip = $action['uip_history_ip'];

    //POST TIME
    $view_time = date('u', strtotime($action['post_date']));
    $human_time = human_time_diff(date('U', strtotime($action['post_date'])));

    //GET AUTHOR DETAILS
    $authorID = $action['post_author'];
    $user_meta = get_userdata($authorID);

    if ($user_meta) {
      $username = $user_meta->user_login;
      $roles = $user_meta->roles;
      $email = $user_meta->user_email;
      $image = get_avatar_url($authorID, ['default' => 'retro']);
    } else {
      $username = __('User no longer exists', 'uipress-pro');
      $roles = [];
      $email = '';
      $image = get_avatar_url($authorID, ['default' => 'retro']);
    }

    $returnData['human_time'] = sprintf(__('%s ago', 'uipress-pro'), $human_time);
    $returnData['ip_address'] = $ip;
    $returnData['id'] = $action['ID'];
    $returnData['user'] = $username;
    $returnData['user_id'] = $authorID;
    $returnData['image'] = $image;
    $returnData['roles'] = $roles;
    $returnData['user_email'] = $email;
    $returnData['time'] = date(get_option('time_format'), strtotime($action['post_date']));
    $returnData['date'] = date(get_option('date_format'), strtotime($action['post_date']));

    if ($type == 'page_view' && is_array($context)) {
      $returnData['title'] = __('Page view', 'uipress-pro');
      $returnData['type'] = 'primary';
      $returnData['meta'] = __('Viewed page', 'uipress-pro') . " <a class='uip-link-muted uip-no-underline uip-text-bold' href='{$context['url']}'>{$context['title']}</a>";
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'post_created' && is_array($context)) {
      $post_id = $context['post_id'];
      $url = get_the_permalink($post_id);
      $title = get_the_title($post_id);
      $returnData['title'] = __('Post created', 'uipress-pro');
      $returnData['type'] = 'primary';
      $returnData['meta'] = __('Created post', 'uipress-pro') . " <a class='uip-link-muted uip-no-underline uip-text-bold' href='{$url}'>{$title}</a>";
      $returnData['links'] = [
        [
          'name' => __('View page', 'uipress-pro'),
          'url' => $url,
        ],
        [
          'name' => __('Edit page', 'uipress-pro'),
          'url' => get_edit_post_link($post_id),
        ],
      ];
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'post_updated' && is_array($context)) {
      $post_id = $context['post_id'];
      $url = get_the_permalink($post_id);
      $title = get_the_title($post_id);
      $returnData['title'] = __('Post modified', 'uipress-pro');
      $returnData['type'] = 'warning';
      $returnData['meta'] = __('Modified post', 'uipress-pro') . " <a class='uip-link-muted uip-no-underline uip-text-bold' href='{$url}'>{$title}</a>";
      $returnData['links'] = [
        [
          'name' => __('View page', 'uipress-pro'),
          'url' => $url,
        ],
        [
          'name' => __('Edit page', 'uipress-pro'),
          'url' => get_edit_post_link($post_id),
        ],
      ];
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'post_trashed' && is_array($context)) {
      $post_id = $context['post_id'];
      $url = get_the_permalink($post_id);
      $title = get_the_title($post_id);
      $returnData['title'] = __('Post moved to trash', 'uipress-pro');
      $returnData['type'] = 'warning';
      $returnData['meta'] = __('Moved post to trash', 'uipress-pro') . " <a class='uip-link-muted uip-no-underline uip-text-bold' href='{$url}'>{$title}</a>";
      $returnData['links'] = [
        [
          'name' => __('Edit page', 'uipress-pro'),
          'url' => get_edit_post_link($post_id),
        ],
      ];
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'post_deleted' && is_array($context)) {
      $returnData['title'] = __('Post deleted', 'uipress-pro');
      $returnData['type'] = 'danger';
      $returnData['meta'] = __('Deleted post', 'uipress-pro') . " <strong>{$context['title']}</strong> (ID:{$context['post_id']})";
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'post_status_change' && is_array($context)) {
      $post_id = $context['post_id'];
      $url = get_the_permalink($post_id);
      $title = get_the_title($post_id);
      $returnData['title'] = __('Post status change', 'uipress-pro');
      $returnData['type'] = 'warning';
      $returnData['meta'] =
        sprintf(__('Post status changed from %s to %s', 'uipress-pro'), $context['old_status'], $context['new_status']) .
        " <a class='uip-link-muted uip-no-underline uip-text-bold' href='{$url}'>{$title}</a> (ID:{$context['post_id']})";
      $returnData['links'] = [
        [
          'name' => __('View page', 'uipress-pro'),
          'url' => $url,
        ],
        [
          'name' => __('Edit page', 'uipress-pro'),
          'url' => get_edit_post_link($post_id),
        ],
      ];
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'new_comment' && is_array($context)) {
      $post_id = $context['post_id'];
      $url = get_the_permalink($post_id);
      $title = get_the_title($post_id);

      $returnData['title'] = __('Posted a comment', 'uipress-pro');
      $returnData['type'] = 'warning';
      $returnData['meta'] = __('Posted a comment on post', 'uipress-pro') . " <a class='uip-link-muted uip-no-underline uip-text-bold' href='{$url}'>{$title}</a>";
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];

      $com = get_comment($context['comment_id']);

      if ($com) {
        $comlink = get_comment_link($com);
        $editlink = get_edit_comment_link($context['comment_id']);
        $returnData['links'] = [
          [
            'name' => __('View comment', 'uipress-pro'),
            'url' => $comlink,
          ],
          [
            'name' => __('Edit comment', 'uipress-pro'),
            'url' => $editlink,
          ],
        ];
      }
    }

    if ($type == 'trash_comment' && is_array($context)) {
      $post_id = $context['post_id'];
      $url = get_the_permalink($post_id);
      $title = get_the_title($post_id);

      $returnData['title'] = __('Trashed a comment', 'uipress-pro');
      $returnData['type'] = 'warning';
      $returnData['meta'] = __('Moved a comment to the trash', 'uipress-pro');
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];

      $com = get_comment($context['comment_id']);

      if ($com) {
        $comlink = get_comment_link($com);
        $editlink = get_edit_comment_link($context['comment_id']);

        $returnData['links'] = [
          [
            'name' => __('View comment', 'uipress-pro'),
            'url' => $comlink,
          ],
          [
            'name' => __('Edit comment', 'uipress-pro'),
            'url' => $editlink,
          ],
        ];
      }
    }

    if ($type == 'delete_comment' && is_array($context)) {
      $com = $context['comment_id'];

      $returnData['title'] = __('Deleted a comment', 'uipress-pro');
      $returnData['type'] = 'danger';
      $returnData['meta'] = __('Permanently deleted a comment', 'uipress-pro') . " (ID:{$com})";
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'plugin_activated' && is_array($context)) {
      $returnData['title'] = __('Plugin activated', 'uipress-pro');
      $returnData['type'] = 'warning';
      $returnData['meta'] = sprintf(__('A plugin called %s was activated', 'uipress-pro'), $context['plugin_name']);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'plugin_deactivated' && is_array($context)) {
      $returnData['title'] = __('Plugin deactivated', 'uipress-pro');
      $returnData['type'] = 'danger';
      $returnData['meta'] = sprintf(__('A plugin called %s was deactivated', 'uipress-pro'), $context['plugin_name']);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'plugin_deleted' && is_array($context)) {
      $returnData['title'] = __('Plugin deleted', 'uipress-pro');
      $returnData['type'] = 'danger';
      $returnData['meta'] = sprintf(__('A plugin called %s was deleted', 'uipress-pro'), $context['plugin_name']);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'user_login' && is_array($context)) {
      $returnData['title'] = __('User logged in', 'uipress-pro');
      $returnData['type'] = 'primary';
      $returnData['meta'] = sprintf(__('Logged in with ip address %s. Country: %s', 'uipress-pro'), $context['ip'], $context['country']);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'user_logout' && is_array($context)) {
      $returnData['title'] = __('User logged out', 'uipress-pro');
      $returnData['type'] = 'primary';
      $returnData['meta'] = sprintf(__('Logged out with ip address %s. Country: %s', 'uipress-pro'), $context['ip'], $context['country']);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'option_added' && is_array($context)) {
      $newvalue = $context['new_value'];

      if (is_array($newvalue) || is_object($newvalue)) {
        $newvalue = json_encode($newvalue);
      }
      $returnData['title'] = __('Site option added', 'uipress-pro');
      $returnData['type'] = 'danger';
      $returnData['meta'] = sprintf(__('Site option (%s) was added with a value of (%s)', 'uipress-pro'), $context['option_name'], $newvalue);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'attachment_uploaded') {
      $name = '';
      if (isset($context['name'])) {
        $name = $context['name'];
      }
      $returnData['title'] = __('Uploaded attachment', 'uipress-pro');
      $returnData['type'] = 'warning';
      $returnData['meta'] = sprintf(__('Attachment called (%s) was uploaded to (%s). Attachment ID: %s', 'uipress-pro'), $name, $context['path'], $context['image_id']);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];

      $attachment = get_edit_post_link($context['image_id'], '&');

      if ($attachment) {
        $returnData['links'] = [
          [
            'name' => __('View attachment', 'uipress-pro'),
            'url' => $attachment,
          ],
        ];
      }
    }

    if ($type == 'attachment_deleted' && is_array($context)) {
      $returnData['title'] = __('Deleted attachment', 'uipress-pro');
      $returnData['type'] = 'danger';
      $returnData['meta'] = sprintf(__('Attachment called (%s) was deleted. Attachment ID: %s', 'uipress-pro'), $context['name'], $context['image_id']);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'user_created' && is_array($context)) {
      $returnData['title'] = __('User created', 'uipress-pro');
      $returnData['type'] = 'warning';
      $returnData['meta'] = sprintf(__('New user created with username (%s) and email (%s)', 'uipress-pro'), $context['username'], $context['email']);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'user_deleted' && is_array($context)) {
      $returnData['title'] = __('User deleted', 'uipress-pro');
      $returnData['type'] = 'danger';
      $returnData['meta'] = sprintf(__('A user with username (%s) and email (%s) was deleted', 'uipress-pro'), $context['username'], $context['email']);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    if ($type == 'user_updated' && is_array($context)) {
      $oldvalue = $context['old_value'];
      $newvalue = $context['new_value'];

      if (is_array($oldvalue) || is_object($oldvalue)) {
        $oldvalue = json_encode($oldvalue, JSON_PRETTY_PRINT);
      }

      if (is_array($newvalue) || is_object($newvalue)) {
        $newvalue = json_encode($newvalue, JSON_PRETTY_PRINT);
      }

      if (strlen($oldvalue) > 20) {
        $fullvalue = $oldvalue;
        $short = substr($oldvalue, 0, 20) . ' ... ';
        $oldvalue = '<inline-drop>';
        $oldvalue .= "<trigger><strong>{$short}</strong></trigger>";
        $oldvalue .= "<drop-content class='uip-padding-xs uip-shadow uip-border-round uip-max-h-200 uip-max-w-300 uip-overflow-auto uip-background-default' style='left:50%;transform:translateX(-50%)'><pre>{$fullvalue}</pre><drop-content>";
        $oldvalue .= '</inline-drop>';
      }

      if (strlen($newvalue) > 20) {
        $fullvalue = $newvalue;
        $short = substr($newvalue, 0, 20) . ' ... ';
        $newvalue = '<inline-drop >';
        $newvalue .= "<trigger><strong>{$short}</strong></trigger>";
        $newvalue .= "<drop-content class='uip-padding-xs uip-shadow uip-border-round uip-max-h-200 uip-max-w-300 uip-overflow-auto uip-background-default' style='left:50%;transform:translateX(-50%)'><pre>{$fullvalue}</pre><drop-content>";
        $newvalue .= '</inline-drop>';
      }

      $returnData['title'] = __('User updated', 'uipress-pro');
      $returnData['type'] = 'warning';
      $returnData['meta'] = sprintf(__('A user with username (%s) and email (%s) was updated from (%s) to (%s)', 'uipress-pro'), $context['username'], $context['email'], $oldvalue, $newvalue);
      $returnData['action'] = $returnData['title'];
      $returnData['description'] = $returnData['meta'];
    }

    return $returnData;
  }

  /**
   * Gets users recent page views
   *
   * @since 2.3.5
   */
  public static function get_user_page_views($userID)
  {
    $recent_page_views = get_user_meta($userID, 'recent_page_views', true);
    $page_views = [];

    if (is_array($recent_page_views)) {
      foreach ($recent_page_views as $view) {
        $view_time = $view['time'];
        $human_time = human_time_diff($view_time);

        $view['human_time'] = sprintf(__('%s ago', 'uipress-pro'), $human_time);
        array_push($page_views, $view);
      }
    }

    $page_views = array_reverse($page_views);

    return $page_views;
  }

  /**
   * Returns date filters
   *
   * @param string $date
   * @param string $type
   * @param array $args
   *
   * @since 3.2.0
   */
  public static function returnDateFilter($date, $type, $args)
  {
    if ($type == 'on') {
      $year = date('Y', strtotime($date));
      $month = date('m', strtotime($date));
      $day = date('d', strtotime($date));

      $args['date_query'] = [
        'year' => $year,
        'month' => $month,
        'day' => $day,
      ];
    } else {
      if ($type == 'before') {
        $args['date_query'] = [
          [
            'before' => date('Y-m-d', strtotime($date)),
            'inclusive' => true,
          ],
        ];
      } elseif ($type == 'after') {
        $args['date_query'] = [
          [
            'after' => date('Y-m-d', strtotime($date)),
            'inclusive' => true,
          ],
        ];
      }
    }

    return $args;
  }

  /**
   * Formats user data
   *
   * @since 2.3.5
   */
  public static function uip_format_user_data($all_users)
  {
    $allusers = [];
    foreach ($all_users as $user) {
      $user_meta = get_userdata($user->ID);
      $first_name = $user_meta->first_name;
      $last_name = $user_meta->last_name;
      $full_name = $first_name . ' ' . $last_name;
      $roles = $user->roles;

      //$hasimage = get_avatar($user->ID);
      $image = get_avatar_url($user->ID, ['default' => 'retro']);

      $expiry = get_user_meta($user->ID, 'uip-user-expiry', true);
      $last_login = get_user_meta($user->ID, 'uip_last_login_date', true);
      $group = get_user_meta($user->ID, 'uip_user_group', true);

      if ($last_login) {
        $last_login = date(get_option('date_format'), strtotime($last_login));
      }

      $dateformat = get_option('date_format');
      $formattedCreated = date($dateformat, strtotime($user->user_registered));

      $temp['username'] = $user->user_login;
      $temp['user_email'] = $user->user_email;
      $temp['name'] = $full_name;
      $temp['first_name'] = $user->first_name;
      $temp['last_name'] = $user->last_name;
      $temp['uip_last_login_date'] = $last_login;
      $temp['roles'] = $roles;
      $temp['image'] = $image;
      $temp['initial'] = strtoupper($user->user_login[0]);
      $temp['user_id'] = $user->ID;
      $temp['expiry'] = $expiry;
      $temp['user_registered'] = $formattedCreated;
      $temp['uip_user_group'] = $group;
      $allusers[] = $temp;
    }

    return $allusers;
  }

  /**
   * Returns list of history actions
   * @since 2.3.5
   */

  public static function uip_return_history_actions()
  {
    return [
      [
        'name' => 'page_view',
        'label' => __('Page view', 'uipress-pro'),
      ],
      [
        'name' => 'post_created',
        'label' => __('Post created', 'uipress-pro'),
      ],
      [
        'name' => 'post_updated',
        'label' => __('Post updated', 'uipress-pro'),
      ],
      [
        'name' => 'post_trashed',
        'label' => __('Post trashed', 'uipress-pro'),
      ],
      [
        'name' => 'post_deleted',
        'label' => __('Post deleted', 'uipress-pro'),
      ],
      [
        'name' => 'post_status_change',
        'label' => __('Post status change', 'uipress-pro'),
      ],
      [
        'name' => 'trash_comment',
        'label' => __('Trashed comment', 'uipress-pro'),
      ],
      [
        'name' => 'delete_comment',
        'label' => __('Deelete comment', 'uipress-pro'),
      ],

      [
        'name' => 'plugin_activated',
        'label' => __('Plugin activated', 'uipress-pro'),
      ],
      [
        'name' => 'plugin_deactivated',
        'label' => __('Plugin deactivated', 'uipress-pro'),
      ],
      [
        'name' => 'plugin_deleted',
        'label' => __('Plugin deleted', 'uipress-pro'),
      ],
      [
        'name' => 'user_login',
        'label' => __('User login', 'uipress-pro'),
      ],
      [
        'name' => 'user_logout',
        'label' => __('User logout', 'uipress-pro'),
      ],
      [
        'name' => 'option_change',
        'label' => __('Option change', 'uipress-pro'),
      ],
      [
        'name' => 'option_added',
        'label' => __('Site option added', 'uipress-pro'),
      ],
      [
        'name' => 'attachment_uploaded',
        'label' => __('Attachmnet uploaded', 'uipress-pro'),
      ],
      [
        'name' => 'attachment_deleted',
        'label' => __('Attachmnet deleted', 'uipress-pro'),
      ],
      [
        'name' => 'user_created',
        'label' => __('User created', 'uipress-pro'),
      ],
      [
        'name' => 'user_deleted',
        'label' => __('User deleted', 'uipress-pro'),
      ],
      [
        'name' => 'user_updated',
        'label' => __('User updated', 'uipress-pro'),
      ],
    ];
  }
}
