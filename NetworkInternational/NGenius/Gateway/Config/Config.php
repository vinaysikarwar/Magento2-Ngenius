<?php

namespace NetworkInternational\NGenius\Gateway\Config;

/**
 * Class Config
 */
class Config extends \Magento\Payment\Gateway\Config\Config
{
    /*
     * Payment code
     */

    const CODE = 'ngeniusonline';
    /*
     * Config tags
     */
    const ENVIRONMENT = 'environment';
    const ACTIVE = 'active';
    const OUTLET_REF = 'outlet_ref';
    const API_KEY = 'api_key';
    const PAYMENT_ACTION = 'payment_action';
    const UAT_IDENTITY_URL = 'uat_identity_url';
    const LIVE_IDENTITY_URL = 'live_identity_url';
    const UAT_API_URL = 'uat_api_url';
    const LIVE_API_URL = 'live_api_url';
    const TOKEN_ENDPOINT = '/identity/auth/access-token';
    const ORDER_ENDPOINT = 'order_endpoint';
    const FETCH_ENDPOINT = 'fetch_endpoint';
    const CAPTURE_ENDPOINT = 'capture_endpoint';
    const REFUND_ENDPOINT = 'refund_endpoint';
    const VOID_ENDPOINT = 'void_auth_endpoint';
    const DEBUG = 'debug';

    /**
     * Gets value of configured environment.
     * Possible values: live or uat.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getEnvironment($storeId = null)
    {
        return $this->getValue(Config::ENVIRONMENT, $storeId);
    }

    /**
     * Gets Api Key.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiKey($storeId = null)
    {
        return $this->getValue(Config::API_KEY, $storeId);
    }

    /**
     * Gets Outlet Reference ID.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOutletReferenceId($storeId = null)
    {
        return $this->getValue(Config::OUTLET_REF, $storeId);
    }

    /**
     * Check is active.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isActive($storeId = null)
    {
        return (bool) $this->getValue(Config::ACTIVE, $storeId);
    }

    /**
     * get payment action.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function get_payment_action($storeId = null)
    {
        return $this->getValue(Config::PAYMENT_ACTION, $storeId);
    }

    /**
     * Check is complete.
     *
     * @param int|null $storeId
     * @return bool
     */
    public function isComplete($storeId = null)
    {

        $complete = false;
        if (!empty($this->getApiKey($storeId)) && !empty($this->getOutletReferenceId($storeId))) {
            $complete = true;
        }

        return $complete;
    }

    /**
     * Gets API URL.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getApiUrl($storeId = null)
    {

        $value = Config::UAT_API_URL;

        if($this->getEnvironment($storeId) == "live"){
            $value = Config::LIVE_API_URL;
        }

        return $this->getValue($value, $storeId);
    }

    /**
     * Gets token request URL.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getTokenRequestURL($storeId = null)
    {
        return $this->getApiUrl($storeId) . self::TOKEN_ENDPOINT;
    }

    /**
     * Gets order request URL.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrderRequestURL($storeId = null,$action)
    {
        $endpoint = sprintf($this->getValue(Config::ORDER_ENDPOINT, $storeId), $this->getOutletReferenceId($storeId));
        return $this->getApiUrl($storeId) . $endpoint."?action=$action";
    }

    /**
     * Gets fetch URL.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getFetchRequestURL($orderRef, $storeId = null)
    {
        $endpoint = sprintf($this->getValue(Config::FETCH_ENDPOINT, $storeId), $this->getOutletReferenceId($storeId), $orderRef);
        return $this->getApiUrl($storeId) . $endpoint;
    }

    /**
     * Checks debug on.
     *
     * @param int|null $storeId
     * @return string
     */
    public function isDebugOn($storeId = null)
    {
        return (bool) $this->getValue(Config::DEBUG, $storeId);
    }

    /**
     * Gets capture URL.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrderCaptureURL($orderRef, $paymentRef, $storeId = null)
    {
        $endpoint = sprintf($this->getValue(Config::CAPTURE_ENDPOINT, $storeId), $this->getOutletReferenceId($storeId), $orderRef, $paymentRef);
        return $this->getApiUrl($storeId) . $endpoint;
    }

    /**
     * Gets refund URL.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrderRefundURL($orderRef, $paymentRef, $transactionId, $storeId = null)
    {
        $endpoint = sprintf($this->getValue(Config::REFUND_ENDPOINT, $storeId), $this->getOutletReferenceId($storeId), $orderRef, $paymentRef, $transactionId);
        return $this->getApiUrl($storeId) . $endpoint;
    }

    /**
     * Gets void URL.
     *
     * @param int|null $storeId
     * @return string
     */
    public function getOrderVoidURL($orderRef, $paymentRef, $storeId = null)
    {
        $endpoint = sprintf($this->getValue(Config::VOID_ENDPOINT, $storeId), $this->getOutletReferenceId($storeId), $orderRef, $paymentRef);
        return $this->getApiUrl($storeId) . $endpoint;
    }
}
