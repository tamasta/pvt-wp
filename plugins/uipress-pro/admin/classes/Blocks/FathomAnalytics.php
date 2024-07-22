<?php
namespace UipressPro\Classes\Blocks;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\Utils\Sanitize;
use UipressLite\Classes\App\UserPreferences;
use UipressLite\Classes\App\UipOptions;

!defined('ABSPATH') ? exit() : '';

class FathomAnalytics
{
  /**
   * Gets required data for fathom analytics request
   *
   * @since 3.0.0
   */
  public static function build_query()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $data = UipOptions::get('uip_pro', true);

    $saveAccountToUser = sanitize_text_field($_POST['saveAccountToUser']);

    if ($saveAccountToUser == 'true') {
      $fathom = UserPreferences::get('fathom_analytics');
    } else {
      $fathom = UipOptions::get('fathom_analytics');
    }

    if (!$data || !isset($data['key'])) {
      $returndata['error'] = true;
      $returndata['message'] = __('You need a licence key to use analytics blocks', 'uipress-pro');
      $returndata['error_type'] = 'no_licence';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    if (!$fathom || !isset($fathom['siteID']) || !isset($fathom['authToken'])) {
      $returndata['error'] = true;
      $returndata['message'] = __('You need to connect a fathom analytics account to display data', 'uipress-pro');
      $returndata['error_type'] = 'no_fathom';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    $key = $data['key'];
    $instance = $data['instance'];

    $siteID = $fathom['siteID'];
    $authToken = $fathom['authToken'];

    $domain = get_home_url();

    if ($key == '' || $siteID == '' || $authToken == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('You need to connect a fathom analytics account to display data', 'uipress-pro');
      $returndata['error_type'] = 'no_fathom';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    $token = '';
    if (isset($fathom['token']) && $fathom['token'] != '') {
      $token = $fathom['token'];
    }

    $theQuery = sanitize_url("https://analytics.uipress.co/fathom/?code={$authToken}&siteid={$siteID}&key={$key}&instance={$instance}&d={$domain}&uip_token={$token}");

    $returndata = [];
    $returndata['success'] = true;
    $returndata['url'] = $theQuery;
    $returndata['fathomPath'] = '';
    wp_send_json($returndata);
  }

  /**
   * Saves fathom data account
   *
   * @since 3.2.05
   */
  public static function save_account()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $data = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['analytics'])));
    $saveAccountToUser = sanitize_text_field($_POST['saveAccountToUser']);

    if (!is_object($data)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Missing data required to connect', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (!isset($data->siteID) || !isset($data->authToken)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Missing data required to connect', 'uipress-pro');
      wp_send_json($returndata);
    }

    $fathom = $saveAccountToUser == 'true' ? UserPreferences::get('fathom_analytics') : UipOptions::get('fathom_analytics');

    if (!is_array($fathom)) {
      $fathom = [];
    }

    $fathom['siteID'] = $data->siteID;
    $fathom['authToken'] = $data->authToken;

    if ($saveAccountToUser == 'true') {
      UserPreferences::update('fathom_analytics', $fathom);
    } else {
      UipOptions::update('fathom_analytics', $fathom);
    }

    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }

  /**
   * Saves fathom data
   *
   * @since 3.1.05
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

    $fathom = $saveAccountToUser == 'true' ? UserPreferences::get('fathom_analytics') : UipOptions::get('fathom_analytics');

    if (!is_array($fathom)) {
      $fathom = [];
    }

    $fathom['token'] = $token;

    if ($saveAccountToUser == 'true') {
      UserPreferences::update('fathom_analytics', $fathom);
    } else {
      UipOptions::update('fathom_analytics', $fathom);
    }

    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }

  /**
   * Removes fathom account
   * @since 3.1.05
   */
  public static function remove_account()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $saveAccountToUser = sanitize_text_field($_POST['saveAccountToUser']);

    $fathom = $saveAccountToUser == 'true' ? UserPreferences::get('fathom_analytics') : UipOptions::get('fathom_analytics');

    if (!is_array($fathom)) {
      $fathom = [];
    }

    $fathom['siteID'] = false;
    $fathom['authToken'] = false;

    if ($saveAccountToUser == 'true') {
      UserPreferences::update('fathom_analytics', $fathom);
    } else {
      UipOptions::update('fathom_analytics', $fathom);
    }

    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }
}
