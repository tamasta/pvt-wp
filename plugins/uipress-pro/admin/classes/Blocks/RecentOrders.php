<?php
namespace UipressPro\Classes\Blocks;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\Utils\Sanitize;

!defined('ABSPATH') ? exit() : '';

class RecentOrders
{
  /**
   * Returns list of posts for recent posts blocks
   *
   * @since 3.0.0
   */
  public static function get_recent_orders()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $page = sanitize_option('page_for_posts', $_POST['page']);
    $perPage = sanitize_option('page_for_posts', $_POST['perPage']);
    $string = Sanitize::clean_input_with_code($_POST['searchString']);

    if (!is_plugin_active('woocommerce/woocommerce.php')) {
      $returndata['error'] = true;
      $returndata['message'] = __('Woocommerce is not active on this site', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (!$perPage || $perPage == '') {
      $perPage = 10;
    }

    //Get template
    $args = [
      'limit' => $perPage,
      'paged' => $page,
      'status' => 'any',
      'type' => 'shop_order',
      'paginate' => true,
    ];

    if ($string && $string != '') {
      $args['s'] = $string;
    }

    $query = wc_get_orders($args);
    $foundPosts = $query->orders;

    $formattedPosts = [];

    foreach ($foundPosts as $item) {
      $temp = [];

      $modified = get_the_modified_date('U', $item->ID);
      $humandate = human_time_diff($modified, strtotime(date('Y-D-M'))) . ' ' . __('ago', 'uipress-pro');
      $author_id = get_post_field('post_author', $item->ID);
      $user = get_user_by('id', $author_id);
      $username = $user->user_login;
      $order = wc_get_order($item->ID);
      $firstName = $order->get_billing_first_name();
      $lastName = $order->get_billing_last_name();
      $orderTitle = '#' . $item->ID . ' ' . $firstName . ' ' . $lastName;

      $post_type_obj = get_post_type_object(get_post_type($item->ID));

      $temp['name'] = $orderTitle;
      $temp['link'] = get_permalink($item->ID);
      $temp['editLink'] = get_edit_post_link($item->ID, '&');
      $temp['modified'] = $humandate;
      $temp['type'] = $post_type_obj->labels->singular_name;
      $temp['author'] = $username;
      $temp['status'] = $order->get_status();
      $temp['total'] = $order->get_formatted_order_total();
      $temp['id'] = $item->id;
      $formattedPosts[] = $temp;
    }

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['posts'] = $formattedPosts;
    $returndata['totalPages'] = $query->max_num_pages;
    wp_send_json($returndata);
  }
}
