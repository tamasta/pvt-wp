<?php

namespace UipressPro\Classes\Extensions\UserManagement;
use UipressLite\Classes\Utils\Objects;
use UipressPro\Classes\PostTypes\uipHistory;

!defined("ABSPATH") ? exit() : "";

class HistoryApp
{
  private static $uip_site_settings_object = false;

  /**
   * Starts plugin
   *
   * @since 1.0
   */
  public static function start()
  {
    // HOOK INTO DELAYED FUNCTIONS
    add_action('uip_delay_history_event', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'uip_deleyed_new_history_event'], 10, 4);
    add_action('uip_cleanup_activity', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'uip_remove_old_activity']);

    // Exit if doing cron
    if (wp_doing_cron()) {
      return;
    }

    // Exit if site settings are not defined
    if (!defined('uip_site_settings')) {
      return;
    }

    // Load main actions
    self::$uip_site_settings_object = json_decode(uip_site_settings);
    self::load_history_actions();
  }

  /**
   * Deletes old history
   * @since 2.3.5
   */
  public static function load_history_actions()
  {
    // Is history enabled
    $historyEnabled = Objects::get_nested_property(self::$uip_site_settings_object, ['activityLog', 'activityLogEnabled']) ?? false;

    // Actions are disabled
    if ($historyEnabled != 'uiptrue') {
      return;
    }

    // Start history post type
    uipHistory::start();

    // TRACK WORDPRESS ACTIONS
    add_action('wp_footer', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'track_user_views']);
    add_action('admin_footer', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'track_user_views']);

    // POST HISTORY
    add_action('save_post', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'post_created'], 10, 3);
    add_action('transition_post_status', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'post_status_changed'], 10, 3);
    add_action('wp_trash_post', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'post_trashed'], 10);
    add_action('before_delete_post', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'post_deleted'], 10);

    // COMMENTED HISTORY
    add_action('comment_post', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'new_comment'], 10, 2);
    add_action('trash_comment', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'trash_comment'], 10, 2);
    add_action('delete_comment', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'delete_comment'], 10, 2);

    // PLUGINS
    add_action('activated_plugin', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'plugin_activated'], 10, 2);
    add_action('deactivated_plugin', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'plugin_deactivated'], 10, 2);
    add_action('deleted_plugin', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'plugin_deleted'], 10, 2);

    // LOGIN
    add_action('wp_login', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'user_last_login'], 10, 2);
    add_action('clear_auth_cookie', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'user_logout'], 10);

    // WP OPTIONS
    add_action('added_option', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'uip_site_option_added'], 10, 2);

    // IMAGES
    add_filter('wp_generate_attachment_metadata', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'uip_log_image_upload'], 10, 3);
    add_filter('delete_attachment', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'uip_log_image_delete'], 10, 2);

    // USERS
    add_filter('wp_create_user', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'uip_log_new_user'], 10, 3);
    add_filter('wp_insert_user', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'uip_log_new_user_insert'], 10, 1);
    add_filter('delete_user', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'uip_log_new_user_delete'], 10, 3);
    add_filter('profile_update', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'uip_log_user_update'], 10, 3);
    add_filter('user_register', ['UipressPro\Classes\Extensions\UserManagement\HistoryApp', 'uip_log_user_register'], 10, 2);

    ///SCHEDULE HISTORY DELETION
    if (!wp_next_scheduled('uip_cleanup_activity')) {
      wp_schedule_event(time(), 'hourly', 'uip_cleanup_activity');
    }

    return;
  }

  /**
   * Creates new history event from delayed cron job
   *
   * @since 2.3.5
   */
  public static function uip_deleyed_new_history_event($type, $context, $userID, $ip)
  {
    $postTitle = $type . '-' . time();

    if ($userID == null) {
      $userID = get_current_user_id();
    }

    $database = self::uip_history_get_database();
    self::uip_history_prep_database($database);

    // Prepare the post data.
    $post_data = [
      'post_title' => $postTitle,
      'post_type' => 'uip-history',
      'post_author' => $userID,
      'post_date' => current_time('mysql'),
      'post_status' => 'publish',
      'uip_history_type' => $type,
      'uip_history_context' => json_encode($context),
      'uip_history_ip' => $ip,
    ];

    // Insert the post data into the remote database.
    $database->insert('uip_history', $post_data);
  }

  /**
   * Deletes old history
   *
   * @since 3.0.9
   */
  public static function uip_remove_old_activity()
  {
    $details = self::uip_activity_lengths();
    $database = self::uip_history_get_database();

    $expiryDate = date('Y-m-d', strtotime('-' . $details['days'] . ' days'));
    $max = $details['quantity'];

    $currnetTotal = $database->get_var("SELECT COUNT(*) FROM `uip_history` WHERE `post_status` = 'publish'");

    ///To many entries, let's delete some
    if ($currnetTotal > $max) {
      $difference = $currnetTotal - $max;
      if ($difference > 0 && is_numeric($difference)) {
        // Prepare the delete query to remove the 60 oldest entries.
        $delete_query = $database->prepare(
          "
            DELETE FROM `uip_history`
            WHERE `ID` IN (
                SELECT * FROM (
                    SELECT `ID`
                    FROM `uip_history`
                    ORDER BY `post_date` ASC
                    LIMIT %d
                ) AS oldest_posts
            )
        ",
          $difference
        );
        // Execute the delete query on the custom database.
        $deleted_rows = $database->query($delete_query);

        // Check the result.
        if ($deleted_rows !== false) {
          error_log("Deleted {$deleted_rows} uipress history entries. (Reason: Log over max amount)");
        } else {
          error_log('Error while deleting uipress history entries: ' . $database->last_error);
        }
      }
    }

    // Prepare the delete query to remove entries older than the given date.
    $delete_query = $database->prepare(
      "
        DELETE FROM `uip_history`
        WHERE `post_date` < %s
    ",
      $expiryDate
    );

    // Execute the delete query on the custom database.
    $deleted_rows = $database->query($delete_query);

    // Check the result.
    if ($deleted_rows !== false) {
      error_log("Deleted {$deleted_rows} uipress history entries. (Reason: Items older than set limit)");
    } else {
      error_log('Error while deleting uipress history entries: ' . $database->last_error);
    }

    error_log('uip history cleanup completed');
  }

  /**
   * Preps the database for history queries
   *
   * @since 3.0.9
   */
  public static function uip_history_prep_database($database)
  {
    // Create the `posts` table in your custom database.
    $sql = "CREATE TABLE IF NOT EXISTS `uip_history` (
        `ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        `post_author` bigint(20) unsigned NOT NULL DEFAULT '0',
        `post_date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `post_date_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `post_content` longtext NOT NULL,
        `uip_history_type` longtext NOT NULL,
        `uip_history_context` longtext NOT NULL,
        `uip_history_ip` longtext NOT NULL,
        `post_title` text NOT NULL,
        `post_excerpt` text NOT NULL,
        `post_status` varchar(20) NOT NULL DEFAULT 'publish',
        `comment_status` varchar(20) NOT NULL DEFAULT 'open',
        `ping_status` varchar(20) NOT NULL DEFAULT 'open',
        `post_password` varchar(20) NOT NULL DEFAULT '',
        `post_name` varchar(200) NOT NULL DEFAULT '',
        `to_ping` text NOT NULL,
        `pinged` text NOT NULL,
        `post_modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `post_modified_gmt` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
        `post_content_filtered` longtext NOT NULL,
        `post_parent` bigint(20) unsigned NOT NULL DEFAULT '0',
        `guid` varchar(255) NOT NULL DEFAULT '',
        `menu_order` int(11) NOT NULL DEFAULT '0',
        `post_type` varchar(20) NOT NULL DEFAULT 'post',
        `post_mime_type` varchar(100) NOT NULL DEFAULT '',
        `comment_count` bigint(20) NOT NULL DEFAULT '0',
        PRIMARY KEY (`ID`),
        KEY `post_name` (`post_name`(191)),
        KEY `type_status_date` (`post_type`,`post_status`,`post_date`,`ID`),
        KEY `post_parent` (`post_parent`),
        KEY `post_author` (`post_author`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

    $database->query($sql);
  }

  /**
   * Gets the current database for history items
   *
   * @since 3.0.9
   */
  public static function uip_history_get_database()
  {
    global $wpdb;
    $database = self::uip_activity_isRemote();

    if ($database) {
      $db = new \wpdb($database->username, $database->password, $database->name, $database->host);

      if (property_exists($db, 'error')) {
        $error = $db->error;
        if (is_object($error)) {
          if (property_exists($error, 'errors')) {
            $db = $wpdb;
          }
        }
      }
    } else {
      $db = $wpdb;
    }

    return $db;
  }

  /**
   * Returns whether the activity database is set as remote
   *
   * @return
   * @since 3.2.0
   */
  public static function uip_activity_isRemote()
  {
    if (!defined('uip_site_settings')) {
      return false;
    }
    self::$uip_site_settings_object = json_decode(uip_site_settings);
    //Menu editor
    if (!isset(self::$uip_site_settings_object->activityLog) || !isset(self::$uip_site_settings_object->activityLog->databaseDetails)) {
      return false;
    } else {
      $details = self::$uip_site_settings_object->activityLog->databaseDetails;
    }

    if (!is_object($details)) {
      return false;
    }

    if (property_exists($details, 'enabled')) {
      if ($details->enabled != 'true' && $details->enabled != 'uiptrue') {
        return false;
      }
    }

    if (!property_exists($details, 'host') || !property_exists($details, 'username') || !property_exists($details, 'password') || !property_exists($details, 'name')) {
      return false;
    }

    if ($details->name == '' || $details->username == '' || $details->host == '' || $details->password == '') {
      return false;
    }

    return $details;
  }

  /**
   * Returns activity max lengths
   *
   * @since 3.2.0
   */
  public static function uip_activity_lengths()
  {
    if (!defined('uip_site_settings')) {
      return false;
    }
    self::$uip_site_settings_object = json_decode(uip_site_settings);

    //Max amount
    if (!isset(self::$uip_site_settings_object->activityLog) || !isset(self::$uip_site_settings_object->activityLog->historyMaxAmount)) {
      $maxAmount = 20000;
    } else {
      $maxAmount = self::$uip_site_settings_object->activityLog->historyMaxAmount;
      if (!is_numeric($maxAmount)) {
        $maxAmount = 20000;
      }
    }
    //Max length
    if (!isset(self::$uip_site_settings_object->activityLog) || !isset(self::$uip_site_settings_object->activityLog->historyMaxLength)) {
      $maxDays = 30;
    } else {
      $maxDays = self::$uip_site_settings_object->activityLog->historyMaxLength;
      if (!is_numeric($maxDays)) {
        $maxDays = 30;
      }
      if ($maxDays < 1) {
        $maxDays = 30;
      }
    }

    $details['quantity'] = $maxAmount;
    $details['days'] = $maxDays;

    return $details;
  }

  /**
   * Adds custom cron schedules
   * @since 2.3.5
   */
  function uip_cron_schedules($schedules)
  {
    if (!isset($schedules['1min'])) {
      $schedules['1min'] = [
        'interval' => 60,
        'display' => __('Once every 1 minutes'),
      ];
      $schedules['1hour'] = [
        'interval' => 60 * 60,
        'display' => __('Once every 1 hour'),
      ];
    }
    return $schedules;
  }

  /**
   * Capture Login Data
   * @since 1.0
   */
  public static function user_last_login($user_login, $user)
  {
    update_user_meta($user->ID, 'uip_last_login', time());
    update_user_meta($user->ID, 'uip_last_login_date', date('Y-m-d'));

    $vis_ip = self::getVisIPAddr();
    $ipdat = @json_decode(file_get_contents('http://www.geoplugin.net/json.gp?ip=' . $vis_ip));
    $country = $ipdat->geoplugin_countryName;

    update_user_meta($user->ID, 'uip_last_login_country', $country);

    $context['ip'] = $vis_ip;
    $context['country'] = $country;

    self::create_new_history_event('user_login', $context, $user->ID);
  }

  /**
   * Get User IP
   * @since 1.0
   */
  public static function getVisIpAddr()
  {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
      $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
      $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
      $ip = $_SERVER['REMOTE_ADDR'];
    }

    if (!isset(self::$uip_site_settings_object->anonymizeIP) || !isset(self::$uip_site_settings_object->activityLog->anonymizeIP)) {
      $anonymizeIP = false;
    } else {
      $anonymizeIP = self::$uip_site_settings_object->activityLog->anonymizeIP;
    }

    //Anomonise IP
    if ($anonymizeIP == 'uiptrue') {
      return hash('ripemd160', $ip);
    }

    return $ip;
  }
  /**
   * Tracks page views
   * @since 2.3.5
   */
  public static function track_user_views()
  {
    if (defined('DOING_AJAX') || !is_user_logged_in()) {
      return;
    }

    if (is_admin()) {
      $title = get_admin_page_title();
      $url = '//' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    } else {
      global $wp;
      $title = get_the_title();
      $url = home_url($wp->request);
    }

    $postTitle = 'PageView ' . time();
    $context['url'] = $url;
    $context['title'] = $title;

    self::update_recent_views($url, $title);
  }

  /**
   * Creates new history event
   *
   * @since 2.3.5
   */
  public static function create_new_history_event($type, $context, $userID = null)
  {
    if (!isset(self::$uip_site_settings_object->actionsNoTrack) || !isset(self::$uip_site_settings_object->activityLog->actionsNoTrack)) {
      $noActions = [];
    } else {
      $noActions = self::$uip_site_settings_object->activityLog->actionsNoTrack;
    }

    if (is_array($noActions) && in_array($type, $noActions)) {
      return;
    }

    $args = [
      'type' => $type,
      'context' => $context,
      'userID' => get_current_user_id(),
      'ip' => self::getVisIpAddr(),
    ];

    wp_schedule_single_event(time() + 10, 'uip_delay_history_event', $args);
  }

  /**
   * Logs recent page views
   * @since 2.3.5
   */
  public static function update_recent_views($url, $title)
  {
    $userID = get_current_user_id();
    $views = get_user_meta($userID, 'recent_page_views', true);

    ///CHECK IF NO HISTORY
    if (!is_array($views)) {
      $views = [];
      $currentpage['title'] = $title;
      $currentpage['time'] = time();
      $currentpage['url'] = $url;
      array_push($views, $currentpage);
    } else {
      $length = count($views);

      ///ONLY KEEP 5 RECORDS
      if ($length > 4) {
        array_shift($views);
        $currentpage['title'] = $title;
        $currentpage['time'] = time();
        $currentpage['url'] = $url;
        array_push($views, $currentpage);
      } else {
        $currentpage['title'] = $title;
        $currentpage['time'] = time();
        $currentpage['url'] = $url;
        array_push($views, $currentpage);
      }
    }

    update_user_meta($userID, 'recent_page_views', $views);
  }

  /**
   * Logs post creation / modification
   * @since 2.3.5
   */
  public static function post_created($post_id, $post, $update)
  {
    if (get_post_type($post_id) == 'uip-history') {
      return;
    }
    $context['title'] = $post->post_title;
    $context['url'] = get_permalink($post_id);
    $context['post_id'] = $post_id;

    if (!$update) {
      self::create_new_history_event('post_created', $context);
    }
  }

  /**
   * Logs post status change
   * @since 2.3.5
   */
  public static function post_status_changed($new_status, $old_status, $post)
  {
    if (get_post_type($post->ID) == 'uip-history') {
      return;
    }

    if ($old_status != $new_status) {
      $context['title'] = $post->post_title;
      $context['url'] = get_permalink($post->ID);
      $context['post_id'] = $post->ID;
      $context['old_status'] = $old_status;
      $context['new_status'] = $new_status;
      self::create_new_history_event('post_status_change', $context);
    }
  }

  /**Logs post trashing
   * @since 2.3.5
   */
  public static function post_trashed($post_id)
  {
    if (get_post_type($post_id) == 'uip-history') {
      return;
    }
    $context['title'] = get_the_title($post_id);
    $context['url'] = get_permalink($post_id);
    $context['post_id'] = $post_id;

    self::create_new_history_event('post_trashed', $context);
  }

  /**
   * Logs post permanent delete
   * @since 2.3.5
   */
  public static function post_deleted($post_id)
  {
    if (get_post_type($post_id) == 'uip-history') {
      return;
    }

    if (wp_is_post_revision($post_id)) {
      return;
    }

    $context['title'] = get_the_title($post_id);
    $context['url'] = get_permalink($post_id);
    $context['post_id'] = $post_id;

    self::create_new_history_event('post_deleted', $context);
  }

  /**
   * Logs new comment
   * @since 2.3.5
   */
  public static function new_comment($comment_ID, $comment_approved)
  {
    $theComment = get_comment($comment_ID);
    $comment_post_id = $theComment->comment_post_ID;
    $context['author'] = $theComment->comment_author;
    $context['content'] = $theComment->comment_content;
    $context['comment_id'] = $comment_ID;
    $context['post_id'] = $comment_post_id;

    self::create_new_history_event('new_comment', $context);
  }

  /**
   * Logs deleted comment
   * @since 2.3.5
   */
  public static function trash_comment($comment_ID, $comment_approved)
  {
    $theComment = get_comment($comment_ID);
    $comment_post_id = $theComment->comment_post_ID;
    $context['author'] = $theComment->comment_author;
    $context['content'] = $theComment->comment_content;
    $context['comment_id'] = $comment_ID;
    $context['post_id'] = $comment_post_id;

    self::create_new_history_event('trash_comment', $context);
  }

  /**
   * Logs deleted comment
   * @since 2.3.5
   */
  public static function delete_comment($comment_ID, $comment_approved)
  {
    $theComment = get_comment($comment_ID);
    $comment_post_id = $theComment->comment_post_ID;
    $context['author'] = $theComment->comment_author;
    $context['content'] = $theComment->comment_content;
    $context['comment_id'] = $comment_ID;
    $context['post_id'] = $comment_post_id;

    self::create_new_history_event('delete_comment', $context);
  }

  /**
   * Logs plugin activation
   * @since 2.3.5
   */
  public static function plugin_activated($plugin, $network_activation)
  {
    $pluginObject = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
    $context['plugin_name'] = $pluginObject['Name'];
    $context['plugin_path'] = $plugin;

    self::create_new_history_event('plugin_activated', $context);
  }

  /**
   * Logs plugin deactivation
   * @since 2.3.5
   */
  public static function plugin_deactivated($plugin, $network_activation)
  {
    $pluginObject = get_plugin_data(WP_PLUGIN_DIR . '/' . $plugin);
    $context['plugin_name'] = $pluginObject['Name'];
    $context['plugin_path'] = $plugin;

    self::create_new_history_event('plugin_deactivated', $context);
  }

  /**
   * Logs plugin deletion
   * @since 2.4.1
   */
  public static function plugin_deleted($plugin, $deleted)
  {
    if (!$deleted) {
      return;
    }
    $context['plugin_name'] = $plugin;
    $context['plugin_path'] = $plugin;

    self::create_new_history_event('plugin_deleted', $context);
  }

  /**
   * Logs user logout
   * @since 2.3.5
   */
  public static function user_logout()
  {
    $vis_ip = self::getVisIPAddr();
    $ipdat = @json_decode(file_get_contents('http://www.geoplugin.net/json.gp?ip=' . $vis_ip));
    $country = $ipdat->geoplugin_countryName;

    $context['ip'] = $vis_ip;
    $context['country'] = $country;

    self::create_new_history_event('user_logout', $context, get_current_user_id());
  }

  /**
   * Logs option change
   * @since 2.3.5
   */
  public static function uip_site_option_change($option_name, $old_value, $option_value)
  {
    if (strpos($option_name, 'transient') !== false || strpos($option_name, 'cron') !== false || strpos($option_name, 'action_scheduler') !== false) {
      return;
    }

    $oldvalue = $old_value;
    $newvalue = $option_value;

    if (is_array($oldvalue)) {
      $oldvalue = json_encode($oldvalue);
    }

    if (is_array($newvalue)) {
      $newvalue = json_encode($newvalue);
    }

    if ($oldvalue == $newvalue) {
      return;
    }

    $context['option_name'] = $option_name;
    $context['old_value'] = $old_value;
    $context['new_value'] = $option_value;

    self::create_new_history_event('option_change', $context, get_current_user_id());
  }

  /**
   * Logs option change
   * @since 2.3.5
   */
  public static function uip_site_option_added($option_name, $option_value)
  {
    if (strpos($option_name, 'transient') !== false || strpos($option_name, 'cron') !== false || strpos($option_name, 'action_scheduler') !== false) {
      return;
    }

    $newvalue = $option_value;

    if (is_array($newvalue)) {
      $newvalue = json_encode($newvalue);
    }

    $context['option_name'] = $option_name;
    $context['new_value'] = $option_value;

    self::create_new_history_event('option_added', $context, get_current_user_id());
  }

  /**
   * Logs image upload
   * @since 2.3.5
   */
  public static function uip_log_image_upload($metadata, $attachment_id, $context)
  {
    $data['name'] = get_the_title($attachment_id);

    $data = [];
    if (isset($metadata['file'])) {
      $data['path'] = $metadata['file'];
    }
    $data['image_id'] = $attachment_id;

    self::create_new_history_event('attachment_uploaded', $data, get_current_user_id());

    return $metadata;
  }

  /**
   * Logs image delete
   * @since 2.3.5
   */
  public static function uip_log_image_delete($attachment_id, $post)
  {
    $data['name'] = get_the_title($attachment_id);
    $data['image_id'] = $attachment_id;

    self::create_new_history_event('attachment_deleted', $data, get_current_user_id());
  }

  /**
   * Logs user creation
   * @since 2.3.5
   */
  public static function uip_log_new_user($username, $password, $email)
  {
    $data['username'] = $username;
    $data['email'] = $email;

    self::create_new_history_event('user_created', $data, get_current_user_id());
  }

  /**
   * Logs user creation
   * @since 2.3.5
   */
  public static function uip_log_user_register($userid, $userdata)
  {
    $userObj = new \WP_User($userid);

    $data['username'] = $userObj->user_login;
    $data['email'] = $userObj->user_email;
    $data['user_id'] = $userid;

    self::create_new_history_event('user_created', $data, get_current_user_id());
  }

  /**
   * Logs user creation
   * @since 2.3.5
   */
  public static function uip_log_new_user_insert($user)
  {
    $data['username'] = $user->user_login;
    $data['email'] = $user->user_email;

    self::create_new_history_event('user_created', $data, get_current_user_id());
  }

  /**
   * Logs user deletion
   * @since 2.3.5
   */
  public static function uip_log_new_user_delete($id, $reassign, $user)
  {
    $data['username'] = $user->user_login;
    $data['email'] = $user->user_email;
    $data['user_id'] = $id;

    self::create_new_history_event('user_deleted', $data, get_current_user_id());
  }

  /**
   * Logs user update
   * @since 2.3.5
   */
  public static function uip_log_user_update($user_id, $old_user_data, $userdata)
  {
    $userObj = new \WP_User($user_id);

    $data['username'] = $userObj->user_login;
    $data['email'] = $userObj->user_email;
    $data['user_id'] = $user_id;
    $data['old_value'] = $old_user_data;
    $data['new_value'] = $userdata;

    self::create_new_history_event('user_updated', $data, get_current_user_id());
  }
}
