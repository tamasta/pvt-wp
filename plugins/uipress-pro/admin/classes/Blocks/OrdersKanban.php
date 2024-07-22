<?php
namespace UipressPro\Classes\Blocks;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\Utils\Sanitize;

!defined('ABSPATH') ? exit() : '';

class OrdersKanban
{
  /**
   * Returns list of orders for specific order status
   *
   * @since 3.0.97
   */
  public static function get_orders_for_kanban_by_state()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $state = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['state'])));
    $search = sanitize_text_field($_POST['search']);

    $data = self::format_orders_kanban($state, $search);

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['state'] = $data;
    wp_send_json($returndata);
  }

  /**
   * Updates order status
   *
   * @since 3.0.97
   */
  public static function update_order_status()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $id = sanitize_text_field($_POST['orderID']);
    $newStatus = sanitize_text_field($_POST['newStatus']);
    $cancelNotes = sanitize_text_field($_POST['cancelNotes']);

    if (!$id || $id == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('No order given to update', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (!$newStatus || $newStatus == '') {
      $returndata['error'] = true;
      $returndata['message'] = __('No status given to update order to', 'uipress-pro');
      wp_send_json($returndata);
    }

    if (!current_user_can('edit_post', $id)) {
      $returndata['error'] = true;
      $returndata['message'] = __('You don\'t have the correct priveledges to edit this order', 'uipress-pro');
      wp_send_json($returndata);
    }

    $order = wc_get_order($id);
    if ($order) {
      $notes = '';
      if ($newStatus == 'cancelled') {
        $notes = $cancelNotes;
      }
      $order->set_status($newStatus, $notes);
      $order->save();
    } else {
      $returndata['error'] = true;
      $returndata['message'] = __('Unable to locate order', 'uipress-pro');
      wp_send_json($returndata);
    }

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    wp_send_json($returndata);
  }

  /**
   * Returns list of orders for kanban
   *
   * @since 3.0.97
   */
  public static function get_orders_for_kanban()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $states = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['states'])));
    $search = sanitize_text_field($_POST['search']);

    if (!is_plugin_active('woocommerce/woocommerce.php')) {
      $returndata['error'] = true;
      $returndata['message'] = __('Woocommerce is not active on this site', 'uipress-pro');
      wp_send_json($returndata);
    }

    $data = [];
    $data['onHold'] = self::format_orders_kanban($states->onHold, $search);
    $data['pendingPayment'] = self::format_orders_kanban($states->pendingPayment, $search);
    $data['processing'] = self::format_orders_kanban($states->processing, $search);
    $data['completed'] = self::format_orders_kanban($states->completed, $search);

    //Return data to app
    $returndata = [];
    $returndata['success'] = true;
    $returndata['states'] = $data;
    wp_send_json($returndata);
  }

  /**
   * Formats orders for kanban
   *
   * @param string $state
   * @param string $search
   *
   * @since 3.2.0
   */
  private static function format_orders_kanban($state, $search)
  {
    //adds support for search
    add_filter('woocommerce_order_data_store_cpt_get_orders_query', ['UipressPro\Classes\Blocks\OrdersKanban', 'add_wc_search'], 10, 2);

    $limit = $state->page * 10;
    //Get template
    $args = [
      'limit' => $limit,
      'paged' => 1,
      'status' => $state->name,
      'type' => 'shop_order',
      'paginate' => true,
      'uip_order_s' => $search,
      'orderby' => 'date',
      'order' => 'DESC',
      'return' => 'ids',
    ];

    $query = wc_get_orders($args);
    $foundPosts = $query->orders;

    $formattedPosts = [];

    foreach ($foundPosts as $item) {
      $temp = [];
      $order = wc_get_order($item);
      $OID = $order->get_id();

      $author_id = get_post_field('post_author', $OID);
      $user = get_user_by('id', $author_id);
      $username = $user->user_login;

      $firstName = $order->get_billing_first_name();
      $lastName = $order->get_billing_last_name();
      $cusEmail = $order->get_billing_email();
      $orderTitle = '#' . $OID . ' ' . $firstName . ' ' . $lastName;
      $orderID = '#' . $order->get_order_number();
      $customerName = $firstName . ' ' . $lastName;

      $modified = $order->get_date_created();
      $modified = date('U', strtotime($modified));
      $humandate = human_time_diff($modified, strtotime(date('Y-D-M'))) . ' ' . __('ago', 'uipress-pro');

      $post_type_obj = get_post_type_object(get_post_type($OID));

      $temp['name'] = $orderTitle;
      $temp['link'] = get_permalink($OID);
      $temp['editLink'] = get_edit_post_link($OID, '&');
      $temp['modified'] = $humandate;
      $temp['type'] = $post_type_obj->labels->singular_name;
      $temp['author'] = $username;
      $temp['status'] = $order->get_status();
      $temp['total'] = $order->get_formatted_order_total();
      $temp['orderID'] = $orderID;
      $temp['ID'] = $OID;
      $temp['customerName'] = $customerName;
      $temp['img'] = get_avatar_url($cusEmail, ['default' => 'retro']);
      $formattedPosts[] = $temp;
    }

    $data = [];
    $data['page'] = $state->page;
    $data['totalPages'] = $query->max_num_pages;
    $data['found'] = $query->total;
    $data['label'] = $state->label;
    $data['name'] = $state->name;
    $data['orders'] = $formattedPosts;
    $data['color'] = $state->color;

    return $data;
  }

  /**
   * Adds seach to order query
   *
   * @param object $query
   * @param array $query_vars
   *
   * @since 3.2.0
   */
  public static function add_wc_search($query, $query_vars)
  {
    if (!empty($query_vars['uip_order_s'])) {
      $sq = strtolower($query_vars['uip_order_s']);
      $query['meta_query']['relation'] = 'OR';
      $query['meta_query'][] = [
        'key' => '_billing_first_name',
        'value' => esc_attr($sq),
        'compare' => 'LIKE',
      ];
      $query['meta_query'][] = [
        'key' => '_billing_last_name',
        'value' => esc_attr($sq),
        'compare' => 'LIKE',
      ];
      $query['meta_query'][] = [
        'key' => '_billing_email',
        'value' => esc_attr($sq),
        'compare' => 'LIKE',
      ];
    }
    return $query;
  }
}
