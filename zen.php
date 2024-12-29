<?php

use Payment\Exceptions\NotSupportedHashMethod;
use Payment\Exceptions\OrderIsNotAnArray;
use Zen\Payment\Api;
use Zen\Payment\CartData;
use Zen\Payment\Notification;
use Zen\Payment\Paywall;
use Zen\Payment\Util;

/**
 * Class ControllerExtensionPaymentZen
 */
class ControllerExtensionPaymentZen extends Controller
{

    /**
     * plugin version
     */
    const PLUGIN_VERSION = '2.1.0';

    /**
     * plugin name
     */
    const PLUGIN_NAME = 'Zen';

    /**
     * platform name
     */
    const PLATFORM_NAME = 'OpenCart';

    /**
     * @return mixed
     */
    public function index()
    {

        $this->language->load('extension/payment/zen');

        include_once(DIR_SYSTEM . 'library/payment-core/src/Util.php');

        $this->load->model('setting/setting');

        $data = [
            'url_pay' => $this->url->link('extension/payment/zen/pay', '', true),
        ];

        return $this->load->view('extension/payment/zen', $data);
    }

    /**
     * initiate form payment, make payment
     *
     * @throws NotSupportedHashMethod
     * @throws OrderIsNotAnArray
     */
    public function pay()
    {

        include_once(DIR_SYSTEM . 'library/payment-core/src/Util.php');
        include_once(DIR_SYSTEM . 'library/payment-core/src/Paywall.php');
        include_once(DIR_SYSTEM . 'library/payment-core/src/Api.php');

        $this->load->model('checkout/order');
        $this->language->load('extension/payment/zen');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $api = new Api($this->getEnvironment());

        $order_data = Paywall::prepareOrderData(
            Util::convertAmount($order_info['total']),
            strtoupper($order_info['currency_code']),
            $order_info['order_id'],
            $order_info['payment_firstname'],
            $order_info['payment_lastname'],
            $order_info['email'],
            $this->url->link('checkout/success'),
            $this->url->link('checkout/failure'),
            $this->url->link('checkout/success'),
            HTTPS_SERVER . 'index.php?route=extension/payment/zen/notification',
            $this->getCart($order_info),
            $this->config->get('payment_zen_terminal_uuid'),
            $this->config->get('payment_zen_paywall_secret'),
            self::PLUGIN_NAME,
            self::PLUGIN_VERSION,
            self::PLATFORM_NAME,
            VERSION
        );

        $paymentRequest = $api->createPayment(json_encode($order_data));
        $paymentError = !$paymentRequest['success'];

        if ($paymentError) {
            $return['status'] = 'ERROR';
			$return['redirectUrl'] = $paymentRequest['data']['body']['error']['redirectUrl'];
        } else {
            $return['status'] = 'SUCCESS';
            $return['redirectUrl'] = $paymentRequest['body']['redirectUrl'];
            $return['body'] = json_encode($order_data);
        }

	    $this->model_checkout_order->addOrderHistory($order_info['order_id'], 01);
	    $this->load->language('checkout/cart');
	    $this->cart->clear();

	    unset($this->session->data['vouchers']);
	    unset($this->session->data['shipping_method']);
	    unset($this->session->data['shipping_methods']);
	    unset($this->session->data['payment_method']);
	    unset($this->session->data['payment_methods']);
	    unset($this->session->data['reward']);

        echo json_encode($return);
        exit();
    }

    /**
     * @return string
     */
    private function getEnvironment()
    {
        return Util::ENVIRONMENT_PRODUCTION;
    }

    /**
     * @param array $order
     *
     * @return array
     */
    private function getCart($order)
    {

        include_once(DIR_SYSTEM . 'library/payment-core/vendor/autoload.php');

        $this->load->model('catalog/product');

        $cart_data = new CartData();

        $cart_data->setAmount(Util::convertAmount($order['total'] * $order['currency_value']));

        foreach ($this->cart->getProducts() as $cart_item) {

            $price = $this->tax->calculate(
                $cart_item['price'],
                $cart_item['tax_class_id'],
                $this->config->get('config_tax'));

            if (isset($cart_item['special']) && $cart_item['special']) {
                $price = $this->tax->calculate(
                    $cart_item['special'],
                    $cart_item['tax_class_id'],
                    $this->config->get('config_tax'));
            }

            $price = Util::convertAmount($price * $order['currency_value']);

            $cart_data->addItem(
                $cart_item['name'],
                $price,
                $cart_item['quantity'],
                Util::convertAmount($price * $cart_item['quantity'])
            );
        }

        if (isset($this->session->data['shipping_method']) && $this->session->data['shipping_method']) {

            $shipping_cost = Util::convertAmount(
                $this->tax->calculate(
                    $this->session->data['shipping_method']['cost'],
                    $this->session->data['shipping_method']['tax_class_id'],
                    $this->config->get('config_tax')) * $order['currency_value']
            );

            $cart_data->addItem(
                $this->session->data['shipping_method']['title'],
                $shipping_cost,
                1,
                $shipping_cost
            );
        }

        $this->load->model('extension/total/coupon');

        $text_round = $this->language->get('text_round');

        if (isset($this->session->data['coupon'])) {
            $coupon_info = $this->model_extension_total_coupon->getCoupon($this->session->data['coupon']);

            if ($coupon_info['total'] > 0) {

                $discount = -Util::convertAmount(
                    $coupon_info['total'] * $order['currency_value']
                );

                $cart_data->addItem(
                    $text_round,
                    $discount,
                    1,
                    $discount
                );
            }
        }

        return $cart_data->getCartData($text_round);
    }

    /**
     * Support incoming notification (and validate)
     *
     * @throws Exception
     */
    public function notification()
    {

        include_once(DIR_SYSTEM . 'library/payment-core/vendor/autoload.php');

        $notification = new Notification(
            $this->config->get('payment_zen_ipn_secret')
        );

        // it can be order data or notification code - depends on verification notification
        $result_check_request_notification = $notification->checkRequest();

        if (is_int($result_check_request_notification)) {

            echo Notification::formatResponse(Notification::NS_ERROR);
            exit();
        }

        $this->load->model('checkout/order');

        if (!($order = $this->model_checkout_order->getOrder(
            Util::extractMerchantTransactionId(
                $result_check_request_notification['merchantTransactionId']
            )
        ))) {

            echo Notification::formatResponse(Notification::NS_ERROR);
            exit();
        }
        if (!($order['order_status_id'] === '1' || $order['order_status_id'] === '10')) {
            echo Notification::formatResponse(Notification::NS_ERROR);
            exit();
        }

        if (!Notification::checkRequestAmount($result_check_request_notification, $order['total'], strtoupper($order['currency_code']))) {
            echo Notification::formatResponse(Notification::NS_ERROR);
            exit();
        }

        switch ($result_check_request_notification['status']) {
            case Notification::STATUS_ACCEPTED:
                $this->model_checkout_order->addOrderHistory($order['order_id'], 02);
                echo Notification::formatResponse(Notification::NS_OK);
                exit();
            case Notification::STATUS_REJECTED:
                $this->model_checkout_order->addOrderHistory($order['order_id'], 10);
                echo Notification::formatResponse(Notification::NS_OK);
                exit();
            default:
                echo Notification::formatResponse(Notification::NS_OK);
                exit;
        }
    }
}
