<?php
namespace UipressPro\Classes\Blocks;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\Utils\Sanitize;

!defined('ABSPATH') ? exit() : '';

class ShortCode
{
  /**
   * Runs and returns given shortcode
   *
   * @since 3.0.96
   */
  public static function get_shortcode()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $shortCode = stripslashes(sanitize_text_field($_POST['shortCode']));
    $isMultisite = stripslashes(sanitize_text_field($_POST['isMultisite']));

    if (!$shortCode) {
      $message = __('Unable to run shortcode', 'uipress-pro');
      $returndata['error'] = true;
      $returndata['message'] = $message;
      wp_send_json($returndata);
    }

    // Switch to main blog to run query
    if ($isMultisite == 'true') {
      $mainSiteId = get_main_site_id();
      switch_to_blog($mainSiteId);
    }

    ob_start();

    echo do_shortcode($shortCode);

    $code = ob_get_clean();

    if ($isMultisite == 'true') {
      restore_current_blog();
    }

    $returndata = [];
    $returndata['shortCode'] = $code;
    $returndata['success'] = true;
    wp_send_json($returndata);
  }
}
