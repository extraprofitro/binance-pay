<?php

namespace Zen\Payment;

/**
 * Class Paywall
 *
 * @package Zen\Payment
 */
class Paywall
{

    /**
     * DO NOT CHANGE ORDER OF CREATED KEYS - IT'S REQUIRED TO CALCULATE SIGNATURE
     * @param int $amount
     * @param string $currency
     * @param string $orderId
     * @param string $customerFirstName
     * @param string $customerLastName
     * @param string $customerEmail
     * @param string $urlSuccess
     * @param string $urlFailure
     * @param string $urlReturn
     * @param string $urlIpn
     * @param array $items
     * @param string $pluginName
     * @param string $pluginVersion
     * @param string $platformName
     * @param string $platformVersion
     *
     * @return array
     */
    public static function prepareOrderData(
        $amount, $currency, $orderId, $customerFirstName, $customerLastName,
        $customerEmail, $urlSuccess, $urlFailure, $urlReturn, $urlIpn, $items,
        $terminalId, $paywallSecret,
        $pluginName, $pluginVersion, $platformName, $platformVersion
    )
    {
        $data = [];

	    $data['amount'] = strval($amount);
        $data['currency'] = $currency;
        $data['customer']['email'] = $customerEmail;
        $data['customer']['firstName'] = $customerFirstName;
        $data['customer']['lastName'] = $customerLastName;
        $data['customIpnUrl'] = $urlIpn;

        foreach ($items as $key => $value) {
            $data['items'][$key]['lineAmountTotal'] = strval($value['lineAmountTotal']);

            if (isset($value['name']) && $value['name']) {
                $data['items'][$key]['name'] = $value['name'];
            }

            $data['items'][$key]['price'] = strval($value['price']);
            $data['items'][$key]['quantity'] = strval($value['quantity']);
        }

        $data['merchantTransactionId'] = $orderId . '#' . uniqid();
        $data['sourceAdditionalData']['platformName'] = $platformName;
        $data['sourceAdditionalData']['platformVersion'] = $platformVersion;
        $data['sourceAdditionalData']['pluginName'] = $pluginName;
        $data['sourceAdditionalData']['pluginVersion'] = $pluginVersion;
        $data['terminalUuid'] = trim($terminalId);
        $data['urlFailure'] = $urlFailure;
        $data['urlRedirect'] = $urlReturn;
        $data['urlSuccess'] = $urlSuccess;

        $signature = self::createSignature($data, trim($paywallSecret));
        $data['signature'] = $signature;

        return $data;
    }

    /**
     * @param array $orderData
     * @param string $serviceKey
     * @param string $hashMethod
     *
     * @return string|bool
     */
    private static function createSignature($orderData, $serviceKey, $hashMethod = 'sha256')
    {
        $isHashMethodSupported = Util::getHashMethod($hashMethod);

        if (!$isHashMethodSupported || !is_array($orderData)) {
            return false;
        }

        $hashData = self::prepareHashData($orderData);

        return Util::hashSignature($hashMethod, $hashData, $serviceKey) . ';' . $hashMethod;
    }

    /**
     * @param array $data
     * @param string $prefix
     *
     * @return string
     */
    public static function prepareHashData($data, $prefix = '')
    {
        $hashData = [];

        foreach ($data as $key => $value) {

            if ($prefix) {
                $key = $prefix . (is_numeric($key) ? ('[' . $key . ']') : ('.' . $key));
            }

            if (is_array($value)) {
                $hashData[] = self::prepareHashData($value, $key);
            } else {
	            $hashData[] = mb_strtolower($key, 'UTF-8') . '=' . mb_strtolower($value, 'UTF-8');
            }
        }

        sort($hashData, SORT_STRING);

        return implode('&', $hashData);
    }

}
