<?php
namespace UipressPro\Classes\Blocks;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\Utils\Sanitize;
use UipressLite\Classes\App\UserPreferences;
use UipressLite\Classes\App\UipOptions;

!defined('ABSPATH') ? exit() : '';

class MatomoAnalytics
{
  /**
   * Gets required data for matomo analytics request
   *
   * @since 3.0.0
   */
  public static function build_query()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $data = UipOptions::get('uip_pro', true);

    $saveAccountToUser = sanitize_text_field($_POST['saveAccountToUser']);

    $matomo = $saveAccountToUser == 'true' ? UserPreferences::get('matomo_analytics') : UipOptions::get('matomo_analytics');

    if (!$data || !isset($data['key'])) {
      $returndata['error'] = true;
      $returndata['message'] = __('You need a licence key to use analytics blocks', 'uipress-pro');
      $returndata['error_type'] = 'no_licence';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    if (!$matomo || !isset($matomo['siteID']) || !isset($matomo['siteURL']) || !isset($matomo['authToken'])) {
      $returndata['error'] = true;
      $returndata['message'] = __('You need to connect a matomo analytics account to display data', 'uipress-pro');
      $returndata['error_type'] = 'no_matomo';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    $key = $data['key'];
    $instance = $data['instance'];

    $siteID = $matomo['siteID'];
    $siteURL = $matomo['siteURL'];
    $authToken = $matomo['authToken'];

    $domain = get_home_url();

    if ($key == '' || $siteID == '' || $siteURL == '' || $authToken == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('You need to connect a matomo analytics account to display data', 'uipress-pro');
      $returndata['error_type'] = 'no_matomo';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    $token = '';
    if (isset($matomo['token']) && $matomo['token'] != '') {
      $token = $matomo['token'];
    }

    $theQuery = sanitize_url("https://analytics.uipress.co/matomo/v2/?code={$authToken}&view={$siteURL}&siteid={$siteID}&key={$key}&instance={$instance}&d={$domain}&uip_token={$token}");

    $returndata = [];
    $returndata['success'] = true;
    $returndata['url'] = $theQuery;
    $returndata['matomoPath'] = $siteURL;
    wp_send_json($returndata);
  }

  /**
   * Saves matomo data
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

    if (!isset($data->siteID) || !isset($data->url) || !isset($data->authToken)) {
      $returndata['error'] = true;
      $returndata['message'] = __('Missing data required to connect', 'uipress-pro');
      wp_send_json($returndata);
    }

    $matomo = $saveAccountToUser == 'true' ? UserPreferences::get('matomo_analytics') : UipOptions::get('matomo_analytics');

    if (!is_array($matomo)) {
      $matomo = [];
    }

    $matomo['siteID'] = $data->siteID;
    $matomo['siteURL'] = $data->url;
    $matomo['authToken'] = $data->authToken;

    if ($saveAccountToUser == 'true') {
      UserPreferences::update('matomo_analytics', $matomo);
    } else {
      UipOptions::update('matomo_analytics', $matomo);
    }

    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }

  /**
   * Saves matomo access token
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

    $matomo = $saveAccountToUser == 'true' ? UserPreferences::get('matomo_analytics') : UipOptions::get('matomo_analytics');

    if (!is_array($matomo)) {
      $matomo = [];
    }

    $matomo['token'] = $token;

    if ($saveAccountToUser == 'true') {
      UserPreferences::update('matomo_analytics', $matomo);
    } else {
      UipOptions::update('matomo_analytics', $matomo);
    }

    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }

  /**
   * Removes matomo account
   *
   * @since 3.0.0
   */
  public static function remove_account()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $saveAccountToUser = sanitize_text_field($_POST['saveAccountToUser']);

    $matomo = [];

    $matomo['siteID'] = false;
    $matomo['siteURL'] = false;
    $matomo['authToken'] = false;

    if ($saveAccountToUser == 'true') {
      UserPreferences::update('matomo_analytics', $matomo);
    } else {
      UipOptions::update('matomo_analytics', $matomo);
    }

    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }
}
