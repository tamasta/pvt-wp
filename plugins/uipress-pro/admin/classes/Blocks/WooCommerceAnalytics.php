<?php
namespace UipressPro\Classes\Blocks;
use UipressLite\Classes\Utils\Ajax;
use UipressLite\Classes\Utils\Sanitize;
use UipressLite\Classes\App\UipOptions;

!defined('ABSPATH') ? exit() : '';

class WooCommerceAnalytics
{
  /**
   * Get's top products
   *
   * @param object $order
   * @param array $topProducts
   *
   * @since 3.2.0
   */
  private static function get_top_products($order, $topProducts)
  {
    $items = $order->get_items();
    foreach ($items as $item) {
      $product_id = $item->get_product_id();
      $product_name = $item->get_name();
      $product_total = $item->get_total();
      $product_quantity = $item->get_quantity();
      if (!isset($topProducts[$product_id])) {
        $topProducts[$product_id] = [
          'name' => $product_name,
          'total' => 0,
          'total_sold' => 0,
          'edit_url' => get_edit_post_link($product_id, '&'),
        ];
      }
      $topProducts[$product_id]['total'] += $product_total;
      $topProducts[$product_id]['total_sold'] += $product_quantity;
      $topProducts[$product_id]['total'] = round($topProducts[$product_id]['total'], 2);

      //$total_sales += $product_total;
    }

    return $topProducts;
  }

  /**
   * Get's country data
   *
   * @since 3.2.0
   */
  private static function get_country_data($order, $processed, $status, $orderTotal)
  {
    $billing_country = $order->get_billing_country();

    if ($billing_country) {
      if (!isset($processed[$billing_country])) {
        $processed[$billing_country] = [];

        $processed[$billing_country]['total_orders'] = [];
        $processed[$billing_country]['total_orders']['label'] = __('orders', 'uipress-pro');
        $processed[$billing_country]['total_orders']['total'] = 0;

        $processed[$billing_country]['failed_orders'] = [];
        $processed[$billing_country]['failed_orders']['label'] = __('failed', 'uipress-pro');
        $processed[$billing_country]['failed_orders']['total'] = 0;

        $processed[$billing_country]['refunded_orders'] = [];
        $processed[$billing_country]['refunded_orders']['label'] = __('refunds', 'uipress-pro');
        $processed[$billing_country]['refunded_orders']['total'] = 0;

        $processed[$billing_country]['total_revenue'] = [];
        $processed[$billing_country]['total_revenue']['label'] = __('revenue', 'uipress-pro');
        $processed[$billing_country]['total_revenue']['total'] = 0;
      }

      $processed[$billing_country]['total_orders']['total'] += 1;

      $total = round($processed[$billing_country]['total_revenue']['total'] + $orderTotal, 2);
      $processed[$billing_country]['total_revenue']['total'] = $total;
      //
      //Failed orders
      if ($status == 'wc-failed') {
        $processed[$billing_country]['failed_orders']['total'] += 1;
      }
      //
      //Refunded orders
      if ($status == 'wc-refunded') {
        $processed[$billing_country]['refunded_orders']['total'] += 1;
      }
    }

    return $processed;
  }

  /**
   * Processes country data for orders
   *
   * @param array $processed
   * @param array $comp
   *
   * @since 3.2.0
   */
  private static function process_country_data($processed, $comp)
  {
    //do stuff with comparison data
    foreach ($processed as $key => $value) {
      if (isset($comp[$key])) {
        $processed[$key]['total_orders']['total_comp'] = $comp[$key]['total_orders']['total'];
        $processed[$key]['failed_orders']['total_comp'] = $comp[$key]['failed_orders']['total'];
        $processed[$key]['refunded_orders']['total_comp'] = $comp[$key]['refunded_orders']['total'];
        $processed[$key]['total_revenue']['total_comp'] = $comp[$key]['total_revenue']['total'];
      } else {
        $processed[$key]['total_orders']['total_comp'] = 0;
        $processed[$key]['failed_orders']['total_comp'] = 0;
        $processed[$key]['refunded_orders']['total_comp'] = 0;
        $processed[$key]['total_revenue']['total_comp'] = 0;
      }
      //Order stats
      $prev = $processed[$key]['total_orders']['total_comp'];
      $current = $processed[$key]['total_orders']['total'];
      $processed[$key]['total_orders']['change'] = 0;
      if ($prev != 0 && $current != 0) {
        $processed[$key]['total_orders']['change'] = round((($current - $prev) / $prev) * 100, 2);
      }
      //Failed orders
      $prev = $processed[$key]['failed_orders']['total_comp'];
      $current = $processed[$key]['failed_orders']['total'];
      $processed[$key]['failed_orders']['change'] = 0;
      if ($prev != 0 && $current != 0) {
        $processed[$key]['failed_orders']['change'] = round((($current - $prev) / $prev) * 100, 2);
      }
      //Refunded orders
      $prev = $processed[$key]['refunded_orders']['total_comp'];
      $current = $processed[$key]['refunded_orders']['total'];
      $processed[$key]['refunded_orders']['change'] = 0;
      if ($prev != 0 && $current != 0) {
        $processed[$key]['refunded_orders']['change'] = round((($current - $prev) / $prev) * 100, 2);
      }
      //Refunded orders
      $prev = $processed[$key]['total_revenue']['total_comp'];
      $current = $processed[$key]['total_revenue']['total'];
      $processed[$key]['total_revenue']['change'] = 0;
      if ($prev != 0 && $current != 0) {
        $processed[$key]['total_revenue']['change'] = round((($current - $prev) / $prev) * 100, 2);
      }
    }

    return $processed;
  }

  /**
   * Process total change for report
   *
   * @param array $data
   * @param array $compData
   *
   * @since 3.2.0
   */
  private static function process_total_change($data, $compData)
  {
    $totalChange = [];

    if ($compData['total_orders'] == 0) {
      $totalChange['total_orders'] = 0;
    } else {
      $diff = $data['total_orders'] - $compData['total_orders'];
      $totalChange['total_orders'] = round(($diff / $compData['total_orders']) * 100, 2);
    }
    if ($compData['total_revenue'] == 0) {
      $totalChange['total_revenue'] = 0;
    } else {
      $diff = $data['total_revenue'] - $compData['total_revenue'];
      $totalChange['total_revenue'] = round(($diff / $compData['total_revenue']) * 100, 2);
    }
    if ($compData['failed_orders'] == 0) {
      $totalChange['failed_orders'] = 0;
    } else {
      $diff = $data['failed_orders'] - $compData['failed_orders'];
      $totalChange['failed_orders'] = round(($diff / $compData['failed_orders']) * 100, 2);
    }
    if ($compData['refunded_orders'] == 0) {
      $totalChange['refunded_orders'] = 0;
    } else {
      $diff = $data['refunded_orders'] - $compData['refunded_orders'];
      $totalChange['refunded_orders'] = round(($diff / $compData['refunded_orders']) * 100, 2);
    }

    return $totalChange;
  }

  /**
   * Returns totals for reports
   *
   * @since 3.2.0
   */
  public static function return_totals($data)
  {
    $totals = [];
    $totals['total_orders'] = 0;
    $totals['total_revenue'] = 0;
    $totals['failed_orders'] = 0;
    $totals['refunded_orders'] = 0;

    foreach ($data as $key => $value) {
      $totals['total_orders'] += $value['total_orders'];
      $totals['total_revenue'] += $value['total_revenue'];
      $totals['failed_orders'] += $value['failed_orders'];
      $totals['refunded_orders'] += $value['refunded_orders'];
    }

    return $totals;
  }

  /**
   * Returns data array
   *
   * @since 3.0.7
   */
  private static function return_date_array($start, $end)
  {
    $format = get_option('date_format');
    $period = new \DatePeriod(new \DateTime($start), new \DateInterval('P1D'), new \DateTime($end));
    $dates = [];
    foreach ($period as $key => $value) {
      $dates[] = $value->format($format);
    }
    $dates[] = date($format, strtotime($end));
    return $dates;
  }

  /**
   * Takes orders and formats by date
   *
   * @since 3.0.7
   */
  private static function format_timeline_data($foundOrders, $dateArray)
  {
    $formattedByDate = [];
    $countryData = [];
    $topProducts = [];
    $format = get_option('date_format');
    //Format array before looping orders
    foreach ($dateArray as $date) {
      $formattedByDate[$date] = [];
      $formattedByDate[$date]['total_orders'] = 0;
      $formattedByDate[$date]['total_revenue'] = 0;
      $formattedByDate[$date]['failed_orders'] = 0;
      $formattedByDate[$date]['refunded_orders'] = 0;
    }
    //Loop orders
    foreach ($foundOrders as $order) {
      $timestamp = get_post_timestamp($order->ID);
      $order_date = date($format, $timestamp);
      $status = get_post_status($order->ID);
      //
      //Get total orders
      $formattedByDate[$order_date]['total_orders'] += 1;

      //
      //Total revenue
      $order = wc_get_order($order);
      $orderTotal = $order->get_total();
      $total = round($formattedByDate[$order_date]['total_revenue'] + $orderTotal, 2);
      $formattedByDate[$order_date]['total_revenue'] = $total;

      //
      //Failed orders
      if ($status == 'wc-failed') {
        $formattedByDate[$order_date]['failed_orders'] += 1;
      }
      //
      //Refunded orders
      if ($status == 'wc-refunded') {
        $formattedByDate[$order_date]['refunded_orders'] += 1;
      }

      $countryData = self::get_country_data($order, $countryData, $status, $orderTotal);
      if ($status != 'wc-refunded' && $status != 'wc-failed') {
        $topProducts = self::get_top_products($order, $topProducts);
      }
    }

    $noDate = [];
    foreach ($formattedByDate as $key => $value) {
      $value['date'] = $key;
      $noDate[] = $value;
    }

    // Sort the top products by total sales
    usort($topProducts, function ($a, $b) {
      return $b['total'] - $a['total'];
    });

    // Limit the top products to the top 3
    $topProductsRevenue = array_slice($topProducts, 0, 10);

    usort($topProducts, function ($a, $b) {
      return $b['total_sold'] - $a['total_sold'];
    });

    // Limit the top products to the top 3
    $topProductsQuantity = array_slice($topProducts, 0, 10);

    $data = [];
    $data['timeline'] = $noDate;
    $data['map_data'] = $countryData;
    $data['top_products_revenue'] = $topProductsRevenue;
    $data['top_products_quantity'] = $topProductsQuantity;
    return $data;
  }

  /**
   * Run woocommerce report
   *
   * @since 3.0.7
   */
  public static function run_woocommerce_analytics_query()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $dates = Sanitize::clean_input_with_code(json_decode(stripslashes($_POST['dates'])));

    //Get template
    $args = [
      'limit' => -1,
      'status' => 'any',
      'type' => 'shop_order',
      'paginate' => false,
      'date_created' => $dates->startDate . '...' . $dates->endDate,
    ];

    $foundOrders = wc_get_orders($args);

    $masterData['timeline'] = [];
    $masterData['timeline']['report'] = [];
    $masterData['timeline']['report']['dates'] = self::return_date_array($dates->startDate, $dates->endDate);
    $data = self::format_timeline_data($foundOrders, $masterData['timeline']['report']['dates']);
    $masterData['timeline']['report']['data'] = $data['timeline'];
    $masterData['timeline']['report']['totals'] = self::return_totals($masterData['timeline']['report']['data']);

    //Geta all the comparison order data
    $args = [
      'limit' => -1,
      'status' => 'any',
      'type' => 'shop_order',
      'paginate' => false,
      'date_created' => $dates->startDateCom . '...' . $dates->endDateCom,
    ];

    $foundOrdersComp = wc_get_orders($args);

    $masterData['timeline']['report_comparison'] = [];
    $masterData['timeline']['report_comparison']['dates'] = self::return_date_array($dates->startDateCom, $dates->endDateCom);

    $compData = self::format_timeline_data($foundOrdersComp, $masterData['timeline']['report_comparison']['dates']);
    $masterData['timeline']['report_comparison']['data'] = $compData['timeline'];
    $masterData['timeline']['report_comparison']['totals'] = self::return_totals($masterData['timeline']['report_comparison']['data']);

    $masterData['timeline']['report']['totals_change'] = self::process_total_change($masterData['timeline']['report']['totals'], $masterData['timeline']['report_comparison']['totals']);

    $masterData['map_data'] = self::process_country_data($data['map_data'], $compData['map_data']);

    $masterData['top_products_revenue'] = $data['top_products_revenue'];
    $masterData['top_products_quantity'] = $data['top_products_quantity'];

    $masterData['currency'] = html_entity_decode(get_woocommerce_currency_symbol());
    $masterData['currency_pos'] = get_option('woocommerce_currency_pos');

    $format = get_option('date_format');
    $masterData['start_date'] = date($format, strtotime($dates->startDate));
    $masterData['end_date'] = date($format, strtotime($dates->endDate));

    $returndata = [];
    $returndata['success'] = true;
    $returndata['data'] = $masterData;
    wp_send_json($returndata);
  }

  /**
   * Gets required data for woocommerce analytics request
   *
   * @since 3.0.7
   */
  public static function build_woocommerce_analytics_query()
  {
    // Check security nonce and 'DOING_AJAX' global
    Ajax::check_referer();

    $data = UipOptions::get('uip_pro', true);

    if (!$data || !isset($data['key'])) {
      $returndata['error'] = true;
      $returndata['message'] = __('You need a licence key to use analytics blocks', 'uipress-pro');
      $returndata['error_type'] = 'no_licence';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    if (!is_plugin_active('woocommerce/woocommerce.php')) {
      $returndata['error'] = true;
      $returndata['message'] = __('Woocommerce needs to be active on this site to use these blocks', 'uipress-pro');
      $returndata['error_type'] = 'no_woocommerce';
      $returndata['url'] = false;
      wp_send_json($returndata);
    }

    $returndata = [];
    $returndata['success'] = true;
    $returndata['url'] = true;
    wp_send_json($returndata);
  }
}
