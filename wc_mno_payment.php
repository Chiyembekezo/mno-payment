<?php
/**
 * Plugin Name: WooCommerce Mobile Money
 * Plugin URI: http://yourwebsite.com
 * Description: Adds Mobile Money payment options to WooCommerce
 * Version: 1.0
 * Author: Chiyembekezo
 * Author URI: http://yourwebsite.com
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly
}

// Hook our custom initialization function into the 'plugins_loaded' action, which fires after all plugins are loaded.
add_action( 'plugins_loaded', 'initialize_wc_gateway_mobile_money' );

function initialize_wc_gateway_mobile_money() {
    // First, check if WooCommerce is active and WC_Payment_Gateway exists
    if ( ! class_exists( 'WC_Payment_Gateway' ) ) return;

    // Define the custom payment gateway class
    class WC_Gateway_Mobile_Money extends WC_Payment_Gateway {
        // Constructor for the gateway class
        public function __construct() {
            $this->id                 = 'mobile_money';
            $this->icon               = apply_filters('woocommerce_mobile_money_icon', '');
            $this->has_fields         = true;
            $this->method_title       = __( 'Mobile Money', 'woocommerce' );
            $this->method_description = __( 'Allows payments with mobile money.', 'woocommerce' );
            
            // Load the settings
            $this->init_form_fields();
            $this->init_settings();
            
            // Define user set variables
            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->instructions = $this->get_option( 'instructions', $this->description );
            
            // Actions
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // You can also register hooks related to your payment gateway here
        }

        public function init_form_fields() {
            parent::init_form_fields();

            $this->form_fields += array(
                'mobile_money_number' => array(
                    'title'       => __('Mobile Money Number', 'woocommerce'),
                    'type'        => 'text',
                    'description' => __('Please enter your mobile money number', 'woocommerce'),
                    'default'     => '',
                    'desc_tip'    => true,
                ),
                'enabled' => array(
                    'title'       => __( 'Enable/Disable', 'woocommerce' ),
                    'label'       => __( 'Enable Mobile Money Payment', 'woocommerce' ),
                    'type'        => 'checkbox',
                    'description' => '',
                    'default'     => 'no'
                ),
                'title' => array(
                    'title'       => __( 'Title', 'woocommerce' ),
                    'type'        => 'text',
                    'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
                    'default'     => __( 'Mobile Money Payment', 'woocommerce' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Description', 'woocommerce' ),
                    'type'        => 'textarea',
                    'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce' ),
                    'default'     => ''
                ),
                'instructions' => array(
                    'title'       => __( 'Instructions', 'woocommerce' ),
                    'type'        => 'textarea',
                    'description' => __( 'Instructions that will be added to the thank you page.', 'woocommerce' ),
                    'default'     => ''
                ),
            );
        }

        public function process_payment( $order_id ) {
            // Process the payment

            $order = wc_get_order( $order_id );
    
            // Preparing your data for the payload
            $amount = $order->get_total();
            $service_code = "0025"; // This should be your service code for Mobile Money
            $mobile = $order->get_billing_phone(); // Customer's mobile phone number
            $merchantId = 1; // Your merchant ID
            $callbackUrl = "https://google.com"; // The callback URL where the payment gateway will send the transaction status
            $paymentDescription = "UN TEST"; // Description of the payment
            $paymentReference = time(); // A timestamp as a simple payment reference
            $last_name = $order->get_billing_last_name(); // Customer's last name from the order
        
            $payload = array(
                "amount" => $amount,
                "service_code" => $service_code,
                "mobile" => $mobile,
                "merchantId" => $merchantId,
                "callbackUrl" => $callbackUrl,
                "paymentDescription" => $paymentDescription,
                "paymentReference" => $paymentReference,
                "kyc" => array(
                    "test" => "probase airtel test",
                    "last_name" => $last_name
                )
            );
        
            $response = wp_remote_post( 'http://95.179.223.128:4200/pbs/Payments/Api/V1/TransactionLookup', array(
                'method' => 'POST',
                'headers' => array(
                    'Content-Type' => 'application/json',
                ),
                'body' => json_encode( $payload ),
                'data_format' => 'body'
            ));
        
            if ( is_wp_error( $response ) ) {
                wc_add_notice( 'Connection error: ' . $response->get_error_message(), 'error' );
                return;
            }
        
            $body = wp_remote_retrieve_body( $response );
            $data = json_decode( $body );
        
            if ( isset($data->status) && $data->status == 'success' ) {
                // Mark as on-hold (we're awaiting the payment)
                $order->update_status( 'on-hold', __( 'Awaiting mobile money payment', 'woocommerce' ) );
        
                // Reduce stock levels
                wc_reduce_stock_levels( $order_id );
        
                // Remove cart
                WC()->cart->empty_cart();
        
                // Return thankyou redirect
                return array(
                    'result'   => 'success',
                    'redirect' => $this->get_return_url( $order )
                );
            } else {
                // You can retrieve $data->message or another error indicator if provided by your API response
                wc_add_notice( 'Payment error: ' . (isset($data->message) ? $data->message : 'An error occurred'), 'error' );
                return;
            }
        }

        // You will need to define other methods required by WC_Payment_Gateway here
    }

    // Add the new gateway to WooCommerce
    function add_wc_gateway_mobile_money( $methods ) {
        $methods[] = 'WC_Gateway_Mobile_Money';
        return $methods;
    }
    add_filter( 'woocommerce_payment_gateways', 'add_wc_gateway_mobile_money' );
}

// Rest of your integration code, if any, goes here...
