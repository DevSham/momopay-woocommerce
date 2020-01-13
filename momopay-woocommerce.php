<?php
/**
 * Plugin Name: MTN MomoPay WooCommerce Payment Gateway
 * Plugin URI: https://momodeveloper.mtn.com
 * Description: Official WooCommerce payment gateway for MTN MomoPay
 * Author: Pearlbrains Ltd. (Allan Dereal)
 * Author URI: http://pearlbrains.net
 * Version: 1.0.0
 * License: GNU GPLv3
 */

define( 'MOMOPAY_PLUGIN_DIR_PATH', plugin_dir_path( __FILE__ ) );
require_once( MOMOPAY_PLUGIN_DIR_PATH . 'mtn-momopay-php-sdk/lib/Momopay.php' );
require_once( MOMOPAY_PLUGIN_DIR_PATH . 'mtn-momopay-php-sdk/lib/EventHandlerInterface.php' );
require_once( MOMOPAY_PLUGIN_DIR_PATH . 'mtn-momopay-php-sdk/lib/MomopayEventHandler.php' );

use MTN\MomopayEventHandler;
use MTN\Momopay;

/**
 * This action hook registers our PHP class as a WooCommerce payment gateway
 */
add_filter( 'woocommerce_payment_gateways', 'momopay_add_gateway_class' );


/**
 * @param $gateways
 * @return array
 */
function momopay_add_gateway_class($gateways ) {
    $gateways[] = 'WC_Momopay_Gateway';
    return $gateways;
}

/**
 * The class itself, please note that it is inside plugins_loaded action hook
 */
add_action( 'plugins_loaded', 'momopay_init_gateway_class' );


/**
 * Add the Settings link to the plugin
 *
 * @param  array $links Existing links on the plugin page
 *
 * @return array          Existing links with our settings link added
 */
function momopay_plugin_action_links( $links ) {

    $momopay_settings_url = esc_url( get_admin_url( null, 'admin.php?page=wc-settings&tab=checkout&section=momopay' ) );
    array_unshift( $links, "<a title='MTN MomoPay Settings Page' href='$momopay_settings_url'>Settings</a>" );

    return $links;

}
add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), 'momopay_plugin_action_links' );


/**
 *
 */
function momopay_init_gateway_class() {

    if ( !class_exists( 'WC_Payment_Gateway' ) ) return;


    class WC_Momopay_Gateway extends WC_Payment_Gateway {

        /**
         * Class constructor, more about it in Step 3
         */
        public function __construct()
        {
            $this->id = 'momopay'; // payment gateway plugin ID
            $this->icon = plugins_url('assets/img/momopay.png', __FILE__);
            $this->has_fields = true; // in case you need a custom credit card form
            $this->method_title = __('MTN MomoPay', 'momopay-payments');
            $this->method_description = __('MomoPay allows you to accept payment from MTN mobile subscribers in multiple currencies.', 'mmomopay-payments'); // will be displayed on the options page

            // gateways can support subscriptions, refunds, saved payment methods,
            // but in this tutorial we begin with simple payments
            $this->supports = array(
                'products'
            );

            // Method with all the options fields
            $this->init_form_fields();

            // Load the settings.
            $this->init_settings();

            $this->title = $this->get_option( 'title' );
            $this->description = $this->get_option( 'description' );
            $this->enabled = $this->get_option( 'enabled' );
            $this->go_live = 'yes' === $this->get_option( 'go_live' );

            $this->primary_key   = $this->get_option( 'primary_key' );
            $this->secondary_key   = $this->get_option( 'secondary_key' );

            $this->api_user   =  $this->go_live ? $this->get_option( 'live_api_user' ) : $this->get_option( 'test_api_user' );
            $this->api_key   =  $this->go_live ? $this->get_option( 'live_api_key' ) : $this->get_option( 'test_api_key' );
            $this->env = $this->go_live ? 'live' : 'sandbox';
            $this->base_url = $this->go_live ? 'https://live.momodeveloper.mtn.com/collection/' : 'https://sandbox.momodeveloper.mtn.com/collection/';
            $this->currency = $this->get_option( 'currency' );



            // This action hook saves the settings
            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );

            // We need custom JavaScript to obtain a token
            add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );

            // You can also register a webhook here
            // add_action( 'woocommerce_api_{webhook name}', array( $this, 'webhook' ) );
        }

        /**
         * Plugin options, we deal with it in Step 3 too
         */
        public function init_form_fields()
        {
            $this->form_fields = array(

                'enabled' => array(
                    'title'       => __( 'Enable/Disable', 'momopay-payments' ),
                    'label'       => __( 'Enable MTN MomoPay Payment Gateway', 'momopay-payments' ),
                    'type'        => 'checkbox',
                    'description' => __( 'Enable MTN MomoPay Payment Gateway as a payment option on the checkout page', 'momopay-payments' ),
                    'default'     => 'no',
                    'desc_tip'    => true
                ),
                'title' => array(
                    'title'       => __( 'Payment method title', 'momopay-payments' ),
                    'type'        => 'text',
                    'description' => __( 'Optional', 'momopay-payments' ),
                    'default'     => 'MTN MomoPay'
                ),
                'description' => array(
                    'title'       => __( 'Payment method description', 'momopay-payments' ),
                    'type'        => 'text',
                    'description' => __( 'Optional', 'momopay-payments' ),
                    'default'     => 'Powered by MTN Momo Pay: Accepts Payments Via MTN Mobile Money.'
                ),
                'go_live' => array(
                    'title'       => __( 'Mode', 'momopay-payments' ),
                    'label'       => __( 'Live mode', 'momopay-payments' ),
                    'type'        => 'checkbox',
                    'description' => __( 'Check this box if you\'re using your production keys.', 'momopay-payments' ),
                    'default'     => 'no',
                    'desc_tip'    => true
                ),
                'primary_key' => array(
                    'title'       => __( 'MTN MomoPay Primary Key', 'momopay-payments' ),
                    'type'        => 'text',
                    'description' => __( 'Required! Enter your primary key from https://momodeveloper.mtn.com', 'momopay-payments' ),
                    'default'     => ''
                ),
                'secondary_key' => array(
                    'title'       => __( 'MTN MomoPay Secondary Key', 'momopay-payments' ),
                    'type'        => 'text',
                    'description' => __( 'Optional! Enter your primary key from https://momodeveloper.mtn.com', 'momopay-payments' ),
                    'default'     => ''
                ),
                'live_api_user' => array(
                    'title'       => __( 'MTN MomoPay Live API User', 'momopay-payments' ),
                    'type'        => 'text',
                    'description' => __( 'Required! Enter your Live API User from https://momodeveloper.mtn.com', 'momopay-payments' ),
                    'default'     => ''
                ),
                'live api_key' => array(
                    'title'       => __( 'MTN MomoPay Live API Key', 'momopay-payments' ),
                    'type'        => 'text',
                    'description' => __( 'Required! Enter your Live API Key from https://momodeveloper.mtn.com', 'momopay-payments' ),
                    'default'     => ''
                ),
                'test_api_user' => array(
                    'title'       => __( 'MTN MomoPay Test API User', 'momopay-payments' ),
                    'type'        => 'text',
                    'description' => __( 'Enter your Test API User from https://momodeveloper.mtn.com', 'momopay-payments' ),
                    'default'     => ''
                ),
                'test_api_key' => array(
                    'title'       => __( 'MTN MomoPay Test API Key', 'momopay-payments' ),
                    'type'        => 'text',
                    'description' => __( 'Enter your Test API Key from https://momodeveloper.mtn.com', 'momopay-payments' ),
                    'default'     => ''
                ),
                'currency' => array(
                    'title'       => __( 'Charge Country', 'momopay-payments' ),
                    'type'        => 'select',
                    'description' => __( 'Optional - Charge country. (Default: Uganda)', 'momopay-payments' ),
                    'options'     => array(
                        'UGX' => esc_html_x( 'Uganda', 'currency', 'momopay-payments' ),
                        'ZMK' => esc_html_x( 'Zambia', 'currency', 'momopay-payments' ),
                        'XAF' => esc_html_x( 'Cameroon', 'currency', 'momopay-payments' ),
                        'XOF' => esc_html_x( 'COTE D\'IVOIRE', 'currency', 'momopay-payments' ),
                    ),
                    'default'     => 'UGX'
                )
            );
        }

        /**
         * You will need it if you want your custom credit card form, Step 4 is about it
         */
        public function payment_fields()
        {
            // ok, let's display some description before the payment form
            if ( $this->description ) {
                // you can instructions for test mode, I mean test card numbers etc.
                if ( !$this->go_live ) {
                    $this->description .= ' <b style="color: red;">TEST MODE ENABLED</b>. In test mode, you can use the test numbers listed in <a href="https://momodeveloper.mtn.com/api-documentation/testing/" target="_blank" rel="noopener noreferrer">documentation</a>.';
                    $this->description  = trim( $this->description );
                }
                // display the description with <p> tags etc.
                echo wpautop( wp_kses_post( $this->description ) );
            }

            // I will echo() the form, but you can close PHP tags and print it directly in HTML
            echo '<fieldset id="wc-' . esc_attr( $this->id ) . '-cc-form" class="wc-momopay-form wc-payment-form" style="background:transparent;">';

            // Add this action hook if you want your custom payment gateway to support it
            do_action( 'woocommerce_momopay_form_start', $this->id );



            // I recommend to use inique IDs, because other gateways could already use #ccNo, #expdate, #cvc
            echo '<div class="form-row form-row-wide">
                <label>Phone Number to Charge <span class="required">*</span></label>
                <input id="momopay_phone" name="momopay_phone" type="tel" autocomplete="off" placeholder=" eg. 0771234567">
            </div>
            <div class="clear"></div>';

            do_action( 'woocommerce_momopay_form_end', $this->id );

            echo '<div class="clear"></div></fieldset>';
        }

        /**
         * Custom CSS and JS, in most cases required only when you decided to go with a custom credit card form
         */
        public function payment_scripts()
        {
            // we need JavaScript to process a token only on cart/checkout pages, right?
            if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) ) {
                return;
            }

            // if our payment gateway is disabled, we do not have to enqueue JS too
            if ( 'no' === $this->enabled ) {
                return;
            }

            // no reason to enqueue JavaScript if API keys are not set
            if ( empty( $this->primary_key ) ||  empty( $this->api_user ) || empty( $this->api_key ) ) {
                return;
            }

            // do not work with api without SSL unless your website is in a test mode
            //if ( ! $this->testmode && ! is_ssl() ) {
            //    return;
            //}

            // let's suppose it is our payment processor JavaScript that allows to obtain a arguments

            wp_enqueue_script( 'momopay_js', plugins_url( 'assets/js/momopay.js', __FILE__ ), array( 'jquery' ), '1.0.0', true );

            // in most payment processors you have to use primary_key to obtain a token
            wp_localize_script( 'momopay_js', 'momopay_args', array(
                'currencies' => array(
                    'UG' => 'UGX', //uganda
                    'CI' => 'XOF', //cote d'voire
                    'CA' => 'XAF', //cameroon
                    'ZM' => 'ZMK' //zambia
                ),
                'go_live' => $this->go_live
            ) );
        }

        /**
          * Fields validation, more in Step 5
         */
        public function validate_fields()
        {
            if( empty( $_POST[ 'billing_first_name' ]) ) {
                wc_add_notice(  'First name is required!', 'error' );
                return false;
            }
            return true;
        }

        /**
         * We're processing the payments here, everything about it is in Step 5
         */
        public function process_payment( $order_id )
        {
            // we need it to get any order details
            $order = wc_get_order( $order_id );
            $currency = $this->go_live ? strtoupper($order->get_order_currency()) : 'EUR';
            $phone = isset($_POST['momopay_phone']) ? $_POST['momopay_phone'] : $order->get_billing_phone();
            $phone = substr($phone, 0, 1) == '+' ? substr($phone, 4) : $phone;
            $external_id = 'WOO_'.$order->id.'_'.time();
            $note =  'Payment for order '.$order->id.' on '.get_permalink( woocommerce_get_page_id( 'shop' ) );
            $error = null;
            $event_handler = new MomopayEventHandler($order);

            $request = new Momopay($this->primary_key, $this->api_user, $this->api_key, $this->base_url, $this->env, $event_handler);
            $access_token = $request->getAccessToken();

            if ($access_token){
                $response = $request->setAmount(round($order->order_total))
                    ->setCurrency($currency)
                    ->setPhone($phone)
                    ->setExternalID($external_id)
                    ->setPayerMessage($note)
                    ->setPayeeNote($note)
                    ->requestPayment();

                if ($response){
                    //query/requery transaction status
                    $status = $request->getRequestStatus();
                    switch ($status) {
                        case 'error':
                            $error = 'Transaction Failed!';
                            break;
                        case 'failed':
                            $error = $request->getError();
                            break;
                        case 'successful':
                            //
                    }
                } else {
                    $error = 'Can\'t Process Payment. Please check Payment form details.';
                }
            } else {
                $error = 'Payment Failed!';
            }

            if (!is_null($error)){
                wc_add_notice(  $error, 'error' );
                return;
            }

            // Redirect to the thank you page
            return array(
             'result' => 'success',
             'redirect' => $this->get_return_url( $order )
            );
        }

        /**
         * In case you need a webhook, like PayPal IPN etc
         */
        public function webhook()
        {
            //
        }
    }
}