<?php

namespace Zen\Payment;

use Exception;

/**
 * Class Notification
 *
 * @package Zen\Payment
 */
class Notification
{

    // region notification codes
    const NC_OK = 0;
    const NC_INVALID_SIGNATURE = 1;
    const NC_SERVICE_ID_NOT_MATCH = 2;
    const NC_ORDER_NOT_FOUND = 3;
    const NC_INVALID_SIGNATURE_HEADERS = 4;
    const NC_EMPTY_NOTIFICATION = 5;
    const NC_NOTIFICATION_IS_NOT_JSON = 6;
    const NC_INVALID_JSON_STRUCTURE = 7;
    const NC_INVALID_ORDER_STATUS = 8;
    const NC_AMOUNT_NOT_MATCH = 9;
    const NC_UNHANDLED_STATUS = 10;
    const NC_ORDER_STATUS_NOT_CHANGED = 11;
    const NC_CART_NOT_FOUND = 12;
    const NC_ORDER_STATUS_IS_NOT_SETTLED_ORDER_ARRANGEMENT_AFTER_IPN = 13;
    const NC_ORDER_EXISTS_ORDER_ARRANGEMENT_AFTER_IPN = 14;
    const NC_REQUEST_IS_NOT_POST = 15;
    const NC_MISSING_ORDER_ID_IN_POST = 16;
    const NC_CURL_IS_NOT_INSTALLED = 17;
    const NC_MISSING_SIGNATURE_IN_POST = 18;
    const NC_UNKNOWN = 100;
    // endregion

    // region notification status
    const NS_OK = 'ok';
    const NS_ERROR = 'error';
    // endregion

    // region transaction type
    const TRT_PURCHASE = 'TRT_PURCHASE';
    const TRT_REFUND = 'TRT_REFUND';
    // endregion

    // region statuses Zen
    const STATUS_ACCEPTED = 'ACCEPTED';
    const STATUS_REJECTED = 'REJECTED';
    // endregion

    // region hash method
    const HASH_METHOD = 'sha256';
    // endregion

    /**
     * @var string
     */
    private $ipnSecret = '';

    /**
     * Notification constructor.
     *
     * @param string $ipnSecret
     */
    public function __construct($ipnSecret)
    {
        $this->ipnSecret = $ipnSecret;
    }

    /**
     * @param string $status
     **
     *
     * @return string
     */
    public static function formatResponse($status)
    {
        $response = [
            'status' => $status,
        ];

        // region add additional data for some cases and set proper header
        switch ($status) {
            case self::NS_OK:

                header('HTTP/1.1 200 OK');
                break;
            case self::NS_ERROR:

                header('HTTP/1.1 404 Not Found');
                break;
            default:

                break;
        }
        // endregion

        header('Content-Type: application/json');

        return json_encode($response);
    }

    /**
     * @param array $payloadDecoded
     * @param int $amount
     * @param string $currency
     *
     * @return bool
     */
    public static function checkRequestAmount($payloadDecoded, $amount, $currency)
    {

        return (float)$payloadDecoded['amount'] === (float)$amount && $payloadDecoded['currency'] === $currency;
    }

    /**
     * Verify notification body and signature
     *
     * @return bool|array
     * @throws Exception
     */
    public function checkRequest()
    {

        if (!isset($_SERVER['CONTENT_TYPE']) || strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== 0) {

            return self::NC_INVALID_SIGNATURE_HEADERS;
        }

        $payload = file_get_contents('php://input', true);

        if (!$payload) {

            return self::NC_EMPTY_NOTIFICATION;
        }

        if (!Util::isJson($payload)) {

            return self::NC_NOTIFICATION_IS_NOT_JSON;
        }

        try {

            Validate::notification($payload);
        } catch (Exception $e) {

            return self::NC_INVALID_JSON_STRUCTURE;
        }

        $payloadDecoded = json_decode($payload, true);

        if ($payloadDecoded['hash'] !== strtoupper(Util::hashSignature(
                self::HASH_METHOD,
                $payloadDecoded['merchantTransactionId'] . $payloadDecoded['currency'] . $payloadDecoded['amount'] . $payloadDecoded['status'],
                $this->ipnSecret))
        ) {

            return self::NC_INVALID_SIGNATURE;
        }

        return $payloadDecoded;
    }
}
