<?php
/**
 * Plugin Name: nganluong gateway
 * Plugin URI: https://portfolio.quochuy.dev/nganluong-gateway
 * Description: A custom WooCommerce payment gateway to integrate nganluong.
 * Version: 1.0.0
 * Author: @quochuydev
 * Author URI: https://portfolio.quochuy.dev
 * License: GPLv2 or later
 * Text Domain: nganluong-gateway
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'woocommerce_payment_gateways', 'spg_add_nganluong_gateway_class' );

function spg_add_nganluong_gateway_class( $gateways ) {
    $gateways[] = 'SPG_Nganluong_Gateway';
    return $gateways;
}

add_action( 'plugins_loaded', 'spg_init_nganluong_gateway_class' );

add_action('woocommerce_checkout_process', function() {
    if ('spg_nganluong' === WC()->session->get('chosen_payment_method')) {
        if (empty($_POST['spg_nganluong_bank_code'])) {
            wc_add_notice(__('Please select a bank for payment.', 'nganluong-gateway'), 'error');
        }
    }
});

add_action('woocommerce_checkout_update_order_meta', function($order_id) {
    if (!empty($_POST['spg_nganluong_bank_code'])) {
        update_post_meta($order_id, '_nganluong_bank_code', sanitize_text_field($_POST['spg_nganluong_bank_code']));
    }
});

function spg_init_nganluong_gateway_class() {
    class SPG_Nganluong_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'spg_nganluong';
            $this->icon               = '';
            $this->has_fields         = false;
            $this->method_title       = __( 'Nganluong', 'nganluong-gateway' );
            $this->method_description = __( 'Redirect customers to Nganluong to complete payment.', 'nganluong-gateway' );

            $this->init_form_fields();
            $this->init_settings();

            $this->title        = $this->get_option( 'title' );
            $this->description  = $this->get_option( 'description' );
            $this->client_id    = $this->get_option( 'client_id' );
            $this->client_secret = $this->get_option( 'client_secret' );
            $this->return_url   = $this->get_option( 'return_url' );
            $this->receiver_email   = $this->get_option( 'receiver_email' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_return' ) );
        }

        // Define the settings fields.
        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'   => __( 'Enable/Disable', 'nganluong-gateway' ),
                    'type'    => 'checkbox',
                    'label'   => __( 'Enable Nganluong Payment', 'nganluong-gateway' ),
                    'default' => 'yes',
                ),
                'title' => array(
                    'title'       => __( 'Title', 'nganluong-gateway' ),
                    'type'        => 'text',
                    'description' => __( 'The title displayed at checkout.', 'nganluong-gateway' ),
                    'default'     => __( 'Pay with Nganluong', 'nganluong-gateway' ),
                    'desc_tip'    => true,
                ),
                'description' => array(
                    'title'       => __( 'Description', 'nganluong-gateway' ),
                    'type'        => 'textarea',
                    'default'     => __( 'You will be redirected to Nganluong to complete your purchase.', 'nganluong-gateway' ),
                ),
                'bank_code' => array(
                    'title'       => __( 'Default Bank Code', 'nganluong-gateway' ),
                    'type'        => 'select',
                    'description' => __( 'Select the default bank for transactions.', 'nganluong-gateway' ),
                    'desc_tip'    => true,
                    'default'     => 'VCB',
                    'options'     => array(
                        'VCB' => 'Ngân hàng Ngoại thương Việt Nam (Vietcombank)',
                        'DAB' => 'Ngân hàng Đông Á (DongA Bank)',
                        'TCB' => 'Ngân hàng kỹ thương Việt Nam (Techcombank)',
                        'MB' => 'Ngân hàng Quân đội (MB)',
                        'VIB' => 'Ngân hàng Quốc Tế Việt Nam (VIB)',
                        'AGB' => 'Ngân hàng Nông nghiệp và phát triển Nông thôn Việt Nam (Agribank)',
                        'BIDV' => 'Ngân hàng đầu tư và phát triển Việt Nam (BIDV)',
                        'OCB' => 'Ngân hàng Phương Đông',
                        'SHNB' => 'Ngân hàng Shinhan Việt Nam (Shinhan Bank)',	
                    ),
                ),
                'client_id' => array(
                    'title'       => __( 'Nganluong Client ID', 'nganluong-gateway' ),
                    'type'        => 'text',
                    'description' => __( 'Your Nganluong Client ID for API integration.', 'nganluong-gateway' ),
                    'desc_tip'    => true,
                    'default'     => '',
                ),
                'client_secret' => array(
                    'title'       => __( 'Nganluong Client Secret', 'nganluong-gateway' ),
                    'type'        => 'password',
                    'description' => __( 'Your Nganluong Client Secret for API integration.', 'nganluong-gateway' ),
                    'desc_tip'    => true,
                    'default'     => '',
                ),
                'receiver_email' => array(
                    'title'       => __( 'Receiver Email', 'nganluong-gateway' ),
                    'type'        => 'email',
                    'description' => __( 'Enter the email associated with your Nganluong account.', 'nganluong-gateway' ),
                    'desc_tip'    => true,
                    'default'     => get_option('admin_email'), // Default to WooCommerce admin email
                ),
                'return_url' => array(
                    'title'       => __( 'Return URL', 'nganluong-gateway' ),
                    'type'        => 'text',
                    'description' => __( 'The URL Nganluong will redirect to after payment.', 'nganluong-gateway' ),
                    'desc_tip'    => true,
                    'default'     => site_url( '/wc-api/spg_nganluong' ),
                ),
            );
        }

        public function payment_fields() {
            ?>
            <p><?php echo esc_html($this->description); ?></p>
            <fieldset>
                <p class="form-row form-row-wide">
                    <label for="spg_nganluong_bank_code"><?php _e('Chọn ngân hàng thanh toán:', 'nganluong-gateway'); ?></label>
                    <select name="spg_nganluong_bank_code" id="spg_nganluong_bank_code">
                        <option value="VCB">NH Ngoại thương Việt Nam (Vietcombank)</option>
                        <option value="DAB">NH Đông Á (DongA Bank)</option>
                        <option value="TCB">NH kỹ thương Việt Nam (Techcombank)</option>
                        <option value="MB">NH Quân đội (MB)</option>
                        <option value="VIB">NH Quốc Tế Việt Nam (VIB)</option>
                        <option value="AGB">NH Nông nghiệp và phát triển Nông thôn Việt Nam (Agribank)</option>
                        <option value="BIDV">NH đầu tư và phát triển Việt Nam (BIDV)</option>
                        <option value="OCB">NH Phương Đông</option>
                        <option value="SHNB">NH Shinhan Việt Nam (Shinhan Bank)</option>
                    </select>
                </p>
            </fieldset>
            <?php
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );

            if ( $order_total <= 20000 ) {
                wc_add_notice(__('The minimum order amount for Nganluong payment is 20,000 VND.', 'nganluong-gateway'), 'error');
                return array('result' => 'failure');
            }
        
            $nganluong_order = $this->create_nganluong_order( $order );
        
            if (!empty($nganluong_order['checkout_url'])) {
                $order->update_meta_data('_nganluong_token', $nganluong_order['token']);
                $order->save();
        
                return array(
                    'result'   => 'success',
                    'redirect' => $nganluong_order['checkout_url'],
                );
            } else {
                wc_add_notice(__('Failed to create Nganluong order.', 'nganluong-gateway'), 'error');
                return array('result' => 'failure');
            }
        }

        private function create_nganluong_order( $order ) {
            $url = 'https://www.nganluong.vn/checkout.api.nganluong.post.php';
        
            $bank_code = get_post_meta($order->get_id(), '_nganluong_bank_code', true);
            if (!$bank_code) {
                $bank_code = $this->get_option('bank_code'); // Use default bank if not set
            }

            error_log("client_id: " . $this->client_id);
            error_log("client_secret: " . $this->client_secret);
            error_log("md5 client_secret: " . md5($this->client_secret));
            error_log("receiver_email: " . $this->receiver_email);
            error_log("return_url: " . $this->return_url);
            error_log("notify_url: " . site_url('/wc-api/spg_nganluong'));

            $body = array(
                'merchant_id'       => trim(strval($this->client_id)),  // Retrieved from WooCommerce settings
                'merchant_password' => strval(md5($this->client_secret)), // Securely hash password
                'version'           => '3.1',
                'function'          => 'SetExpressCheckout',
                'receiver_email'    => $this->receiver_email, // Use stored email
                'order_code'        => $order->get_id(),
                'total_amount'      => $order->get_total(),
                'payment_method'    => 'IB_ONLINE', // Default payment method, can be dynamic
                'bank_code'         => $bank_code, // Use selected bank
                'payment_type'      => '1',
                'order_description' => 'Payment for Order #' . $order->get_id(),
                'tax_amount'        => '0',
                'discount_amount'   => '0',
                'fee_shipping'      => '0',
                'return_url'        => $this->return_url,
                'notify_url'        => site_url('/wc-api/spg_nganluong'),
                'cancel_url'        => wc_get_checkout_url(),
                'time_limit'        => '1440',
                'buyer_fullname'    => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                'buyer_email'       => $order->get_billing_email(),
                'buyer_mobile'      => $order->get_billing_phone() ? $order->get_billing_phone() : '',
                'buyer_address'     => $order->get_billing_address_1(),
                'cur_code'          => 'vnd',
                'lang_code'         => 'vi',
            );
        
            $response = wp_remote_post($url, array(
                'body'       => $body,
                'timeout'    => 45,
                'headers'    => array('Content-Type' => 'application/x-www-form-urlencoded'),
            ));
        
            if (is_wp_error($response)) {
                error_log('Nganluong API error: ' . $response->get_error_message());
                return [];
            }
        
            $response_body = wp_remote_retrieve_body($response);
            $parsed_data = simplexml_load_string($response_body);
        
            if (!$parsed_data || (string) $parsed_data->error_code !== '00') {
                error_log('Nganluong API returned an error: ' . json_encode($parsed_data));
                return [];
            }
        
            error_log("checkout_url: " . $parsed_data->checkout_url);

            return [
                'checkout_url' => (string) $parsed_data->checkout_url,
                'token'        => (string) $parsed_data->token,
            ];
        }

        public function handle_return() {
            error_log('$order_code: ' . $_GET['order_code']);

            if ( !isset($_GET['order_code']) || !isset($_GET['token']) ) {
                wp_redirect(wc_get_checkout_url());
                exit;
            }
        
            $order_code = sanitize_text_field($_GET['order_code']);
            $token = sanitize_text_field($_GET['token']);

            $order = wc_get_order($order_code);
            error_log('$order: ' . $order);
        
            if (!$order) {
                wp_redirect(wc_get_checkout_url());
                exit;
            }
        
            $response = $this->verify_payment_status($token);
            error_log('nganluongVerifyPayment: ' . $response);
            
            if ($response && isset($response['error_code']) && $response['error_code'] === "00") {
                $order->payment_complete();
                $order->update_status('completed'); // ✅ Mark order as COMPLETED
                $order->add_order_note("Payment completed via Nganluong. Transaction ID: " . $token);
                wp_redirect($this->get_return_url($order));
                exit;
            }
        
            $order->add_order_note("Payment verification failed with error: " . ($response['description'] ?? 'Unknown error'));
            wp_redirect(wc_get_checkout_url());
            exit;
        }
        
        private function verify_payment_status($token) {
            $body = array(
                'merchant_id'       => $this->client_id,
                'merchant_password' => md5($this->client_secret), // Check API docs if MD5 is needed
                'version'           => '3.1',
                'function'          => 'GetTransactionDetail',
                'token'             => $token,
            );
        
            $response = wp_remote_post('https://www.nganluong.vn/checkout.api.nganluong.post.php', array(
                'body'      => http_build_query($body),
                'timeout'   => 45,
                'headers'   => array('Content-Type' => 'application/x-www-form-urlencoded'),
            ));
        
            if (is_wp_error($response)) {
                return false;
            }
        
            $xml = simplexml_load_string(wp_remote_retrieve_body($response));
            return json_decode(json_encode($xml), true);
        }
    }
}
