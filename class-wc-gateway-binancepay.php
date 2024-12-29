<?php
/*
Plugin Name:  Binance Pay Woocommerce
Plugin URI:
Description:  A payment gateway that allows your customers to pay with cryptocurrency via Binance
Version:      1.1.9
Author:       Binance
Author URI:
License:      GPLv3+
License URI:  https://www.gnu.org/licenses/gpl-3.0.html
Text Domain:  binance
Domain Path:  /languages
*/
if (!defined('ABSPATH')) {
    exit;
}

add_filter('woocommerce_payment_gateways', 'binance_pay_add_gateway_class');
function binance_pay_add_gateway_class($gateways)
{
    $gateways[] = 'Binance_Pay_Gateway';
    return $gateways;
}

add_action('plugins_loaded', 'binance_pay_init_gateway_class');


function binance_pay_init_gateway_class()
{

    class Binance_Pay_Gateway extends WC_Payment_Gateway
    {

        public function __construct()
        {
            $this->id = 'binance';
            $this->has_fields = false;
            $this->method_title = 'Binance Pay';
            $this->icon = apply_filters('woocommerce_binance_pay_icon', plugins_url('/assets/binance-logo.svg', __FILE__));
            $this->method_description = 'A payment gateway that sends your customers to Binance to pay with cryptocurrency.';

            $this->init_form_fields();
            $this->init_settings();
            $this->title = $this->get_option('title');
            $this->enabled = $this->get_option('enabled');
            $this->api_path = "https://bpay.binanceapi.com/binancepay/openapi/";
            $this->api_key = $this->get_option('api_key');
            $this->secret_key = $this->get_option('secret_key');
            $this->transaction_currency = $this->get_option('transaction_currency');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_wc_gateway_binance', array($this, 'binance_pay_handle_webhook'));
        }

        public function init_form_fields()
        {
            $this->form_fields = array(
                'enabled' => array(
                    'title' => 'Enable/Disable',
                    'label' => 'Enable Binance Pay Gateway',
                    'type' => 'checkbox',
                    'description' => '',
                    'default' => 'no'
                ),
                'api_key' => array(
                    'title' => 'API Key',
                    'type' => 'text'
                ),
                'secret_key' => array(
                    'title' => 'Secret Key',
                    'type' => 'password',
                ),
                'transaction_currency' => array(
                    'title' => 'Currency',
                    'type' => 'select',
                    'class' => 'wc-enhanced-select',
                    'description' => 'This is the currency used to create order in Binance Pay.  With fiat selection, your customers will pay you with equivalent USDT, and your settlement currency will be in USDT as well. However, if you are in the EU, your transactions must be in EUR. You will select EUR and receive settlements in EUR.
', 'woocommerce',
                    'default' => '',
                    'desc_tip' => true,
                    'custom_attributes' => array('required' => 'required'),
                    'options' => array(
                        'USDT' => 'USDT',
                        'USDC' => 'USDC',
                        'USD' => 'USD',
                        'EUR' => 'EUR',
                        'CHF' => 'CHF',
                        'GBP' => 'GBP',
                        'ARS' => 'ARS',
                        'CNY' => 'CNY',
                        'HKD' => 'HKD',
                        'NGN' => 'NGN',
                    ),
                )
            );
        }

        public function payment_scripts()
        {
        }

        public function validate_fields()
        {

        }

        /**
         * Get payment method title
         *
         * @return string
         */
        public function get_title()
        {
            return $this->method_title;
        }


        public function process_payment($order_id)
        {
            global $woocommerce;
            $order = new WC_Order($order_id);
            $result = $this->create_binance_order($order_id);
            if ($result['code'] != '000000') {
                wc_add_notice('Payment error:  ' . $result['errorMessage'], 'error');
                return array('result' => 'fail');
            }
            $detail = $result['data'];
            $order->update_status('on-hold', 'Awaiting check payment');
            $order->update_meta_data('binance_pay_prepay_id', $result['data']['prepayId']);
            $order->save();

            $woocommerce->cart->empty_cart();
            return array(
                'result' => 'success',
                'redirect' => $detail['universalUrl'],
            );
        }


        public function generate_random_string()
        {
            $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < 32; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            return $randomString;
        }

        public function create_binance_order($order_id)
        {
            $order = new WC_Order($order_id);
            $req = array(
                'env' => array('terminalType' => 'WEB'),
                'merchantTradeNo' => $order_id
            );
            $currency = $this->transaction_currency;
            if (empty($currency) || $currency == 'BUSD') {
                $currency = 'USDT';
            }
            if ($currency == 'USDT' || $currency == 'USDC') {
                $req['orderAmount'] = $order->get_total();
                $req['currency'] = $currency;
            } else {
                $req['fiatAmount'] = $order->get_total();
                $req['fiatCurrency'] = $currency;
            }
            $req['goods'] = array();
            $req['passThroughInfo'] = "wooCommerce-1.0";
            $req['goods']['goodsType'] = "02";
            $req['goods']['goodsCategory'] = "Z000";
            $referenceGoodsId = '';
            $goodsName = '';
            $index = 0;
            foreach ($order->get_items() as $item_id => $item) {
                $product_id = (string)$item->get_product_id();
                if ($index == 0) {
                    $referenceGoodsId = $product_id;
                    $goodsName = $item->get_name();
                } else {
                    $referenceGoodsId .= ', ' . $product_id;
                    $goodsName .= ', ' . $item->get_name();
                }
                $index++;
            }
            $req['goods']['referenceGoodsId'] = $referenceGoodsId;
            $req['goods']['goodsName'] = $goodsName;
            $req['returnUrl'] = $this->get_return_url($order);
            $req['cancelUrl'] = $order->get_cancel_order_url();
            $req['webhookUrl'] = esc_url(home_url('/')) . '?wc-api=wc_gateway_binance';
            $nonce = $this->generate_random_string();
            $body = json_encode($req);
            $microtime = microtime(true);
            $seconds = floor($microtime);
            $milliseconds = sprintf('%03d', ($microtime - $seconds) * 1000);
            $timestamp = $seconds . $milliseconds;
            $payload = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
            $secretKey = $this->secret_key;
            $signature = strtoupper(hash_hmac('sha512', $payload, $secretKey));
            $apiKey = $this->api_key;

            $headers = array(
                'Content-Type' => 'application/json',
                "BinancePay-Timestamp" => $timestamp,
                "BinancePay-Nonce" => $nonce,
                "BinancePay-Certificate-SN" => $apiKey,
                "BinancePay-Signature" => $signature
            );
            $args = array(
                'body' => json_encode($req),
                'timeout' => '60',
                'redirection' => '8',
                'httpversion' => '1.0',
                'blocking' => true,
                'headers' => $headers,
                'cookies' => array(),
            );
            $response = wp_remote_post($this->api_path . 'v2/order', $args);
            $responseBody = wp_remote_retrieve_body($response);
            error_log("binance response " . $responseBody);
            return json_decode($responseBody, true);
        }

        public function binance_pay_handle_webhook()
        {
            $body = file_get_contents('php://input');
            $timestamp = sanitize_text_field($_SERVER['HTTP_BINANCEPAY_TIMESTAMP']);
            $nonce = sanitize_text_field($_SERVER['HTTP_BINANCEPAY_NONCE']);
            $payload = $timestamp . "\n" . $nonce . "\n" . $body . "\n";
            $signature = sanitize_text_field($_SERVER['HTTP_BINANCEPAY_SIGNATURE']);
            if (!empty($body) && $this->validate_webhook($payload, $signature)) {
                $this->process_callback(json_decode($body, true));
                echo '{"returnCode":"SUCCESS"}';
                exit;
            } else {
                wp_die('Binance Webhook Request Failure', 'Binance Webhook', array('response' => 500));
            }
        }

        public function validate_webhook($payload, $signature)
        {
            $decodedSignature = base64_decode($signature);
            $pubKey = "-----BEGIN PUBLIC KEY-----
MIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEAwuF7PgDsNBuJVZ6HeVoH
T2Tqj22+eWLQ/kZbYj3CTgdvedFymj0Kqvxtwy3InVbU6t6g5UkjL+dMOAkt5GxL
NYI1uNy9g3+mifCDRXDArwvcKkB5jYu0R3WTNtf1ODicjpOf1NUqMZ+0t3jNwVAy
awvxlyxpX8gMa6OAMbzMtH3iskM52nu5mS57Xh4ryibwjIxd0ssb63gD2qH8jy60
AK/qgkijlysgEQDzYTk6X2x4t9BfVoOL3+yxkIiwnfL/KY9xkvSmWuAFIZqu4pY7
g+GXFiG50sSCe2BkBcSzIS56L1Qp/tSzDUl1+fQCGhA3BFY42/zTpvdjLLUgbRYZ
pJCu9Z4w0HsM118rKCxBZNveoc12oHXEbDMDy7y/c39KYNyniCH6iKPNzu6Zi8tb
XXN7KG9mUHUzstnafVd5QpqIumQUgE+JVTSrdx0YJy7OQeqeSoQeBeI7pr1gsRuH
x0pcniYOIRwmtZ27Ybkbk0zOu1vBmzE8hC8RAkE8Yz06T7quoa547FicUYQBvtkR
YLJDbSIjFLkfTFNOgV5VU92JfJvFji3F/nDVQ0gI6iuDktKYB0FNe1LZvKbDgPs+
J8/Pssd1DOW8XJbQXmJz8VCrubv/SdOYsy0lP0m/ZybEFjSVSWKT3xCpHVSDVJNm
rTUypediX9eeNMlfs0x/vmkCAwEAAQ==
-----END PUBLIC KEY-----";
            $result = openssl_verify($payload, $decodedSignature, $pubKey, OPENSSL_ALGO_SHA256);
            if ($result === 1) {
                return true;
            }
            return false;
        }

        public function process_callback($body)
        {
            if ($body["bizType"] == "PAY") {
                $data = json_decode($body["data"], true);
                $order_id = $data["merchantTradeNo"];
                $order = wc_get_order($order_id);
                if ($body["bizStatus"] == "PAY_SUCCESS") {
                    $order->update_status('processing', __('binance payment success', 'binance'));
                    $order->payment_complete();
                }
                if ($body["bizStatus"] == "PAY_CLOSED") {
                    $order->update_status('cancelled', __('binance payment expired.', 'binance'));
                }
            }
        }
    }
}