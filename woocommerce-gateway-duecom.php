<?php
/**
 * Plugin Name: Due.com E-Commerce Payment Gateway
 * Plugin URI: https://wordpress.org/plugins/duecom-e-commerce-payment-gateway/
 * Description: Provides a Payment Gateway via Due.com for WooCommerce.
 * Version: 1.2.4
 * Author: Due
 * Author URI: https://due.com/
 * Author Email: chalmers@due.com
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly


class WC_Due_Payments {
	/**
	 * Constructor
	 */
	public function __construct() {
		// Actions
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array(
			$this,
			'plugin_action_links'
		) );

		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );
		add_filter( 'woocommerce_payment_gateways', array(
			$this,
			'register_gateway'
		) );
	}

	/**
	 * Add relevant links to plugins page
	 *
	 * @param  array $links
	 *
	 * @return array
	 */
	public function plugin_action_links( $links ) {
		$plugin_links = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=wc-settings&tab=checkout&section=duecom' ) ) . '">' . __( 'Settings', 'woocommerce-gateway-duecom' ) . '</a>'
		);

		return array_merge( $plugin_links, $links );
	}

	/**
	 * Init localisations and files
	 */
	public function init() {
		if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
			return;
		}

		// Localization
		load_plugin_textdomain( 'woocommerce-gateway-duecom', FALSE, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

		// Includes
		include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-duecom.php' );
	}

	/**
	 * Register the gateways for use
	 */
	public function register_gateway( $methods ) {
		$methods[] = 'WC_Gateway_Duecom';

		return $methods;
	}

	/**
	 * Add Scripts
	 */
	public function add_scripts() {
		wp_enqueue_style( 'wc-gateway-duecom', plugins_url( '/assets/css/style.css', __FILE__ ), array(), FALSE, 'all' );
	}
}

new WC_Due_Payments();

