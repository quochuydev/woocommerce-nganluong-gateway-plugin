<?php
/**
 * Plugin Name: nganluong gateway
 * Plugin URI: https://portfolio.quochuy.dev/nganluong-gateway
 * Description: A custom WooCommerce payment gateway to integrate nganluong.
 * Version: 1.0.1
 * Author: @quochuydev
 * Author URI: https://portfolio.quochuy.dev
 * License: MIT License
 * Text Domain: nganluong-gateway
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

add_filter( 'woocommerce_payment_gateways', 'add_nganluong_gateway_class' );

function add_nganluong_gateway_class( $gateways ) {
    $gateways[] = 'SPG_Nganluong_Gateway';
    return $gateways;
}

add_action( 'plugins_loaded', 'init_nganluong_gateway_class' );

add_action('woocommerce_checkout_process', function() {
    $chosen_payment_method = WC()->session->get('chosen_payment_method');
    $bank_code = sanitize_text_field($_POST['nganluong_bank_code']);
    $payment_method_type = sanitize_text_field($_POST['payment_method_type']);
    $order_total = WC()->cart->total;

    if ($chosen_payment_method === 'nganluong') {
        if($payment_method_type === 'IB_ONLINE') {
            if ($order_total < 20000) {
                wc_add_notice(__('Số tiền tối thiểu để thanh toán Online Banking là 20,000 VND.', 'nganluong-gateway'), 'error');
                return array('result' => 'failure');
            }
        }

        if($payment_method_type === 'QRCODE247') {
            if ($order_total < 50000) {
                wc_add_notice(__('Số tiền tối thiểu để thanh toán QR Code là 50,000 VND.', 'nganluong-gateway'), 'error');
                return array('result' => 'failure');
            }
        }
    }
});

add_action('woocommerce_checkout_update_order_meta', function($order_id) {
    if (!empty($_POST['nganluong_bank_code'])) {
        update_post_meta($order_id, '_nganluong_bank_code', sanitize_text_field($_POST['nganluong_bank_code']));
    }
});

add_action('woocommerce_order_details_after_order_table', 'display_qr_code_checkout');

function display_qr_code_checkout($order_id) {
    $order = wc_get_order($order_id);

    if($order->get_status() !== 'completed') {
        $qr_code = $order->get_meta('_nganluong_qrcode_image');
    
        if ($qr_code) {
            echo '<div class="nganluong-qrcode-container" style="text-align:center;margin-top:20px;">';
            echo '<h3>' . __('Quét mã thanh toán', 'nganluong-gateway') . '</h3>';
            echo '<img src="'.$qr_code.'" alt="QR Code" style="max-width:200px;height:auto;"/>';
            echo '<p style="margin-top:10px;">' . __('Nếu đã thanh toán vui lòng refresh lại trình duyệt', 'nganluong-gateway') . '</p>';
            echo '</div>';
        }
    }
}

function init_nganluong_gateway_class() {
    class SPG_Nganluong_Gateway extends WC_Payment_Gateway {

        public function __construct() {
            $this->id                 = 'nganluong';
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
            $this->receiver_email   = $this->get_option( 'receiver_email' );

            add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
            add_action( 'woocommerce_api_' . $this->id, array( $this, 'handle_return' ) );
        }

        public function init_form_fields() {
            $this->bank_code_options = array(
                'VCB' => 'Ngân hàng Ngoại thương Việt Nam (Vietcombank)',
                'DAB' => 'Ngân hàng Đông Á (DongA Bank)',
                'TCB' => 'Ngân hàng kỹ thương Việt Nam (Techcombank)',
                'MB' => 'Ngân hàng Quân đội (MB)',
                'VIB' => 'Ngân hàng Quốc Tế Việt Nam (VIB)',
                'AGB' => 'Ngân hàng Nông nghiệp và phát triển Nông thôn Việt Nam (Agribank)',
                'BIDV' => 'Ngân hàng đầu tư và phát triển Việt Nam (BIDV)',
                'OCB' => 'Ngân hàng Phương Đông',
                'SHNB' => 'Ngân hàng Shinhan Việt Nam (Shinhan Bank)',	
            );

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
                ),
                'description' => array(
                    'title'       => __( 'Description', 'nganluong-gateway' ),
                    'type'        => 'textarea',
                    'default'     => __( 'You will be redirected to Nganluong to complete your purchase.', 'nganluong-gateway' ),
                ),
                'bank_code' => array(
                    'title'       => __( 'Mã ngân hàng tạo QR Code thanh toán', 'nganluong-gateway' ),
                    'type'        => 'text',
                    'description' => __( 'Mã ngân hàng tạo QR Code thanh toán.', 'nganluong-gateway' ),
                    'default'     => 'VCB',
                ),
                'client_id' => array(
                    'title'       => __( 'Nganluong Client ID', 'nganluong-gateway' ),
                    'type'        => 'text',
                    'description' => __( 'Your Nganluong Client ID for API integration.', 'nganluong-gateway' ),
                    'default'     => '',
                ),
                'client_secret' => array(
                    'title'       => __( 'Nganluong Client Secret', 'nganluong-gateway' ),
                    'type'        => 'password',
                    'description' => __( 'Your Nganluong Client Secret for API integration.', 'nganluong-gateway' ),
                    'default'     => '',
                ),
                'receiver_email' => array(
                    'title'       => __( 'Receiver Email', 'nganluong-gateway' ),
                    'type'        => 'email',
                    'description' => __( 'Enter the email associated with your Nganluong account.', 'nganluong-gateway' ),
                    'default'     => get_option('admin_email'), // Default to WooCommerce admin email
                ),
            );
        }

        public function payment_fields() {
            ?>
            <p><?php echo esc_html($this->description); ?></p>
            <div class="checkout__payment_method_type">
                <p class="form-row form-row-wide">
                    <label for="payment_method_type"><?php _e('Chọn phương thức thanh toán:', 'nganluong-gateway'); ?></label>
                    <select name="payment_method_type" id="payment_method_type">
                        <option value="IB_ONLINE">Online Banking</option>
                        <option value="QRCODE247">QR Code 247</option>
                    </select>
                </p>
            </div>
            <div class="checkout__nganluong_bank_code">
                <p class="form-row form-row-wide">
                    <label for="nganluong_bank_code"><?php _e('Chọn ngân hàng thanh toán:', 'nganluong-gateway'); ?></label>
                    <select name="nganluong_bank_code" id="nganluong_bank_code">
                        <?php
                        $default_bank = esc_attr($this->get_option('bank_code'));
                        foreach ($this->bank_code_options as $code => $name) {
                            printf('<option value="%s" %s>%s</option>', esc_attr($code), selected($default_bank, $code, false), esc_html($name));
                        }
                        ?>
                    </select>
                </p>
            </div>
            <script>
                (function($) {
                    $(document).ready(function() {
                        function toggleBankCode() {
                            let paymentMethod = $("#payment_method_type").val();
                            let bankCodeField = $(".checkout__nganluong_bank_code");

                            if (paymentMethod === "QRCODE247") {
                                bankCodeField.hide();
                            } else {
                                bankCodeField.show();
                            }
                        }
                        $("#payment_method_type").change(toggleBankCode);
                        toggleBankCode();
                    });
                })(jQuery);
            </script>
            <?php
        }

        public function process_payment( $order_id ) {
            $order = wc_get_order( $order_id );
            $payment_method_type = sanitize_text_field($_POST['payment_method_type']);
            $order_total = $order->get_total();

            error_log("payment_method_type: " . $payment_method_type);

            if ($payment_method_type === 'IB_ONLINE') {
                if ($order_total < 20000 ) {
                    wc_add_notice(__('Số tiền tối thiểu để thanh toán Online Banking là 20,000 VND.', 'nganluong-gateway'), 'error');
                    return array('result' => 'failure');
                }

                $nganluong_order = $this->create_nganluong_online_banking_order( $order );
        
                if (!empty($nganluong_order['checkout_url'])) {
                    $order->update_meta_data('_nganluong_token', $nganluong_order['token']);
                    $order->save();
            
                    return array(
                        'result'   => 'success',
                        'redirect' => $nganluong_order['checkout_url'],
                    );
                }
            }

            if ($payment_method_type === 'QRCODE247') {
                if ($order_total < 50000 ) {
                    wc_add_notice(__('Số tiền tối thiểu để thanh toán QR Code là 50,000 VND.', 'nganluong-gateway'), 'error');
                    return array('result' => 'failure');
                }

                $nganluong_order = $this->create_nganluong_qr_code_order( $order );
        
                if (!empty($nganluong_order['qrcode_image'])) {
                    $order->update_meta_data('_nganluong_qrcode_image', $nganluong_order['qrcode_image']);
                    $order->update_meta_data('_nganluong_token', $nganluong_order['token']);
                    $order->save();
            
                    return array(
                        'result'   => 'success',
                        'redirect' => $this->get_return_url($order)
                    );
                }
            }

            wc_add_notice(__('Failed to create Nganluong order.', 'nganluong-gateway'), 'error');
            return array('result' => 'failure');
        }

        private function create_nganluong_online_banking_order( $order ) {
            $url = 'https://www.nganluong.vn/checkout.api.nganluong.post.php';
            $bank_code = get_post_meta($order->get_id(), '_nganluong_bank_code', true);

            if (!$bank_code) {
                wc_add_notice(__('Please select a bank for payment.', 'nganluong-gateway'), 'error');
                return array('result' => 'failure');
            }

            error_log("receiver_email: " . $this->receiver_email);
            error_log("bank_code_online_banking: " . $bank_code);
            error_log("return_url: " . site_url('/wc-api/nganluong'));
        
            $body = array(
                'merchant_id'       => trim(strval($this->client_id)),  // Retrieved from WooCommerce settings
                'merchant_password' => strval(md5($this->client_secret)), // Securely hash password
                'version'           => '3.1',
                'function'          => 'SetExpressCheckout',
                'receiver_email'    => $this->receiver_email, // Use stored email
                'order_code'        => $order->get_id(),
                'total_amount'      => $order->get_total(),
                'payment_method'    => 'IB_ONLINE',
                'bank_code'         => $bank_code,
                'payment_type'      => '1',
                'order_description' => 'Payment for Order #' . $order->get_id(),
                'tax_amount'        => '0',
                'discount_amount'   => '0',
                'fee_shipping'      => '0',
                'return_url'        => site_url('/wc-api/nganluong'),
                'notify_url'        => site_url('/wc-api/nganluong'),
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

        private function create_nganluong_qr_code_order( $order ) {
            $url = 'https://www.nganluong.vn/checkoutseamless.api.nganluong.post.php';
            $bank_code = $this->get_option('bank_code');
            
            error_log("receiver_email: " . $this->receiver_email);
            error_log("bank_code_qr_code: " . $bank_code);
            error_log("return_url: " . site_url('/wc-api/nganluong'));

            $body = array(
                'merchant_id'       => trim(strval($this->client_id)),  // Retrieved from WooCommerce settings
                'merchant_password' => strval(md5($this->client_secret)), // Securely hash password
                'version'           => '3.2',
                'function'          => 'SetExpressCheckout',
                'receiver_email'    => $this->receiver_email, // Use stored email
                'order_code'        => $order->get_id(),
                'total_amount'      => $order->get_total(),
                'payment_method'    => 'QRCODE247',
                'bank_code'         => $bank_code,
                'payment_type'      => '1',
                'order_description' => 'Payment for Order #' . $order->get_id(),
                'tax_amount'        => '0',
                'discount_amount'   => '0',
                'fee_shipping'      => '0',
                'return_url'        => site_url('/wc-api/nganluong'),
                'notify_url'        => site_url('/wc-api/nganluong'),
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
            $xml = new SimpleXMLElement($response_body);
            $qrcode_image = (string) $xml->qr247_data->qrcode_image;
            $token = (string) $xml->token;
            $error_code = (string) $xml->error_code;

            error_log("qrcode_image: " . $qrcode_image);
            error_log("token: " . $token);
        
            if ((string) $error_code !== '00') {
                error_log('Nganluong API returned an error: ' . json_encode($response_body));
                return [];
            }
        
            return [
                'qrcode_image' => (string) $qrcode_image,
                'token'        => (string) $token,
            ];
        }

        public function handle_return() {
            $order_id = sanitize_text_field($_GET['order_code']);
            $token = sanitize_text_field($_GET['token']);

            error_log('$order_id: ' . $order_id);
            error_log('$token: ' . $token);

            if ( !isset($order_id) || !isset($token) ) {
                return array('result' => 'failure');
            }

            $order = wc_get_order($order_id);
            error_log('$order: ' . $order);
        
            if (!$order) {
                wp_redirect(wc_get_checkout_url());
                exit;
            }
        
            $response = $this->verify_payment_status($token);
            error_log('verifyPayment: ' . $response);
            
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
                'merchant_password' => md5($this->client_secret),
                'version'           => '3.2',
                'function'          => 'GetTransactionDetail',
                'token'             => $token,
            );
        
            $response = wp_remote_post('https://www.nganluong.vn/checkoutseamless.api.nganluong.post.php', array(
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
