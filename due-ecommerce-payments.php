<?php
/**
 * Plugin Name: Due.com E-Commerce Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/duecom-e-commerce-payment-gateway/
 * Description: This plugin adds a payment option in WooCommerce for customers to pay with their Credit Cards Via Due Payments. Visit Due.com for more information.
 * Version: 1.2.4
 * Author: Due
 * Author URI: https://due.com/
 * Author Email: chalmers@due.com
 * License: GPL2
 */

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
define('ROOT_PLUGIN_PATH_WITH_SLASH', plugin_dir_path( __FILE__ ));

function wpdp_due_payments_init() {
    include_once(ROOT_PLUGIN_PATH_WITH_SLASH."lib/Due/init.php");

    function wpdp_add_due_payments_gateway( $methods ) {
        $methods[] = 'WC_Due_Gateway';
        return $methods;
    }
    add_filter( 'woocommerce_payment_gateways', 'wpdp_add_due_payments_gateway' );

	// If the parent WC_Payment_Gateway class doesn't exist
	// it means WooCommerce is not installed on the site
	// so do nothing
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

	// If we made it this far, then include our Gateway Class
    include(ROOT_PLUGIN_PATH_WITH_SLASH."lib/wc-due-gateway.php");
}
// Include our Gateway Class and register Payment Gateway with WooCommerce
add_action( 'plugins_loaded', 'wpdp_due_payments_init', 0 );

function due_woocommerce_addon_activate() {

    if(!function_exists('curl_exec'))
    {
        wp_die( '<pre>This plugin requires PHP CURL library installled in order to be activated </pre>' );
    }
}
register_activation_hook( __FILE__, 'due_woocommerce_addon_activate' );

// Add custom action links
function wpdp_due_payments_action_links( $links ) {
	$plugin_links = array(
		'<a href="' . admin_url( 'admin.php?page=wc-settings&tab=checkout&section=wpdp_due_payments' ) . '">' . __( 'Settings' ) . '</a>',
	);

	// Merge our new link with the default ones
	return array_merge( $plugin_links, $links );
}
$plugin = plugin_basename( __FILE__ );
add_filter( 'plugin_action_links_' . $plugin, 'wpdp_due_payments_action_links' );
