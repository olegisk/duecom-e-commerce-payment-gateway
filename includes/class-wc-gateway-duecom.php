<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
} // Exit if accessed directly

require_once dirname( __FILE__ ) . '/../vendor/autoload.php';

class WC_Gateway_Duecom extends WC_Payment_Gateway_CC {
	/**
	 * Init
	 */
	public function __construct() {
		$this->id                 = 'duecom';
		$this->has_fields         = TRUE;
		$this->method_title       = __( 'Due.com Payments', 'woocommerce-gateway-duecom' );
		$this->method_description = '';
		$this->new_method_label   = __( 'Use a new card', 'woocommerce-gateway-duecom' );

		$this->icon     = apply_filters( 'woocommerce_duecom_icon', plugins_url( '/assets/images/due.png', dirname( __FILE__ ) ) );
		$this->supports = array(
			'default_credit_card_form',
			'products',
			'refunds',
			'tokenization',
			'subscriptions',
			'subscription_cancellation',
			'subscription_suspension',
			'subscription_reactivation',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			//'multiple_subscriptions',
		);

		// Load the form fields.
		$this->init_form_fields();

		// Load the settings.
		$this->init_settings();

		// Define variables
		$this->enabled         = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'no';
		$this->title           = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Pay with Credit Card', 'woocommerce-gateway-duecom' );
		$this->description     = isset( $this->settings['description'] ) ? $this->settings['description'] : '';
		$this->app_id          = isset( $this->settings['app_id'] ) ? $this->settings['app_id'] : '';
		$this->api_key         = isset( $this->settings['api_key'] ) ? $this->settings['api_key'] : '';
		$this->app_id_sandbox  = isset( $this->settings['app_id_sandbox'] ) ? $this->settings['app_id_sandbox'] : '';
		$this->api_key_sandbox = isset( $this->settings['api_key_sandbox'] ) ? $this->settings['api_key_sandbox'] : '';
		$this->sandbox         = isset( $this->settings['sandbox'] ) ? $this->settings['sandbox'] : 'no';
		$this->store_cards     = isset( $this->settings['store_cards'] ) ? $this->settings['store_cards'] : 'yes';

		// Actions
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array(
			$this,
			'process_admin_options'
		) );

		add_action( 'woocommerce_thankyou_' . $this->id, array(
			$this,
			'thankyou_page'
		) );

		add_action( 'wp_enqueue_scripts', array( $this, 'add_scripts' ) );

		// Payment listener/API hook
		add_action( 'woocommerce_api_wc_gateway_' . $this->id, array(
			$this,
			'return_handler'
		) );

		// WC Subscriptions
		add_action( 'woocommerce_payment_complete', array(
			&$this,
			'add_subscription_token_id'
		), 10, 1 );

		add_action( 'woocommerce_scheduled_subscription_payment_' . $this->id, array(
			$this,
			'scheduled_subscription_payment'
		), 10, 2 );

		add_action( 'woocommerce_subscription_failing_payment_method_updated_' . $this->id, array(
			$this,
			'update_failing_payment_method'
		), 10, 2 );

		add_action( 'wcs_resubscribe_order_created', array(
			$this,
			'delete_resubscribe_meta'
		), 10 );

		// Allow store managers to manually set card id as the payment method on a subscription
		add_filter( 'woocommerce_subscription_payment_meta', array(
			$this,
			'add_subscription_payment_meta'
		), 10, 2 );

		add_filter( 'woocommerce_subscription_validate_payment_meta', array(
			$this,
			'validate_subscription_payment_meta'
		), 10, 2 );

		// Display the credit card used for a subscription in the "My Subscriptions" table
		add_filter( 'woocommerce_my_subscriptions_payment_method', array(
			$this,
			'maybe_render_subscription_payment_method'
		), 10, 2 );
	}

	/**
	 * Initialise Settings Form Fields
	 * @return void
	 */
	public function init_form_fields() {
		$this->form_fields = array(
			'enabled'         => array(
				'title'   => __( 'Enable/Disable', 'woocommerce-gateway-duecom' ),
				'type'    => 'checkbox',
				'label'   => __( 'Enable Due.com Payment Module', 'woocommerce-gateway-duecom' ),
				'default' => 'no'
			),
			'title'           => array(
				'title'       => __( 'Title', 'woocommerce-gateway-duecom' ),
				'type'        => 'text',
				'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-duecom' ),
				'default'     => __( 'Pay with Credit Card', 'woocommerce-gateway-duecom' )
			),
			'description'     => array(
				'title'       => __( 'Description', 'woocommerce-gateway-duecom' ),
				'type'        => 'text',
				'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-duecom' ),
				'default'     => 'Pay securely using your credit card',
			),
			'app_id'          => array(
				'title'       => __( 'Live App Id', 'woocommerce-gateway-duecom' ),
				'type'        => 'text',
				'desc_tip'    => __( 'Your App Id will be sent to you after your account has been approved.', 'woocommerce-gateway-duecom' ),
				'description' => __( 'Go to our <a href="https://due.com/blog/request-access-use-due-payment-gateway-woocommerce/" target="_blank">tutorial on requesting access</a> for more info.', 'woocommerce-gateway-duecom' ),
			),
			'api_key'         => array(
				'title'    => __( 'Live API Key', 'woocommerce-gateway-duecom' ),
				'type'     => 'text',
				'desc_tip' => __( 'Go to the API section of your Due Settings to obtain your Live API key.', 'woocommerce-gateway-duecom' ),
			),
			'app_id_sandbox'  => array(
				'title'       => __( 'Sandbox App Id', 'woocommerce-gateway-duecom' ),
				'type'        => 'text',
				'desc_tip'    => __( 'Your App Id will be sent to you after your account has been approved.', 'woocommerce-gateway-duecom' ),
				'description' => __( 'Go to our <a href="https://due.com/blog/request-access-use-due-payment-gateway-woocommerce/" target="_blank">tutorial on requesting access</a> for more info.', 'woocommerce-gateway-duecom' ),
			),
			'api_key_sandbox' => array(
				'title'    => __( 'Sandbox API Key', 'woocommerce-gateway-duecom' ),
				'type'     => 'text',
				'desc_tip' => __( 'Go to the API section of your Due Settings to obtain your Sandbox API key.', 'woocommerce-gateway-duecom' ),
			),
			'sandbox'         => array(
				'title'       => __( 'Sandbox Mode', 'woocommerce-gateway-duecom' ),
				'label'       => __( 'Enable Sandbox Mode (Enabled if checked)', 'woocommerce-gateway-duecom' ),
				'type'        => 'checkbox',
				'description' => __( 'If Sandbox Mode is enabled, your Sandbox API Key will be used. Otherwise, your Live API Key will be used.', 'woocommerce-gateway-duecom' ),
				'default'     => 'no',
			),
			'store_cards'     => array(
				'title'       => __( 'Allow Stored Cards', 'woocommerce-gateway-duecom' ),
				'label'       => __( 'Allow logged in customers to save credit card profiles to use for future purchases', 'woocommerce-gateway-duecom' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			),
		);
	}

	/**
	 * Output the gateway settings screen
	 * @return void
	 */
	public function admin_options() {
		wc_get_template(
			'admin/admin-options.php',
			array(
				'gateway' => $this,
			),
			'',
			dirname( __FILE__ ) . '/../templates/'
		);
	}

	/**
	 * Check if SSL is enabled and notify the user.
	 * @return void
	 */
	public function checks() {
		if ( 'no' == $this->enabled ) {
			return;
		}

		// Check WooCommerce Version
		if ( version_compare( WC()->version, '2.6', '<' ) ) {
			echo '<div class="error"><p>' . sprintf( __( 'Error: Due.com requires WooCommerce 2.6 and above. You are using version %s.', 'woocommerce-gateway-duecom' ), WC()->version ) . '</p></div>';

			return;
		}

		// @todo WooCommerce v3.0 support
		if ( version_compare( WC()->version, '3.0', '>=' ) ) {
			echo '<div class="error"><p>' . 'Error: Sorry, Due.com don\'t support WooCommerce 3.0 and above. We are working on it.' . '</p></div>';

			return;
		}

		// Check required fields
		if ( 'yes' !== $this->sandbox && ( empty( $this->app_id ) || empty( $this->api_key ) ) ) {
			echo '<div class="error"><p>' . __( 'Error: Please enter your App id and Api key', 'woocommerce-gateway-duecom' ) . '</p></div>';

			return;
		}

		// Show message when using standard mode and no SSL on the checkout page
		if ( ! wc_checkout_is_https() ) {
			echo '<div class="error"><p>' . sprintf( __( '%s is enabled, but the <a href="%s">force SSL option</a> is disabled; your checkout may not be secure! Please enable SSL and ensure your server has a valid SSL certificate - Due.com will only work in sandbox mode.', 'woocommerce-gateway-duecom' ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) . '</p></div>';

			return;
		}
	}

	/**
	 * Check if this gateway is enabled.
	 *
	 * @return bool
	 */
	public function is_available() {
		if ( 'yes' !== $this->enabled ) {
			return FALSE;
		}

		if ( 'yes' !== $this->sandbox && ! wc_checkout_is_https() ) {
			return FALSE;
		}

		if ( 'yes' !== $this->sandbox && ( empty( $this->app_id ) || empty( $this->api_key ) ) ) {
			return FALSE;
		}

		return TRUE;
	}

	/**
	 * Add Scripts
	 * @return void
	 */
	public function add_scripts() {
		if ( ! ( is_checkout() || is_add_payment_method_page() ) || ! $this->is_available() ) {
			return;
		}

		wp_enqueue_script( 'duejs', 'https://static.due.com/v1/due.min.js', NULL, NULL, TRUE );
		wp_enqueue_script( 'duecheckoutjs', plugins_url( '/assets/js/due-checkout.js', dirname( __FILE__ ) ), array( 'duejs' ), NULL, TRUE );

		// Localize the script
		$translation_array = array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'env'      => $this->sandbox === 'yes' ? 'stage' : 'prod',
			'appId'    => $this->sandbox === 'yes' ? $this->app_id_sandbox : $this->app_id,
		);
		wp_localize_script( 'duecheckoutjs', 'Due_Woo', $translation_array );
	}

	/**
	 * If There are no payment fields show the description if set.
	 * @return void
	 */
	public function payment_fields() {
		//parent::payment_fields();

		// Check is Recurring Payment
		$is_recurring = FALSE;
		$cart         = WC()->cart->get_cart();
		foreach ( $cart as $key => $item ) {
			if ( is_object( $item['data'] ) && get_class( $item['data'] ) === 'WC_Product_Subscription' ) {
				$is_recurring = TRUE;
				break;
			}
		}

		// Check is Payment Method Change
		$is_payment_change = FALSE;
		if ( class_exists( 'WC_Subscriptions_Change_Payment_Gateway', FALSE )
		     && WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment
		) {
			$is_payment_change = TRUE;
		}

		// Credit Card Form
		if ( $this->store_cards === 'yes' || $is_recurring || $is_payment_change ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
			$this->form();
			$this->save_payment_method_checkbox();
		} else {
			$this->form();
			// Checked value
			echo sprintf(
				'<input id="wc-%1$s-payment-token-new" type="radio" name="wc-%1$s-payment-token" value="new" style="display: none;" checked />',
				esc_attr( $this->id )
			);
		}

		// Lock "Save to Account" for Recurring Payments
		if ( $is_recurring || $is_payment_change ):
			?>
			<script type="application/javascript">
				(function ($) {
					$(document).ready(function () {
						lockNewPaymentMethod();
					});

					$(document).on('updated_checkout', function () {
						lockNewPaymentMethod();
					});

					function lockNewPaymentMethod() {
						$('input[name="wc-duecom-new-payment-method"]').prop({
							'checked' : true,
							'disabled': true
						});
					}
				}(jQuery) );
			</script>
			<?php
		endif;

		// Select card for "Payment Change"
		if ( $is_payment_change ):
			global $wp;
			$subscription_id = absint( $wp->query_vars['order-pay'] );
			$token_id        = get_post_meta( $subscription_id, '_due_token_id', TRUE );
			if ( wcs_is_subscription( $subscription_id ) && $token_id ) {
				?>
				<script type="application/javascript">
					(function ($) {
						$(document).ready(function () {
							setDefaultToken();
						});

						$(document).on('updated_checkout', function () {
							setDefaultToken();
						});

						function setDefaultToken() {
							var token_id = '<?php echo esc_html( $token_id ); ?>';
							$('input[name="wc-duecom-payment-token"][value="' + token_id + '"]').prop('checked', true);
						}
					}(jQuery) );
				</script>
				<?php
			}
		endif;

		// Add fields for "Add payment method page" and "Payment Change"
		if ( is_user_logged_in() && ( is_add_payment_method_page() || $is_payment_change ) ):
			$current_user = wp_get_current_user();

			$first_name = get_user_meta( get_current_user_id(), 'billing_first_name', TRUE );
			$last_name  = get_user_meta( get_current_user_id(), 'billing_last_name', TRUE );
			$postcode   = get_user_meta( get_current_user_id(), 'billing_postcode', TRUE );
			$email      = $current_user->user_email;
			?>
			<input type="hidden" id="billing_first_name" value="<?php echo esc_attr( $first_name ); ?>" />
			<input type="hidden" id="billing_last_name" value="<?php echo esc_attr( $last_name ); ?>" />
			<input type="hidden" id="billing_email" value="<?php echo esc_attr( $email ); ?>" />
			<input type="hidden" id="billing_postcode" value="<?php echo esc_attr( $postcode ); ?>" />
			<?php
		endif;
	}

	/**
	 * Validate Frontend Fields
	 * @return bool|void
	 */
	public function validate_fields() {
		parent::validate_fields();
	}

	/**
	 * Add Payment Method
	 * @return array|void
	 */
	public function add_payment_method() {
		if ( empty ( $_POST['due_card_id'] ) || empty( $_POST['due_card_hash'] ) ) {
			wc_add_notice( __( 'There was a problem adding this card.', 'woocommerce-gateway-duecom' ), 'error' );

			return;
		}

		$card_id         = wc_clean( $_POST['due_card_id'] );
		$card_hash       = wc_clean( $_POST['due_card_hash'] );
		$card_risk_token = wc_clean( $_POST['due_risk_token'] );
		$card_last4      = wc_clean( $_POST['due_card_last4'] );
		$card_year       = wc_clean( $_POST['due_card_year'] );
		$card_month      = wc_clean( $_POST['due_card_month'] );
		$card_type       = wc_clean( $_POST['due_card_type'] );

		$token = new WC_Payment_Token_CC();
		$token->set_token( implode( ':', array(
			$card_id,
			$card_hash,
			$card_risk_token
		) ) );
		$token->set_gateway_id( $this->id );
		$token->set_last4( $card_last4 );
		$token->set_expiry_year( strlen( $card_year ) < 4 ? 2000 + $card_year : $card_year );
		$token->set_expiry_month( str_pad( $card_month, 2, '0', STR_PAD_LEFT ) );
		$token->set_card_type( $card_type );
		$token->set_user_id( get_current_user_id() );

		// Save Credit Card
		$token->save();
		if ( ! $token->get_id() ) {
			wc_add_notice( __( 'There was a problem adding this card.', 'woocommerce-gateway-duecom' ), 'error' );

			return;
		}

		return array(
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		);
	}

	/**
	 * Process Payment
	 *
	 * @param int $order_id
	 *
	 * @return array
	 */
	public function process_payment( $order_id ) {
		\Due\Due::setEnvName( $this->sandbox === 'yes' ? 'stage' : 'prod' );
		\Due\Due::setApiKey( $this->sandbox === 'yes' ? $this->api_key_sandbox : $this->api_key );
		\Due\Due::setAppId( $this->sandbox === 'yes' ? $this->app_id_sandbox : $this->app_id );

		$order = wc_get_order( $order_id );
		$token = new WC_Payment_Token_CC();
		if ( isset( $_POST['due_card_id'] ) && isset( $_POST['due_card_hash'] ) ) {
			$card_id         = wc_clean( $_POST['due_card_id'] );
			$card_hash       = wc_clean( $_POST['due_card_hash'] );
			$card_risk_token = wc_clean( $_POST['due_risk_token'] );
			$card_last4      = wc_clean( $_POST['due_card_last4'] );
			$card_year       = wc_clean( $_POST['due_card_year'] );
			$card_month      = wc_clean( $_POST['due_card_month'] );
			$card_type       = wc_clean( $_POST['due_card_type'] );

			$token->set_token( implode( ':', array(
				$card_id,
				$card_hash,
				$card_risk_token
			) ) );
			$token->set_gateway_id( $this->id );
			$token->set_last4( $card_last4 );
			$token->set_expiry_year( strlen( $card_year ) < 4 ? 2000 + $card_year : $card_year );
			$token->set_expiry_month( str_pad( $card_month, 2, '0', STR_PAD_LEFT ) );
			$token->set_card_type( $card_type );
			$token->set_user_id( get_current_user_id() );

			// Save Credit Card
			if ( isset( $_POST['wc-duecom-payment-token'] ) && $_POST['wc-duecom-payment-token'] === 'new' ) {
				if ( ($this->store_cards === 'yes' && ( isset( $_POST['wc-duecom-new-payment-method'] ) && $_POST['wc-duecom-new-payment-method'] === 'true' ) ) ||
					(function_exists('wcs_order_contains_subscription') && wcs_order_contains_subscription($order))
				) {
					$token->save();
					if ( ! $token->get_id() ) {
						wc_add_notice( __( 'Failed to save payment token', 'woocommerce-gateway-duecom' ), 'error' );

						return;
					}
				}
			}
		} elseif ( isset( $_POST['wc-duecom-payment-token'] ) && (int) $_POST['wc-duecom-payment-token'] > 0 ) {
			$token->read( $_POST['wc-duecom-payment-token'] );
			if ( ! $token->get_id() ) {
				wc_add_notice( __( 'Failed to load payment token', 'woocommerce-gateway-duecom' ), 'error' );

				return;
			}
			if ( $token->get_user_id() !== get_current_user_id() ) {
				wc_add_notice( __( 'Access denied', 'woocommerce-gateway-duecom' ), 'error' );

				return;
			}

			list( $card_id, $card_hash, $card_risk_token ) = explode( ':', $token->get_token() );
		} else {
			wc_add_notice( __( 'Security Error Occurred. Please Check Your Internet Connection and Try Again.', 'woocommerce-gateway-duecom' ), 'error' );

			return;
		}

		// Change Payment Method
		if ( class_exists( 'WC_Subscriptions_Change_Payment_Gateway', FALSE )
		     && WC_Subscriptions_Change_Payment_Gateway::$is_request_to_change_payment
		) {
			// Update Token ID
			update_post_meta( $order->id, '_due_token_id', $token->get_id() );

			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		// Success payment for empty orders
		if ( $order->get_total() == 0 ) {
			return array(
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order )
			);
		}

		// Do transaction
		$transaction = \Due\Charge::card( array(
			'amount'      => $order->get_total(),
			'currency'    => $order->get_order_currency(),
			'card_id'     => $card_id,
			'card_hash'   => $card_hash,
			'unique_id'   => $order_id,
			'customer_ip' => $_SERVER['REMOTE_ADDR'],
			'rdata'       => '', //@todo
			'webhook_url' => WC()->api_request_url( __CLASS__ )
		) );

		if ( $transaction && $transaction->success ) {
			update_post_meta( $order->id, '_due_token_id', $token->get_id() );
			update_post_meta( $order->id, '_due_transaction_data', (array) $transaction );

			$order->payment_complete( $transaction->id );
			$order->add_order_note(
				sprintf(
					__( 'Payment success. Transaction Id: %s, Card: %s', 'woocommerce-gateway-duecom' ),
					$transaction->id,
					$token->get_display_name()
				)
			);
		} elseif ( $transaction && $transaction->error_message ) {
			wc_add_notice( sprintf( __( 'Failed to perform payment. Error: %s', 'woocommerce-gateway-duecom' ), $transaction->error_message ), 'error' );

			return;
		} else {
			wc_add_notice( __( 'Failed to perform payment', 'woocommerce-gateway-duecom' ), 'error' );

			return;
		}

		return array(
			'result'   => 'success',
			'redirect' => $this->get_return_url( $order )
		);
	}

	/**
	 * Process Refund
	 *
	 * If the gateway declares 'refunds' support, this will allow it to refund
	 * a passed in amount.
	 *
	 * @param  int    $order_id
	 * @param  float  $amount
	 * @param  string $reason
	 *
	 * @return  bool|wp_error True or false based on success, or a WP_Error object
	 */
	public function process_refund( $order_id, $amount = NULL, $reason = '' ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return FALSE;
		}

		\Due\Due::setEnvName( $this->sandbox === 'yes' ? 'stage' : 'prod' );
		\Due\Due::setApiKey( $this->sandbox === 'yes' ? $this->api_key_sandbox : $this->api_key );
		\Due\Due::setAppId( $this->sandbox === 'yes' ? $this->app_id_sandbox : $this->app_id );

		// Do refund
		$refund = \Due\Refund::doCardRefund( array(
			'customer_ip'    => $_SERVER['REMOTE_ADDR'],
			'amount'         => $amount,
			'transaction_id' => $order->get_transaction_id(),
			'meta'           => array(
				'order_number'  => $order_id,
				'refund_reason' => $reason
			)
		) );

		if ( $refund && $refund->success ) {
			$order->add_order_note( sprintf( __( 'Refunded: %s. Reason: %s', 'woocommerce-gateway-duecom' ), wc_price( $amount ), $reason ) );

			return TRUE;
		} elseif ( $refund && $refund->error_message ) {
			return new WP_Error( 'refund', sprintf( __( 'Failed to perform refund. Error: %s', 'woocommerce-gateway-duecom' ), $refund->error_message ) );
		} else {
			return new WP_Error( 'refund', __( 'Failed to perform refund', 'woocommerce-gateway-duecom' ) );
		}
	}

	/**
	 * When a subscription payment is due.
	 *
	 * @param          $amount_to_charge
	 * @param WC_Order $renewal_order
	 */
	public function scheduled_subscription_payment( $amount_to_charge, $renewal_order ) {
		try {
			$token_id = get_post_meta( $renewal_order->id, '_due_token_id', TRUE );
			if ( empty( $token_id ) ) {
				throw new Exception( 'Invalid Token Id' );
			}

			// Load Card Token
			$token = new WC_Payment_Token_CC();
			$token->read( $token_id );
			if ( ! $token->get_id() ) {
				throw new Exception( 'Invalid Token Id' );
			}

			list( $card_id, $card_hash, $card_risk_token ) = explode( ':', $token->get_token() );

			\Due\Due::setEnvName( $this->sandbox === 'yes' ? 'stage' : 'prod' );
			\Due\Due::setApiKey( $this->sandbox === 'yes' ? $this->api_key_sandbox : $this->api_key );
			\Due\Due::setAppId( $this->sandbox === 'yes' ? $this->app_id_sandbox : $this->app_id );

			// Do transaction
			$transaction = \Due\Charge::card( array(
				'amount'      => $renewal_order->get_total(),
				'currency'    => $renewal_order->get_order_currency(),
				'card_id'     => $card_id,
				'card_hash'   => $card_hash,
				'unique_id'   => $renewal_order->id,
				'customer_ip' => $renewal_order->customer_ip_address,
				'rdata'       => '', //@todo
				'webhook_url' => WC()->api_request_url( __CLASS__ )
			) );

			if ( $transaction && $transaction->success ) {
				update_post_meta( $renewal_order->id, '_due_token_id', $token->get_id() );
				update_post_meta( $renewal_order->id, '_due_transaction_data', (array) $transaction );

				$renewal_order->payment_complete( $transaction->id );
				$renewal_order->add_order_note(
					sprintf(
						__( 'Payment success. Transaction Id: %s, Card: %s', 'woocommerce-gateway-duecom' ),
						$transaction->id,
						$token->get_display_name()
					)
				);
			} elseif ( $transaction && $transaction->error_message ) {
				throw new Exception( sprintf( __( 'Failed to perform payment. Details: %s', 'woocommerce-gateway-duecom' ), $transaction->error_message ) );
			} else {
				throw new Exception( __( 'Failed to perform payment', 'woocommerce-gateway-duecom' ), 'error' );
			}
		} catch ( Exception $e ) {
			$renewal_order->update_status( 'failed' );
			$renewal_order->add_order_note( sprintf( __( 'Failed to charge "%s". %s.', 'woocommerce' ), wc_price( $amount_to_charge ), $e->getMessage() ) );
		}
	}

	/**
	 * Thank you page
	 *
	 * @param $order_id
	 *
	 * @return void
	 */
	public function thankyou_page( $order_id ) {
		//
	}

	/**
	 * Notification Callback
	 * ?wc-api=WC_Gateway_Duecom
	 */
	public function return_handler() {
		@ob_clean();
		header( 'HTTP/1.1 200 OK' );

		// @todo Notification Callback
		$logger = new WC_Logger();
		$logger->add( $this->id, 'TODO' );
	}


	/**
	 * Update the card meta for a subscription after using Authorize.Net to complete a payment to make up for
	 * an automatic renewal payment which previously failed.
	 *
	 * @access public
	 *
	 * @param WC_Subscription $subscription  The subscription for which the failing payment method relates.
	 * @param WC_Order        $renewal_order The order which recorded the successful payment (to make up for the failed automatic payment).
	 *
	 * @return void
	 */
	public function update_failing_payment_method( $subscription, $renewal_order ) {
		update_post_meta( $subscription->id, '_due_token_id', get_post_meta( $renewal_order->id, '_due_token_id', TRUE ) );
	}

	/**
	 * Include the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions v2.0+.
	 *
	 * @since 2.4
	 *
	 * @param array           $payment_meta associative array of meta data required for automatic payments
	 * @param WC_Subscription $subscription An instance of a subscription object
	 *
	 * @return array
	 */
	public function add_subscription_payment_meta( $payment_meta, $subscription ) {
		$payment_meta[ $this->id ] = array(
			'post_meta' => array(
				'_due_token_id' => array(
					'value' => get_post_meta( $subscription->id, '_due_token_id', TRUE ),
					'label' => 'Saved Credit Card Token ID',
				),
			),
		);

		return $payment_meta;
	}

	/**
	 * Validate the payment meta data required to process automatic recurring payments so that store managers can
	 * manually set up automatic recurring payments for a customer via the Edit Subscription screen in Subscriptions 2.0+.
	 *
	 * @since 2.4
	 *
	 * @param string $payment_method_id The ID of the payment method to validate
	 * @param array  $payment_meta      associative array of meta data required for automatic payments
	 *
	 * @return array
	 */
	public function validate_subscription_payment_meta( $payment_method_id, $payment_meta ) {
		if ( $this->id === $payment_method_id ) {
			if ( ! isset( $payment_meta['post_meta']['_due_token_id']['value'] ) || empty( $payment_meta['post_meta']['_due_token_id']['value'] ) ) {
				throw new Exception( 'Saved Credit Card Token ID is required.' );
			}
		}
	}

	/**
	 * Don't transfer customer meta to resubscribe orders.
	 *
	 * @access public
	 *
	 * @param int $resubscribe_order The order created for the customer to resubscribe to the old expired/cancelled subscription
	 *
	 * @return void
	 */
	public function delete_resubscribe_meta( $resubscribe_order ) {
		delete_post_meta( $resubscribe_order->id, '_due_token_id' );
	}

	/**
	 * Clone Token ID when Subscription created
	 *
	 * @param $order_id
	 */
	public function add_subscription_token_id( $order_id ) {
		if ( function_exists( 'wcs_get_subscriptions_for_order' ) ) {
			$subscriptions = wcs_get_subscriptions_for_order( $order_id, array( 'order_type' => 'parent' ) );
			foreach ( $subscriptions as $subscription ) {
				$token_id = get_post_meta( $subscription->id, '_due_token_id', true );

				if ( empty( $token_id ) ) {
					$order_token_id = get_post_meta( $subscription->order->id, '_due_token_id', true );
					add_post_meta( $subscription->id, '_due_token_id', $order_token_id );
				}
			}
		}
	}

	/**
	 * Render the payment method used for a subscription in the "My Subscriptions" table
	 *
	 * @param string          $payment_method_to_display the default payment method text to display
	 * @param WC_Subscription $subscription              the subscription details
	 *
	 * @return string the subscription payment method
	 */
	public function maybe_render_subscription_payment_method( $payment_method_to_display, $subscription ) {
		// bail for other payment methods
		if ( $this->id !== $subscription->payment_method || ! $subscription->customer_user ) {
			return $payment_method_to_display;
		}

		$token_id = get_post_meta( $subscription->id, '_due_token_id', TRUE );
		if ( empty( $token_id ) ) {
			return $payment_method_to_display;
		}

		// Load Card Token
		$token = new WC_Payment_Token_CC();
		$token->read( $token_id );
		if ( ! $token->get_id() ) {
			return $payment_method_to_display;
		}

		return sprintf( __( 'Via %s', 'woocommerce-gateway-duecom' ), $token->get_display_name() );
	}
}
