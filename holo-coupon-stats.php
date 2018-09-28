<?php 
 
/*
Plugin Name: Woocomerce Coupon Usage Export by Holocron
Plugin URI:  http://holocron.it/plugins/holo-coupon-stats
Description: This plugin permit to export, in csv forma ,the list of orders where a specific coupon code it was used. 
Version:     0.1
Author:      Holocron
Author URI:  http://holocron.it

Domain Path: /languages
Text Domain: holo-coupon-stats
*/

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define('HOCS_AJAX_ACTION','hocs-download-coupon-report');



//Textdomain
load_plugin_textdomain('holo-coupon-stats', false, basename( dirname( __FILE__ ) ) . '/languages' );

/**
 * 
 * Check if WooCommerce is activated
 * 
 */
if ( ! function_exists( 'hocs_is_woocommerce_activated' ) ) {
  function hocs_is_woocommerce_activated() {
    if ( class_exists( 'woocommerce' ) ) { return true; } else { return false; }
  }
}


/**
 *
 * If WC is loaded add hook for coupon list page
 *
 */
// Add the custom columns to the shop_coupon post type:
add_filter( 'manage_shop_coupon_posts_columns', 'hocs_set_custom_edit_shop_coupon_columns',999 );
// Add the data to the custom columns for the shop_coupon post type:
add_action( 'manage_shop_coupon_posts_custom_column' , 'hocs_custom_shop_coupon_column', 999, 2 );




/**
 *
 * Add Column to Coupon List Page
 *
 */
function hocs_set_custom_edit_shop_coupon_columns($columns) {
  $columns['download_report'] = __( 'Report', 'holo-coupon-stats' );
  return $columns;
}



/**
 *
 *  Set content of new column
 *
 */
function hocs_custom_shop_coupon_column( $column, $coupon_id ) {
  switch ( $column ) {
      case 'download_report' :
          $downloadUrl = admin_url('/admin-ajax.php').'?'.http_build_query( array( 'action' => HOCS_AJAX_ACTION, 'coupon_id' => $coupon_id ) );
          echo '<a target="_blank" class="button button-primary" href="'.$downloadUrl.'">'.__( 'Download', 'holo-coupon-stats' ).'</a>';
              
          break;
  }
}



/**
 *
 *  CSV generator Ajax endpoint
 *
 */
add_action( 'wp_ajax_'.HOCS_AJAX_ACTION , 'hocs_ajax_download_handler',999 );

function hocs_ajax_download_handler() {
    global $wpdb;

    if( empty($_REQUEST['coupon_id']) ) wp_die(__('No Coupon ID provided', 'holo-coupon-stats' ));

    $coupon = new WC_Coupon( intval($_REQUEST['coupon_id']) );
    // var_dump($coupon->get_code());

    $tableName = "ops2k18_woocommerce_order_items";

    $queryData = $wpdb->get_results( 
                      "
                        SELECT i1.*, i2.*
                        FROM ".$wpdb->prefix."woocommerce_order_items AS i1 
                          JOIN ".$wpdb->prefix."woocommerce_order_items AS i2 
                            ON i1.order_id = i2.order_id 
                        WHERE i1.order_item_name = '".$coupon->get_code()."' AND i1.order_item_type = 'coupon' AND i2.order_item_type = 'line_item'
                      "
                  );
    
    
    $exportData = array();

    foreach ($queryData as $i => $item) {
      
      if( !isset( $exportData[$item->order_id] ) ){

        $t = array();
        $t['order'] = new WC_Order( $item->order_id );
        $t['items'] = array();
        $exportData[$item->order_id] = $t;

      }

      $exportData[$item->order_id]['items'][] = $item->order_item_name;

    }

    $CSVdata = "";
    $CSVdata .= "Order ID,Completed Date,Client,Order Total,N° Products,Products \n";
    foreach ($exportData as $order_id => $data) {
      $order = $data['order'];
      $CSVdata .= $order_id.",".$order->get_date_completed().",".$order->get_billing_first_name().' '.$order->get_billing_last_name().",".$order->get_total().",".count($data['items']).",".join(' - ',$data['items'])."\n";
    }


    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="Coupon_report_'.$coupon->get_code().'.csv"');
    echo $CSVdata;
    wp_die();
    
    
}


?>