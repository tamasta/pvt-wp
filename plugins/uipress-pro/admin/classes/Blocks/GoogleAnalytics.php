<?php
namespace UipressPro\Classes\Blocks;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\Utils\Sanitize;
use UipressLite\Classes\App\UserPreferences;
use UipressLite\Classes\App\UipOptions;

!defined('ABSPATH') ? exit() : '';

class GoogleAnalytics
{
  /**
   * Gets required data for google analytics request
   *
   * @since 3.0.0
   */
  public static function build_query()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $data = UipOptions::get('uip_pro', true);

    $saveAccountToUser = sanitize_text_field($_POST['saveAccountToUser']);

    $google = $saveAccountToUser == 'true' ? UserPreferences::get('google_analytics') : UipOptions::get('google_analytics');

    if (!$data || !isset($data['key'])) {
      $returndata['error'] = true;
      $returndata['message'] = __('You need a licence key to use analytics blocks', 'uipress-pro');
      $returndata['error_type'] = 'no_licence';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    if (!$google || !isset($google['view']) || !isset($google['code'])) {
      $returndata['error'] = true;
      $returndata['message'] = __('You need to connect a google analytics account to display data', 'uipress-pro');
      $returndata['error_type'] = 'no_google';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    $key = $data['key'];
    $instance = $data['instance'];
    $code = $google['code'];
    $view = $google['view'];
    $domain = get_home_url();

    if ($key == '' || $code == '' || $view == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('You need to connect a google analytics account to display data', 'uipress-pro');
      $returndata['error_type'] = 'no_google';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    $token = '';
    if (isset($google['token']) && $google['token'] != '') {
      $token = $google['token'];
    }

    $theQuery = sanitize_url("https://analytics.uipress.co/view.php?code={$code}&view={$view}&key={$key}&instance={$instance}&uip3=1&gafour=true&d={$domain}&uip_token=$token");

    $returndata = [];
    $returndata['success'] = true;
    $returndata['url'] = $theQuery;
    wp_send_json($returndata);
  }

  /**
   * Saves google data account
   *
   * @since 3.0.0
   */
  public static function save_account()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $data = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['analytics'])));
    $saveAccountToUser = sanitize_text_field($_POST['saveAccountToUser']);

    if (!is_object($data)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Inccorrect data passed to server', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (!isset($data->view) || !isset($data->code)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Inccorrect data passed to server', 'uipress-pro');
      wp_send_json($returndata);
    }

    $google = $saveAccountToUser == 'true' ? UserPreferences::get('google_analytics') : UipOptions::get('google_analytics');

    if (!is_array($google)) {
      $google = [];
    }

    $google['view'] = $data->view;
    $google['code'] = $data->code;

    if ($saveAccountToUser == 'true') {
      UserPreferences::update('google_analytics', $google);
    } else {
      UipOptions::update('google_analytics', $google);
    }

    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }

  /**
   * Saves google data access token
   *
   * @since 3.0.0
   */
  public static function save_access_token()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $token = sanitize_text_field($_POST['token']);
    $saveAccountToUser = sanitize_text_field($_POST['saveAccountToUser']);

    if (!$token || $token == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('Inccorrect token sent to server', 'uipress-pro');
      wp_send_json($returndata);
    }

    $google = $saveAccountToUser == 'true' ? UserPreferences::get('google_analytics') : UipOptions::get('google_analytics');

    if (!is_array($google)) {
      $google = [];
    }

    $google['token'] = $token;

    if ($saveAccountToUser == 'true') {
      UserPreferences::update('google_analytics', $google);
    } else {
      UipOptions::update('google_analytics', $google);
    }

    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }

  /**
   * Removes google data account
   *
   * @since 3.0.0
   */
  public static function remove_account()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $saveAccountToUser = sanitize_text_field($_POST['saveAccountToUser']);

    $google = $saveAccountToUser == 'true' ? UserPreferences::get('google_analytics') : UipOptions::get('google_analytics');

    if (!is_array($google)) {
      $google = [];
    }

    $google['view'] = false;
    $google['code'] = false;

    if ($saveAccountToUser == 'true') {
      UserPreferences::update('google_analytics', $google);
    } else {
      UipOptions::update('google_analytics', $google);
    }

    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }
}
