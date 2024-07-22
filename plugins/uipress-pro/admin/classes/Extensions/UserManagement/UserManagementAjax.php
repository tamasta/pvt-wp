<?php
namespace UipressPro\Classes\Extensions\UserManagement;
use UipressLite\Classes\Utils\Sanitize;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\App\UserPreferences;
use UipressLite\Classes\App\UipOptions;
use UipressLite\Classes\Utils\Objects;
use UipressLite\Classes\Utils\UserRoles;
use UipressPro\Classes\Extensions\UserManagement\UserManagementApp;
use UipressPro\Classes\Extensions\UserManagement\HistoryApp;

!defined('ABSPATH') ? exit() : '';

class UserManagementAjax
{
  /**
   * Adds ajax actions
   * @since 2.3.5
   */

  public static function start()
  {
    // User actions
    add_action('wp_ajax_uip_get_user_table_data', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'query_all_users']);
    add_action('wp_ajax_uip_get_user_data', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_get_user_data']);
    add_action('wp_ajax_uip_delete_user', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_delete_user']);
    add_action('wp_ajax_uip_logout_user_everywhere', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_logout_user_everywhere']);
    add_action('wp_ajax_uip_reset_password', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_reset_password']);
    add_action('wp_ajax_uip_update_user', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_update_user']);
    add_action('wp_ajax_uip_add_new_user', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_add_new_user']);
    add_action('wp_ajax_uip_send_message', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_send_message']);

    // Role actions
    add_action('wp_ajax_uip_get_role_table_data', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_get_role_table_data']);
    add_action('wp_ajax_uip_get_singular_role', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_get_singular_role']);
    add_action('wp_ajax_uip_update_user_role', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_update_user_role']);
    add_action('wp_ajax_uip_add_custom_capability', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_add_custom_capability']);
    add_action('wp_ajax_uip_remove_custom_capability', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_remove_custom_capability']);
    add_action('wp_ajax_uip_delete_roles', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_delete_roles']);

    // History actions
    add_action('wp_ajax_uip_get_activity_table_data', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_get_activity_table_data']);
    add_action('wp_ajax_uip_delete_multiple_actions', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_delete_multiple_actions']);
    add_action('wp_ajax_uip_delete_all_history', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'uip_delete_all_history']);
  }

  /**
   * Tests remote database details
   *
   * @since 3.0.97
   */
  public static function test_remote_database()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $dataBase = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['dataBase'])));

    if (!$dataBase || !is_object($dataBase)) {
      $returndata['error'] = true;
      $returndata['message'] = __('No details given to test', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (!isset($dataBase->name) || !isset($dataBase->host) || !isset($dataBase->username) || !isset($dataBase->password)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Please fill in all database details', 'uipress-pro');
      wp_send_json($returndata);
    }

    global $wpdb;
    $wpdb_backup = $wpdb; // backup existing instance
    $wpdb = new \wpdb($dataBase->username, $dataBase->password, $dataBase->name, $dataBase->host); // create new db instance

    if (property_exists($wpdb, 'error')) {
      $error = $wpdb->error;
      if (is_object($error)) {
        if (property_exists($error, 'errors')) {
          $errors = $error->errors;
          if (isset($errors['db_connect_fail'])) {
            $wpdb = $wpdb_backup;
            $returndata['error'] = true;
            $returndata['message'] = __('Unable to connect to database', 'uipress-pro');
            $returndata['details'] = __('Double check you have enetered your details correctly and try again', 'uipress-pro');
            wp_send_json($returndata);
          }
        }
      }
    }

    $wpdb = $wpdb_backup;

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['message'] = __('Connection successful', 'uipress-pro');
    wp_send_json($returndata);
  }

  /**
   * Gets data for user table
   * @since 2.3.5
   */

  public static function query_all_users()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $filters = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['filters'])));
    $options = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['options'])));
    $page = isset($filters['page']) ? $filters['page'] : 1;

    // SET SEARCH QUERY
    $s_query = isset($filters['search']) ? $filters['search'] : '';

    // SET ROLE FILTERS
    $roles = [];
    if (isset($filters['roles']) && is_array($filters['roles'])) {
      foreach ($filters['roles'] as $role) {
        $roles[] = $role->name;
      }
    }

    // SET DIRECTION
    $direction = 'ASC';
    if (isset($options['direction']) && $options['direction'] != '') {
      $direction = $options['direction'];
    }

    // SET PER PAGE
    $perpage = '20';
    if (isset($options['perPage']) && $options['perPage'] != '') {
      $perpage = $options['perPage'];
    }

    $args = [
      'number' => $perpage,
      'role__in' => $roles,
      'search' => '*' . $s_query . '*',
      'paged' => $page,
      'order' => $direction,
    ];

    //SET ORDERBY
    $sortBy = 'username';
    if (isset($options['sortBy']) && $options['sortBy'] != '') {
      $sortBy = $options['sortBy'];
    }

    // SET ORDER BY
    $metakeys = ['first_name', 'last_name', 'last_name', 'uip_last_login_date', 'uip_user_group'];

    if (in_array($sortBy, $metakeys)) {
      $args['orderby'] = 'meta_value';
      $args['meta_key'] = $sortBy;
    } elseif ($sortBy == 'roles') {
      $args['orderby'] = 'meta_value';
      $args['meta_key'] = 'wp_capabilities';
    } else {
      $args['orderby'] = $sortBy;
    }

    if (isset($filters['dateCreated']) && is_object($filters['dateCreated'])) {
      $dateFilters = (array) $filters['dateCreated'];
      if (isset($dateFilters['date']) && $dateFilters['date'] != '') {
        $dateCreated = $dateFilters['date'];
        $dataComparison = $dateFilters['type'];

        $args = UserManagementApp::returnDateFilter($dateCreated, $dataComparison, $args);
      }
    }

    $user_query = new \WP_User_Query($args);
    $all_users = $user_query->get_results();
    $total_users = $user_query->get_total();
    $total_pages = ceil($total_users / $args['number']);

    $returnData['tableData']['totalFound'] = number_format($total_users);
    $returnData['tableData']['totalPages'] = $total_pages;
    $returnData['tableData']['users'] = UserManagementApp::uip_format_user_data($all_users);

    wp_send_json($returnData);
  }

  /**
   * Gets data for user table
   *
   * @since 3.0.9
   */
  public static function uip_get_activity_table_data()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $filters = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['filters'])));
    $options = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['options'])));

    $page = isset($options['page']) ? $options['page'] : 1;

    // SET SEARCH QUERY
    $s_query = isset($filters['search']) ? $filters['search'] : '';

    // SET ROLE FILTERS
    $roles = is_array($filters['roles']) ? $filters['roles'] : [];
    $formattedRoles = [];
    foreach ($roles as $role) {
      $formattedRoles[] = is_object($role) ? $role->name : $role;
    }
    $user_ids = [];

    if (count($formattedRoles) > 0) {
      $userargs = [
        'role__in' => $formattedRoles,
        'fields' => ['ID'],
      ];

      $users = get_users($userargs);

      foreach ($users as $user) {
        $user_ids[] = $user->ID;
      }

      if (count($user_ids) === 0) {
        $user_ids = [0];
      }
    }

    // SET DIRECTION
    $direction = 'ASC';
    if (isset($options['direction']) && $options['direction'] != '') {
      $direction = $options['direction'];
    }

    // Set Page
    $perpage = '20';
    if (isset($options['perPage']) && $options['perPage'] != '') {
      $perpage = $options['perPage'];
    }

    // Get and prep database
    $database = HistoryApp::uip_history_get_database();
    HistoryApp::uip_history_prep_database($database);

    //Build search query
    $search_query = '';
    if ($s_query != '') {
      $search_term = '%' . $database->esc_like($s_query) . '%';
      $search_query = $database->prepare(
        ' AND (`post_title` LIKE %s OR `post_content` LIKE %s OR `uip_history_type` LIKE %s OR `uip_history_context` LIKE %s OR `uip_history_ip` LIKE %s)',
        $search_term,
        $search_term,
        $search_term,
        $search_term,
        $search_term
      );
    }

    $author_filter_query = '';
    if (count($user_ids) > 0) {
      // Convert the author IDs array to a comma-separated string for use in the SQL query.
      $author_ids_string = implode(',', array_map('intval', $user_ids));
      $author_filter_query = " AND `post_author` IN ({$author_ids_string})";
    }

    $date_filter_query = '';
    if (isset($filters['dateCreated']) && is_object($filters['dateCreated'])) {
      $dateFilters = (array) $filters['dateCreated'];
      if (isset($dateFilters['date']) && $dateFilters['date'] != '') {
        $dateCreated = $dateFilters['date'];
        $dataComparison = $dateFilters['type'];

        if ($dataComparison == 'before') {
          $date_filter_query = $database->prepare(' AND DATE(`post_date`) < %s', $dateCreated);
        } elseif ($dataComparison == 'after') {
          $date_filter_query = $database->prepare(' AND DATE(`post_date`) > %s', $dateCreated);
        } else {
          $date_filter_query = $database->prepare(' AND DATE(`post_date`) = %s', $dateCreated);
        }
      }
    }

    ///Status filters
    $status_filter_query = '';
    if (isset($filters['status']) && is_array($filters['status']) && count($filters['status']) > 0) {
      $statuses = (array) $filters['status'];
      // Convert the array of statuses to a comma-separated string surrounded by quotes for use in the SQL query.
      $statuses_string = implode(
        ', ',
        array_map(function ($status) use ($database) {
          return $database->prepare('%s', $status);
        }, $statuses)
      );
      $status_filter_query = " AND `uip_history_type` IN ({$statuses_string})";
    }

    // Calculate the offset.
    $offset = ($page - 1) * $perpage;

    // Perform the paged query on the custom database.
    $all_history = $database->get_results(
      $database->prepare(
        "SELECT * FROM `uip_history` WHERE `post_status` = 'publish' {$search_query} {$author_filter_query} {$status_filter_query} {$date_filter_query} ORDER BY `post_date` DESC LIMIT %d OFFSET %d",
        $perpage,
        $offset
      )
    );

    //Post count
    $total_history = $database->get_var("SELECT COUNT(*) FROM `uip_history` WHERE `post_status` = 'publish' {$search_query} {$author_filter_query} {$status_filter_query} {$date_filter_query}");

    //Get total pages
    $totalpages = 0;
    if ($total_history > 0) {
      $totalpages = ceil($total_history / $perpage);
    }

    $formatted = [];
    foreach ($all_history as $action) {
      $formatted[] = UserManagementApp::format_user_activity($action);
    }

    $returnData['tableData']['totalFound'] = number_format($total_history);
    $returnData['tableData']['activity'] = $formatted;
    $returnData['tableData']['totalPages'] = $totalpages;
    $returnData['tableData']['actions'] = UserManagementApp::uip_return_history_actions();

    wp_send_json($returnData);
  }

  /**
   * Gets singular role data
   * @since 3.0.9
   */

  public static function uip_get_singular_role()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $role = sanitize_text_field($_POST['role']);

    if (!$role || $role == '') {
      $message = __('No role given', 'uipress-pro');
      Ajax::error($message);
    }

    $value = get_role($role);

    $roleData = [];
    $roleData['name'] = $role;
    $roleData['label'] = $role_name = $role ? wp_roles()->get_names()[$role] : '';
    $roleData['caps'] = $value->capabilities;
    $roleData['all'] = $value;
    $roleData['granted'] = count($value->capabilities);
    $roleData['redirect'] = '';

    $redirects = UipOptions::get('role_redirects');

    if (is_array($redirects)) {
      if (isset($redirects[$role])) {
        $roleData['redirect'] = $redirects[$role];
      }
    }

    $temp = htmlspecialchars_decode(json_encode($roleData));

    $returnData['role'] = json_decode($temp);
    wp_send_json($returnData);
  }

  /**
   * Gets data for role table
   * @since 2.3.5
   */

  public static function uip_get_role_table_data()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    global $wp_roles;

    $allroles = [];

    global $wp_roles;
    $all_roles = [];

    foreach ($wp_roles->roles as $key => $value) {
      $temp = [];

      if (!isset($value['name']) || $value['name'] == '') {
        continue;
      }

      $temp['name'] = $key;
      $temp['label'] = $value['name'];
      $temp['caps'] = $value['capabilities'];
      $temp['granted'] = count($value['capabilities']);

      $args = [
        'number' => -1,
        'role__in' => [$key],
      ];

      $user_query = new \WP_User_Query($args);
      $allUsers = $user_query->get_results();

      $count = 0;
      $userHolder = [];
      if (!empty($allUsers)) {
        foreach ($allUsers as $user) {
          $userHolder[] = $user->user_login;
          $count += 1;
          if ($count > 4) {
            break;
          }
        }
      }

      $temp['users'] = $userHolder;
      $temp['usersCount'] = $user_query->get_total();

      array_push($all_roles, $temp);
    }

    usort($all_roles, function ($a, $b) {
      return strcmp($a['name'], $b['name']);
    });

    $returnData['roles'] = $all_roles;

    wp_send_json($returnData);
  }

  /**
   * Gets data for specific user
   * @since 2.3.5
   */

  public static function uip_get_user_data()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $userID = sanitize_text_field($_POST['userID']);
    $activityPage = $_POST['activityPage'] ? Sanitize::clean_input_with_code($_POST['activityPage']) : 1;

    $user_meta = get_userdata($userID);

    $first_name = $user_meta->first_name;
    $last_name = $user_meta->last_name;
    $full_name = $first_name . ' ' . $last_name;
    $roles = $user_meta->roles;

    //$hasimage = get_avatar($user->ID);
    $image = get_avatar_url($user_meta->ID, ['default' => 'retro']);

    $expiry = get_user_meta($user_meta->ID, 'uip-user-expiry', true);
    $last_login = get_user_meta($user_meta->ID, 'uip_last_login_date', true);
    $last_login_country = get_user_meta($user_meta->ID, 'uip_last_login_country', true);
    $user_notes = get_user_meta($user_meta->ID, 'uip_user_notes', true);
    $profileImage = get_user_meta($user_meta->ID, 'uip_profile_image', true);
    $groups = get_user_meta($user_meta->ID, 'uip_user_group', true);

    if (!is_array($groups)) {
      $groups = [];
    }

    if ($last_login) {
      $last_login = date(get_option('date_format'), strtotime($last_login));
    }

    if (!$last_login_country || $last_login_country == '') {
      $last_login_country = __('Unknown', 'uipress-pro');
    }

    $dateformat = get_option('date_format');
    $formattedCreated = date($dateformat, strtotime($user_meta->user_registered));

    $temp['username'] = $user_meta->user_login;
    $temp['user_email'] = $user_meta->user_email;
    $temp['name'] = $full_name;
    $temp['first_name'] = $user_meta->first_name;
    $temp['last_name'] = $user_meta->last_name;
    $temp['uip_last_login_date'] = $last_login;
    $temp['uip_last_login_country'] = $last_login_country;
    $temp['roles'] = $roles;
    $temp['image'] = $image;
    $temp['initial'] = strtoupper($user_meta->user_login[0]);
    $temp['user_id'] = $user_meta->ID;
    $temp['expiry'] = $expiry;
    $temp['user_registered'] = $formattedCreated;
    $temp['notes'] = $user_notes;
    $temp['uip_profile_image'] = $profileImage;
    $temp['uip_user_group'] = $groups;

    $args = [
      'user_id' => $userID,
      'count' => true,
    ];
    $comments = get_comments($args);

    $args = [
      'public' => true,
    ];

    $output = 'names'; // 'names' or 'objects' (default: 'names')

    $post_types = get_post_types($args, $output);
    $formatted = [];
    foreach ($post_types as $type) {
      $formatted[] = $type;
    }

    $postcount = count_user_posts($userID, $formatted, true);

    $temp['totalComments'] = $comments;
    $temp['totalPosts'] = $postcount;

    $returnData['user'] = $temp;
    $returnData['recentPageViews'] = UserManagementApp::get_user_page_views($userID);
    $returnData['history'] = UserManagementApp::get_user_activity($activityPage, $userID);

    wp_send_json($returnData);
  }

  /**
   * Updates user info
   * @since 2.3.5
   */

  public static function uip_update_user()
  {
    Ajax::check_referer();

    $user = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['user'])));

    if (!current_user_can('edit_users')) {
      $message = __("You don't have sufficent priviledges to edit users", 'uipress-pro');
      Ajax::error($message);
    }

    if ($user['user_email'] == '' || !filter_var($user['user_email'], FILTER_VALIDATE_EMAIL)) {
      $message = __('Email is not valid', 'uipress-pro');
      Ajax::error($message);
    }

    $user_info = get_userdata($user['user_id']);
    $currentemail = $user_info->user_email;

    //CHECK IF SAME EMAIL - IF NOT CHECK IF NEW ONE EXISTS
    if ($currentemail != $user['user_email']) {
      if (email_exists($user['user_email'])) {
        $message = __('Email already exists', 'uipress-pro');
        Ajax::error($message);
      }
    }

    wp_update_user([
      'ID' => $user['user_id'], // this is the ID of the user you want to update.
      'first_name' => $user['first_name'],
      'last_name' => $user['last_name'],
      'role' => '',
      'user_email' => $user['user_email'],
    ]);

    if (isset($user['roles']) && is_array($user['roles'])) {
      $userObj = new \WP_User($user['user_id']);

      foreach ($user['roles'] as $role) {
        $updaterole = is_object($role) ? $role->name : $role;
        $userObj->add_role($updaterole);
      }
    }

    update_user_meta($user['user_id'], 'uip_user_notes', $user['notes']);
    update_user_meta($user['user_id'], 'uip_profile_image', $user['uip_profile_image']);

    if (isset($user['uip_user_group']) && is_array($user['uip_user_group'])) {
      update_user_meta($user['user_id'], 'uip_user_group', $user['uip_user_group']);
    }

    $returnData['message'] = __('User saved', 'uipress-pro');
    wp_send_json($returnData);
  }

  /**
   * Updates role info
   *
   * @since 2.3.5
   */
  public static function uip_update_user_role()
  {
    Ajax::check_referer();
    $newrole = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['role'])));
    $ogrolename = sanitize_text_field($_POST['originalRoleName']);

    if (!current_user_can('edit_users')) {
      $message = __("You don't have sufficent priviledges to manage roles", 'uipress-pro');
      Ajax::error($message);
    }

    if ($ogrolename == '') {
      $message = __('Original role name required', 'uipress-pro');
      Ajax::error($message);
    }

    if (!isset($newrole['label']) || $newrole['label'] == '') {
      $message = __('Role label is required', 'uipress-pro');
      Ajax::error($message);
    }

    $capabilities = [];
    if (is_object($newrole['caps'])) {
      foreach ($newrole['caps'] as $key => $value) {
        if ($value == 'true' || $value == true || $value == 'uiptrue') {
          $capabilities[$key] = true;
        } else {
          $capabilities[$key] = false;
        }
      }
    }

    // Remove original role so we can re-insert the updated one
    remove_role($ogrolename);
    $status = add_role($ogrolename, $newrole['label'], $capabilities);

    if ($status == null) {
      $message = __('Something has gone wrong', 'uipress-pro');
      Ajax::error($message);
    }

    // Update role redirects
    $redirects = UipOptions::get('role_redirects');
    $redirects = is_array($redirects) ? $redirects : [];

    if (isset($newrole['redirect'])) {
      $redirects[$ogrolename] = $newrole['redirect'];
      UipOptions::update('role_redirects', $redirects);
    }

    $returnData['message'] = __('Role updated', 'uipress-pro');
    wp_send_json($returnData);
  }

  /**
   * Log users out everywhere user everywhere
   *
   * @since 2.3.5
   */
  public static function uip_logout_user_everywhere()
  {
    Ajax::check_referer();

    $userid = sanitize_text_field($_POST['userID']);

    if (!current_user_can('edit_users')) {
      $message = __('You don\'t have access to this action', 'uipress-pro');
      Ajax::error($message);
    }

    global $wp_session;
    $user_id = $userid;
    $session = wp_get_session_token();
    $sessions = \WP_Session_Tokens::get_instance($user_id);
    $sessions->destroy_others($session);

    $returnData['message'] = __('User logged out everywhere', 'uipress-pro');
    wp_send_json($returnData);
  }

  /**
   * Updates role info
   *
   * @since 2.3.5
   */
  public static function uip_delete_roles()
  {
    Ajax::check_referer();

    $roles = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['roles'])));

    if (!current_user_can('delete_users')) {
      $message = __("You don't have sufficent priviledges to manage roles", 'uipress-pro');
      Ajax::error($message);
    }

    if (!is_array($roles)) {
      $message = __('Roles are required', 'uipress-pro');
      Ajax::error($message);
    }

    $user = wp_get_current_user();
    $currentRoles = $user->roles;

    $message = __('Roles deleted', 'uipress-pro');
    foreach ($roles as $role) {
      // Don't delete a role currently assigned to self
      if (in_array($role, $currentRoles)) {
        $message = __("Some roles were not deleted. You can't delete a role that is assigned to yourself", 'uipress-pro');
        continue;
      }

      remove_role($role);
    }

    $returnData['message'] = $message;
    wp_send_json($returnData);
  }

  /**
   * Updates role info
   *
   * @since 2.3.5
   */
  public static function uip_add_custom_capability()
  {
    Ajax::check_referer();

    $role = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['role'])));
    $customcap = sanitize_text_field($_POST['customcap']);

    if (!current_user_can('edit_users')) {
      $message = __("You don't have sufficent priviledges to add this capability", 'uipress-pro');
      Ajax::error($message);
    }

    if (!isset($role['name']) || $role['name'] == '') {
      $message = __('Role name is required', 'uipress-pro');
      Ajax::error($message);
    }

    if (!isset($role['label']) || $role['label'] == '') {
      $message = __('Role name is required', 'uipress-pro');
      Ajax::error($message);
    }

    if (strpos($role['name'], ' ') !== false) {
      $message = __('Role name cannot contain spaces', 'uipress-pro');
      Ajax::error($message);
    }

    if (strpos($customcap, ' ') !== false) {
      $message = __('Capability name cannot contain spaces', 'uipress-pro');
      Ajax::error($message);
    }

    $customcap = strtolower($customcap);

    $currentRole = get_role($role['name']);
    $currentRole->add_cap($customcap, true);
    $currentcaps = $currentRole->capabilities;

    remove_role($role['name']);
    $status = add_role($role['name'], $role['label'], $currentcaps);

    if ($status == null) {
      $message = __('Unable to add capability. Make sure capability name is unique', 'uipress-pro');
      Ajax::error($message);
    }

    $returnData['message'] = __('Capability added', 'uipress-pro');
    $returnData['allcaps'] = UserRoles::get_all_role_capabilities();
    wp_send_json($returnData);
  }

  /**
   * Updates role info
   * @since 2.3.5
   */

  public static function uip_remove_custom_capability()
  {
    Ajax::check_referer();

    $role = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['role'])));
    $customcap = sanitize_text_field($_POST['customcap']);

    if (!current_user_can('edit_users')) {
      $message = __("You don't have sufficent priviledges to add this capability", 'uipress-pro');
      Ajax::error($message);
    }

    if (!isset($role['name']) || $role['name'] == '') {
      $message = __('Role name is required', 'uipress-pro');
      Ajax::error($message);
    }

    if (!isset($role['label']) || $role['label'] == '') {
      $message = __('Role name is required', 'uipress-pro');
      Ajax::error($message);
    }

    if (strpos($role['name'], ' ') !== false) {
      $message = __('Role name cannot contain spaces', 'uipress-pro');
      Ajax::error($message);
    }

    $customcap = strtolower($customcap);

    $currentRole = get_role($role['name']);
    $currentRole->remove_cap($customcap, false);
    $currentcaps = $currentRole->capabilities;

    // Remove an re-add role to update
    remove_role($role['name']);
    $status = add_role($role['name'], $role['label'], $currentcaps);

    if ($status == null) {
      $message = __('Unable to delete capability. Make sure role name is unique', 'uipress-pro');
      Ajax::error($message);
    }

    $returnData['message'] = __('Capability deleted', 'uipress-pro');
    $returnData['allcaps'] = UserRoles::get_all_role_capabilities();
    wp_send_json($returnData);
  }

  /**
   * Updates user info
   * @since 2.3.5
   */

  public static function uip_add_new_user()
  {
    Ajax::check_referer();

    $user = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['user'])));

    if (!current_user_can('edit_users')) {
      $message = __("You don't have sufficent priviledges to create users", 'uipress-pro');
      Ajax::error($message);
    }

    // CHECK USERNAME EXISTS
    if (username_exists($user['username'])) {
      $message = __('Username already exists', 'uipress-pro');
      Ajax::error($message);
    }

    // User name is not valid
    if (!validate_username($user['username'])) {
      $message = __('Username is not valid', 'uipress-pro');
      Ajax::error($message);
    }

    // CHECK IF SAME EMAIL - IF NOT CHECK IF NEW ONE EXISTS
    if (email_exists($user['user_email'])) {
      $message = __('Email already exists', 'uipress-pro');
      Ajax::error($message);
    }

    //CHECK IF EMAIL IS VALID
    if (!filter_var($user['user_email'], FILTER_VALIDATE_EMAIL)) {
      $message = __('Email is not valid', 'uipress-pro');
      Ajax::error($message);
    }

    if (!isset($user['password']) || ($user['password'] = '')) {
      $message = __('Password is required', 'uipress-pro');
      Ajax::error($message);
    }

    $user_id = wp_create_user($user['username'], $user['password'], $user['user_email']);

    if (is_wp_error($user_id)) {
      $error_string = $user_id->get_error_message();
      $returnData['error'] = true;
      $returnData['error'] = $error_string;
      wp_send_json($returnData);
    }

    // Insert user
    wp_update_user([
      'ID' => $user_id, // this is the ID of the user you want to update.
      'first_name' => $user['first_name'],
      'last_name' => $user['last_name'],
      'role' => '',
      'user_email' => $user['user_email'],
    ]);

    // Update roles
    if (isset($user['roles']) && is_array($user['roles'])) {
      $userObj = new \WP_User($user_id);

      foreach ($user['roles'] as $role) {
        $updaterole = is_object($role) ? $role->name : $role;
        $userObj->add_role($updaterole);
      }
    }

    if (isset($user['notes'])) {
      update_user_meta($user_id, 'uip_user_notes', $user['notes']);
    }

    if (isset($user['uip_profile_image'])) {
      update_user_meta($user_id, 'uip_profile_image', $user['uip_profile_image']);
    }

    $returnData['message'] = __('User created', 'uipress-pro');
    $returnData['userID'] = $user_id;
    wp_send_json($returnData);
  }

  /**
   * Sends user reset pass
   *
   * @since 2.3.5
   */
  public static function uip_reset_password()
  {
    Ajax::check_referer();

    if (!current_user_can('edit_users')) {
      $message = __("You don't have sufficent priviledges to edit this user", 'uipress-pro');
      Ajax::error($message);
    }

    $IDS = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['IDS'])));

    foreach ($IDS as $userid) {
      $user = get_user_by('id', $userid);
      $username = $user->user_login;
      $status = retrieve_password($username);
    }

    $returnData['message'] = __('Password reset links sent', 'uipress-pro');
    wp_send_json($returnData);
  }

  /**
   * Sends message to given user
   *
   * @since 2.3.5
   */
  public static function uip_send_message()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $message = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['message'])));

    if (!isset($message['subject']) || $message['subject'] == '') {
      $message = __('Subject is required', 'uipress-pro');
      Ajax::error($message);
    }

    if (!isset($message['replyTo']) || $message['replyTo'] == '') {
      $message = __('Reply to email is required', 'uipress-pro');
      Ajax::error($message);
    }

    if (!isset($message['message']) || $message['message'] == '') {
      $message = __('Message is required', 'uipress-pro');
      Ajax::error($message);
    }

    $subject = $message['subject'];
    $content = stripslashes(html_entity_decode($message['message']));
    $replyTo = $message['replyTo'];

    $headers[] = 'From: ' . ' ' . get_bloginfo('name') . '<' . $replyTo . '>';
    $headers[] = 'Reply-To: ' . ' ' . $replyTo;
    $headers[] = 'Content-Type: text/html; charset=UTF-8';

    $wrap = '<table style="box-sizing:border-box;border-color:inherit;text-indent:0;padding:0;margin:64px auto;width:464px"><tbody>';
    $wrapend = '</tbody></table>';
    $formatted = $wrap . $content . $wrapend;

    add_action('wp_mail_failed', ['UipressPro\Classes\Extensions\UserManagement\UserManagementAjax', 'log_uip_mail_error'], 10, 1);

    $allUsers = $message['recipients'];

    foreach ($allUsers as $user) {
      $email = $user->user_email;
      $headers[] = 'Bcc: ' . $email;
    }

    $status = wp_mail($replyTo, $subject, $formatted, $headers);

    if (!$status) {
      $message = __('Unable to send mail at this time', 'uipress-pro');
      Ajax::error($message);
    }

    $returnData['message'] = __('Message sent', 'uipress-pro');
    wp_send_json($returnData);
  }

  public static function log_uip_mail_error($wp_error)
  {
    error_log(json_encode($wp_error));
  }

  /**
   * Deletes users from array of IDS
   *
   * @since 2.3.5
   */
  public static function uip_delete_user()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    if (!current_user_can('delete_users')) {
      $message = __("You don't have sufficent priviledges to delete this user", 'uipress-pro');
      Ajax::error($message);
    }

    $IDS = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['IDS'])));

    foreach ($IDS as $userID) {
      // Don't delete self
      if (get_current_user_id() == $userID) {
        continue;
      }

      $currentID = get_current_user_id();
      wp_delete_user($userID, $currentID);
    }

    $message = __('Users successfully deleted', 'uipress-pro');
    $pluralMessage = __('Users successfully deleted', 'uipress-pro');

    $message = count($IDS) === 1 ? $message : $pluralMessage;
    $returnData['message'] = $message;
    wp_send_json($returnData);
  }

  /**
   * Deletes actions
   *
   * @since 2.3.9
   */
  public static function uip_delete_multiple_actions()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $allIDS = (array) Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['allIDS'])));

    if (!is_array($allIDS)) {
      $message = __('No actions sent to delete!', 'uipress-pro');
      Ajax::error($message);
    }

    if (!current_user_can('delete_posts')) {
      $message = __("You don't have sufficent priviledges to delete these actions", 'uipress-pro');
      Ajax::error($message);
    }

    // Get and prep database
    $database = HistoryApp::uip_history_get_database();
    HistoryApp::uip_history_prep_database($database);

    // Convert the post IDs array to a comma-separated string for use in the SQL query.
    $post_ids_string = implode(',', array_map('intval', $allIDS));

    // Prepare the delete query with the IN operator.
    $delete_query = "DELETE FROM `uip_history` WHERE `ID` IN ({$post_ids_string})";

    // Execute the delete query on the custom database.
    $deleted_rows = $database->query($delete_query);

    $errors = [];

    $returnData['message'] = __('Actions successfully deleted', 'uipress-pro');
    $returnData['undeleted'] = $errors;
    wp_send_json($returnData);
  }

  /**
   * Deletes all history
   *
   * @since 2.3.9
   */
  public static function uip_delete_all_history()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    if (!current_user_can('delete_posts')) {
      $message = __("You don't have sufficent priviledges to delete all actions", 'uipress-pro');
      Ajax::error($message);
    }

    // Get and prep database
    $database = HistoryApp::uip_history_get_database();
    HistoryApp::uip_history_prep_database($database);

    // Prepare the delete query to truncate the posts table.
    $delete_query = 'TRUNCATE `uip_history`';

    // Execute the delete query on the custom database.
    $deleted_rows = $database->query($delete_query);

    // Check the result.
    if ($deleted_rows !== false) {
      $returnData['message'] = __('Actions successfully deleted', 'uipress-pro');
      wp_send_json($returnData);
    } else {
      $message = __('Unable to delete all histroy items', 'uipress-pro');
      Ajax::error($message);
    }
  }
}
