<?php
/* Due Payment Gateway Class */
class WC_Due_Gateway extends WC_Payment_Gateway {

    public function __construct() {

        // The global ID for this Payment method
        $this->id = "wpdp_due_payments";

        // The Title shown on the top of the Payment Gateways Page next to all the other Payment Gateways
        $this->method_title = __( "Due.com Payments", 'wpdp-due-payments' );

        // The description for this Payment Gateway, shown on the actual Payment options page on the backend
        $this->method_description = __( 'Due.com E-Commerce Payment Gateway Plug-in for WooCommerce. Learn more <a href="https://due.com/blog/request-access-use-due-payment-gateway-woocommerce/" target="_blank">here</a>.', 'wpdp-due-payments' );

        // The title to be used for the vertical tabs that can be ordered top to bottom
        $this->title = __( "Due.com", 'wpdp-due-payments' );

        // If you want to show an image next to the gateway's name on the frontend, enter a URL to an image.
        $this->icon = null;

        // Bool. Can be set to true if you want payment fields to show on the checkout
        // if doing a direct integration, which we are doing in this case
        $this->has_fields = true;

        // Tell WooCommerce what this gateway can do
        $this->supports = array( 'default_credit_card_form','refunds','products' );

        // Web App Base URL
        $this->base_app_url = 'https://app.due.com';
        $this->base_app_url_stage = 'https://stage-app.due.com';

        // Turns Transaction ID into a link
        //$this->view_transaction_url = 'https://app.due.com/reports/ecommerce/payments/%s';
        $this->view_transaction_url = $this->base_app_url.'/payments?cat=ecommerce';

        // This basically defines your settings which are then loaded with init_settings()
        $this->init_form_fields();

        // After init_settings() is called, you can get the settings and load them into variables, e.g:
        // $this->title = $this->get_option( 'title' );
        $this->init_settings();

        // Turn these settings into variables we can use
        foreach ( $this->settings as $setting_key => $value ) {
            $this->$setting_key = $value;
        }

        // API Base URL
        $this->base_api_url = 'https://api.due.com';
        $this->base_api_url_stage = 'https://stage-api.due.com';

        // API Base URL
        $this->duejs_url = 'https://static.due.com/v1/due.min.js';
        $this->duejs_url_stage = 'https://static.due.com/v1/due.test.min.js';

        // API Version
        $this->api_version = 'v1';

        // Lets check for SSL
        add_action( 'admin_notices', array( $this,	'do_ssl_check' ) );

        // Save settings
        if ( is_admin() ) {
            // Versions over 2.0
            // Save our administration options. Since we are not going to be doing anything special
            // we have not defined 'process_admin_options' in this class so the method in the parent
            // class will be used instead
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
        }
        add_action( 'wp_enqueue_scripts', array( $this, 'load_due_scripts' ) );
    } // End __construct()

    // Build the administration fields for this specific Gateway
    public function init_form_fields() {
        $this->form_fields = array(
            'enabled' => array(
                'title'		=> __( 'Enable / Disable', 'wpdp-due-payments' ),
                'label'		=> __( 'Enable Due Credit/Debit Card Payments', 'wpdp-due-payments' ),
                'type'		=> 'checkbox',
                'default'	=> 'no',
            ),
            'title' => array(
                'title'		=> __( 'Title', 'wpdp-due-payments' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Payment title the customer will see during the checkout process.', 'wpdp-due-payments' ),
                'default'	=> __( 'Pay With Credit Card', 'wpdp-due-payments' ),
            ),
            'description' => array(
                'title'		=> __( 'Description', 'wpdp-due-payments' ),
                'type'		=> 'textarea',
                'desc_tip'	=> __( 'Payment description the customer will see during the checkout process.', 'wpdp-due-payments' ),
                'default'	=> __( 'Pay securely using your credit card.', 'wpdp-due-payments' ),
                'css'		=> 'max-width:350px;'
            ),
            'due_app_id' => array(
                'title'		=> __( 'Live App Id', 'wpdp-due-payments' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Your App Id will be sent to you after your account has been approved.', 'wpdp-due-payments' ),
                'description' => __( 'Go to our <a href="https://due.com/blog/request-access-use-due-payment-gateway-woocommerce/" target="_blank">tutorial on requesting access</a> for more info.', 'wpdp-due-payments' ),
            ),
            'api_key_live' => array(
                'title'		=> __( 'Live API Key', 'wpdp-due-payments' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Go to the API section of your Due Settings to obtain your Live API key.', 'wpdp-due-payments' ),
            ),
            'due_app_id_stage' => array(
                'title'		=> __( 'Sandbox App Id', 'wpdp-due-payments' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Your App Id will be sent to you after your account has been approved.', 'wpdp-due-payments' ),
                'description' => __( 'Go to our <a href="https://due.com/blog/request-access-use-due-payment-gateway-woocommerce/" target="_blank">tutorial on requesting access</a> for more info.', 'wpdp-due-payments' ),
            ),
            'api_key_sandbox' => array(
                'title'		=> __( 'Sandbox API Key', 'wpdp-due-payments' ),
                'type'		=> 'text',
                'desc_tip'	=> __( 'Go to the API section of your Due Settings to obtain your Sandbox API key.', 'wpdp-due-payments' ),
            ),
            'due_sandbox' => array(
                'title'		=> __( 'Sandbox Mode', 'wpdp-due-payments' ),
                'label'		=> __( 'Enable Sandbox Mode (Enabled if checked)', 'wpdp-due-payments' ),
                'type'		=> 'checkbox',
                'description' => __( 'If Sandbox Mode is enabled, your Sandbox API Key will be used. Otherwise, your Live API Key will be used.', 'wpdp-due-payments' ),
                'default'	=> 'no',
            )
        );
    }

    /*Is Sandbox Enabled*/
    public function is_sandbox_enabled() {

        //$this->due_sandbox = "yes" or "no"
        if( 'no'  == $this->due_sandbox )
        { return false; }

        return true;
    } // end of function is_sandbox_enabled()

    /*Is Avalaible*/
    public function is_available() {
        if ( ! in_array( get_woocommerce_currency(), apply_filters( 'due_woocommerce_supported_currencies', array( 'USD' ) ) ) )
        { return false; }

        if( !$this->is_sandbox_enabled() && (empty($this->api_key_live) || empty($this->due_app_id) ))
        { return false; }

        return true;
    } // end of function is_available()

    /*Load JS Scripts*/
    public function load_due_scripts() {
        wp_enqueue_script( 'duejs', 'https://static.due.com/v1/due.min.js', false, null, true );
        wp_enqueue_script( 'duewoojs', plugins_url( '../assets/duewoo.js',  __FILE__  ), array( 'duejs' ), null, true );

        $checkout_data_array = array();
        if($this->is_sandbox_enabled()){$due_env='stage';}else{$due_env='prod';}
        $checkout_data_array['due_env'] = $due_env;
        wp_localize_script( 'duewoojs', 'checkout_data_array', $checkout_data_array );
    } // end of function load_due_scripts()

    // get base api path
    public function due_api_request_data() {
        $return = array();

        // Decide which URL to post to
        if($this->is_sandbox_enabled()){
            // Sandbox Mode
            $api_key = $this->api_key_sandbox;
            $base_api_url = $this->base_api_url_stage;
            $base_app_url = $this->base_app_url_stage;
            $due_app_id = $this->due_app_id_stage;
        }else{
            // Live Mode
            $api_key = $this->api_key_live;
            $base_api_url = $this->base_api_url;
            $base_app_url = $this->base_app_url;
            $due_app_id = $this->due_app_id;
        }
        $this->view_transaction_url = $base_app_url.'/payments?cat=ecommerce';
        $return['base_url'] = $base_api_url.'/'.$this->api_version;
        $return['api_key'] = $api_key;
        $return['due_app_id'] = $due_app_id;

        return $return;
    } // end of function due_api_request_data()

    // Submit payment and handle response
    public function process_payment( $order_id ) {
        global $woocommerce;

        $api_request_data = $this->due_api_request_data();
        $due_app_id = $api_request_data['due_app_id'];
        $base_url = $api_request_data['base_url'];
        $payment_api_key = $api_request_data['api_key'];
        if(empty($due_app_id)){
            throw new Exception( __( 'This site does not have permission to use Due.com E-Commerce Payment Gateway', 'wpdp-due-payments' ) );
        }
        $payment_rail_data = $this->get_payment_rail();
        $payment_rail_id = $payment_rail_data['payment_rail_id'];
        if(empty($payment_rail_id)){
            throw new Exception( __( 'This site is not currently setup to process Due Payments.', 'wpdp-due-payments' ) );
        }
        if($payment_rail_id != '588e26fca6909'){
            throw new Exception( __( 'This site does not have permission to use Due.com E-Commerce Payment Gateway', 'wpdp-due-payments' ) );
        }

        // Get this Order's information so that we know
        // who to charge and how much
        $customer_order = wc_get_order( $order_id );

        //Order data is collected to lower risk for merchants and customers
        $risk_data = array();
        $risk_data['items'] = array();
        $risk_data['unstructured_order'] = (array)$customer_order;
        foreach($customer_order->get_items() as $item){
            $product_item = array();
            $product_item['unstructured'] = $item;
            $product_description = (
            empty(get_post($item['product_id'])->post_content) ?
                '' :
                get_post($item['product_id'])->post_content
            );
            $product_item['description'] = $product_description;
            $product_item['amount'] = $item['line_total'];
            $product_item['quantity'] = $item['qty'];
            $risk_data['items'][] = $product_item;
        }

        $payment_endpoint = 'ecommerce/payments/card';
        $environment_url = $base_url.'/'.$payment_endpoint;

        if(trim(sanitize_text_field($payment_api_key)) != ''){

            // This is where the fun stuff begins
            if(
                empty($this->get_post_val('due_card_id')) ||
                empty($this->get_post_val('due_risk_token')) ||
                empty($this->get_post_val('due_risk_url'))
            ){
                $integration_error = 'Security Error Occurred. Please Check Your Internet Connection and Try Again.';
                wc_add_notice( $integration_error, 'error' );
                // Add note to the order for your reference
                $customer_order->add_order_note( 'Payment Integration Error: '. $integration_error );
                return null;
            }
            $risk_token = $this->get_post_val('due_risk_token');
            $risk_url = $this->get_post_val('due_risk_url');
            $risk_ip = $this->get_post_val('due_risk_ip');
            if(empty($risk_ip)){
                $risk_ip = $this->get_wp_client_ip();
            }
            $card_id = $this->get_post_val('due_card_id');

            $customer_data = array(
                'first_name' => $customer_order->billing_first_name,
                'last_name' => $customer_order->billing_last_name,
                'street_1' => $customer_order->billing_address_1,
                'street_2' => $customer_order->billing_address_2,
                'city' => $customer_order->billing_city,
                'state' => $customer_order->billing_state,
                'zip' => $customer_order->billing_postcode,
                'country' => $customer_order->billing_country,
                'phone' => $customer_order->billing_phone,
                'email' => $customer_order->billing_email,
            );
            $shipping_data = array(
                'first_name' => $customer_order->shipping_first_name,
                'last_name' => $customer_order->shipping_last_name,
                'street_1' => $customer_order->shipping_address_1,
                'street_2' => $customer_order->shipping_address_2,
                'city' => $customer_order->shipping_city,
                'state' => $customer_order->shipping_state,
                'zip' => $customer_order->shipping_postcode,
                'country' => $customer_order->shipping_country,
            );

            $card_data = array();
            $card_data['card_id'] = $card_id;

            $payload = array(
                'source_id' => $due_app_id,
                'rdata' => $risk_data,
                'customer_ip' => $risk_ip,
                'rtoken' => $risk_token,
                'source' => $risk_url,
                'amount' => $customer_order->order_total,
                'card' => $card_data,
                'customer' => $customer_data,
                'shipping' => $shipping_data
            );

            try{
                // Send this payload to Due.com for processing
                $post_data = array(
                    'method'    => 'POST',
                    'headers' => array(
                        'DUE-API-KEY' => $payment_api_key,
                    ),
                    'body'      => array('payload'=>$payload),
                    'timeout'   => 90,
                    'sslverify' => false,
                );

                $response = wp_remote_post( $environment_url, $post_data );

                if ( is_wp_error( $response ) )
                    throw new Exception( __( 'We are currently experiencing problems trying to connect to the payment gateway. Sorry for the inconvenience.', 'wpdp-due-payments' ) );

                if ( empty( $response['body'] ) )
                    throw new Exception( __( 'Due.com\'s Response was empty.', 'wpdp-due-payments' ) );

                // Retrieve the body's resopnse if no errors found
                $response_body = wp_remote_retrieve_body( $response );
                $response_data = json_decode($response_body, true);
                if(!empty($response_data['success'])){
                    $successful_transaction = $response_data['success'];
                }else{
                    $successful_transaction = false;
                }

                // Test the code to know if the transaction went through or not.
                if ( $successful_transaction ) {
                    // Payment has been successful
                    $customer_order->add_order_note( __( 'Payment completed.', 'wpdp-due-payments' ) );

                    $successful_transaction_id = null;
                    if(!empty($response_data['transactions'][0]['id'])){
                        $successful_transaction_id = $response_data['transactions'][0]['id'];
                    }
                    // Mark order as Paid
                    $customer_order->payment_complete($successful_transaction_id);

                    // Empty the cart (Very important step)
                    $woocommerce->cart->empty_cart();
                    // Redirect to thank you page
                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url( $customer_order ),
                    );
                } else {
                    // Transaction was not succesful
                    // Add notice to the cart
                    wc_add_notice( $response_data['error_message'], 'error' );
                    // Add note to the order for your reference
                    $customer_order->add_order_note( 'Error: '. $response_data['error_message'] );
                }
            }//end ot try block
            catch (Exception $e)
            {
                $exception_error = 'We were unable to process your payment at this time. Sorry for the inconvenience.';
                wc_add_notice( $exception_error, 'error' );
                // Add note to the order for your reference
                $customer_order->add_order_note( 'Payment Error: '. $exception_error );
            }
        }else{
            $integration_error = 'Store is not successfully setup to process payments. Sorry for the inconvenience.';
            wc_add_notice( $integration_error, 'error' );
            // Add note to the order for your reference
            $customer_order->add_order_note( 'Payment Integration Error: '. $integration_error );
        }
    } // end of function process_payment()

    /*process refund function*/
    public function process_refund(
        $order_id,
        $amount = NULL,
        $reason = ''
    ) {
        if($amount > 0 )
        {
            $wc_order    = wc_get_order( $order_id );
            $due_transaction_id = get_post_meta( $order_id , '_transaction_id', true );

            $payment_endpoint = 'ecommerce/refund/card';
            $api_request_data = $this->due_api_request_data();
            $base_url = $api_request_data['base_url'];
            $payment_api_key = $api_request_data['api_key'];
            $environment_url = $base_url.'/'.$payment_endpoint;

            $payload = array(
                'customer_ip' => $this->get_wp_client_ip(),
                'amount' => $amount,
                'transaction_id' => $due_transaction_id,
                'meta' => array(
                    'order_number'   => $order_id,
                    'refund_reason' => $reason
                )
            );

            try{
                // Send this payload to Due.com for processing
                $post_data = array(
                    'method'    => 'POST',
                    'headers' => array(
                        'DUE-API-KEY' => $payment_api_key,
                    ),
                    'body'      => array('payload'=>$payload),
                    'timeout'   => 90,
                    'sslverify' => false,
                );

                $response = wp_remote_post( $environment_url, $post_data );

                if(
                    is_wp_error( $response ) ||
                    empty( $response['body'] )
                ) {
                    return false;
                }else{
                    $response_body = wp_remote_retrieve_body( $response );
                    $response_data = json_decode($response_body, true);
                    if(!empty($response_data['success'])){
                        $refund = $response_data['success'];
                    }else{
                        $refund = false;
                    }

                    if($refund)
                    {
                        $created = time();
                        $transaction_id = 'N/A';
                        if(!empty($response_data['transactions'][0]['timestamp'])){
                            $created = $response_data['transactions'][0]['timestamp'];
                        }
                        if(!empty($response_data['transactions'][0]['id'])){
                            $transaction_id = $response_data['transactions'][0]['id'];
                        }
                        $refund_id    = $transaction_id;
                        $refund_created  = date('Y-m-d H:i:s A e', $created);

                        $wc_order->add_order_note( __('Refund Initiated at '.$refund_created.' with Refund ID = '.$refund_id , 'woocommerce' ) );
                        return true;
                    }
                    else
                    {
                        return false;
                    }
                }
            }//end ot try block
            catch (Exception $e)
            {
                return false;
            }
        }
        else
        {
            return false;
        }
    }// end of  process_refund()

    // Submit payment and handle response
    public function get_payment_rail() {
        $return = array();
        $return['payment_rail_id'] = '';

        $payment_endpoint = 'ecommerce/get_payment_rail';
        $api_request_data = $this->due_api_request_data();
        $base_url = $api_request_data['base_url'];
        $payment_api_key = $api_request_data['api_key'];
        $environment_url = $base_url.'/'.$payment_endpoint;

        try{
            // Send this payload to Due.com for processing
            $get_data = array(
                'headers'     => array(
                    'DUE-API-KEY' => $payment_api_key,
                ),
                'sslverify'   => false
            );

            $response = wp_remote_get( $environment_url, $get_data );

            if ( is_wp_error( $response ) )
                throw new Exception( __( 'We are currently experiencing problems trying to connect to the payment gateway. Sorry for the inconvenience.', 'wpdp-due-payments' ) );

            if( is_array($response) ) {
                $response_body = wp_remote_retrieve_body( $response );
                $response_data = json_decode($response_body, true);
                $return['payment_rail_id'] = $response_data['payment_rail'];
            }
        }//end ot try block
        catch (Exception $e)
        {

        }

        return $return;
    } // end of function get_payment_rail()

    // get ip address of wordpress user
    function get_wp_client_ip() {
        if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
            //check ip from share internet
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            //to check ip is pass from proxy
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return apply_filters( 'wpb_get_ip', $ip );
    } // end of function get_wp_client_ip()

    //get value from post request
    public function get_post_val($arg_name){
        $value = '';
        if(isset($_POST[$arg_name])){
            $value = trim(sanitize_text_field($_POST[$arg_name]));
        }

        return $value;
    } // end of function get_post_val()

    // Validate fields
    public function validate_fields() {
        return true;
    }

    // Check if we are forcing SSL on checkout pages
    // Custom function not required by the Gateway
    public function do_ssl_check() {
        if( 'yes'  != $this->due_sandbox && "no" == get_option( 'woocommerce_force_ssl_checkout' ) && $this->enabled == "yes" ) {
            echo "<div class=\"error\"><p>". sprintf( __( "<strong>%s</strong> is enabled and WooCommerce is not forcing the SSL certificate on your checkout page. Please ensure that you have a valid SSL certificate and that you are <a href=\"%s\">forcing the checkout pages to be secured.</a>" ), $this->method_title, admin_url( 'admin.php?page=wc-settings&tab=checkout' ) ) ."</p></div>";
        }
    }

} // End of WC_Due_Gateway
