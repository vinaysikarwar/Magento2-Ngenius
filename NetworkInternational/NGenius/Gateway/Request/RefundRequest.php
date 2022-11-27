<?php

namespace NetworkInternational\NGenius\Gateway\Request;

use NetworkInternational\NGenius\Gateway\Config\Config;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\Exception\LocalizedException;
use NetworkInternational\NGenius\Gateway\Request\TokenRequest;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Payment\Gateway\Helper\SubjectReader;
use NetworkInternational\NGenius\Model\CoreFactory;
use Magento\Payment\Helper\Formatter;
use Magento\Sales\Api\Data\TransactionInterface;
use Magento\Sales\Api\Data\OrderInterface;

/**
 * Class RefundRequest
 */
class RefundRequest implements BuilderInterface
{

    use Formatter;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var TokenRequest
     */
    protected $tokenRequest;

    /**
     * @var StoreManagerInterface
     */
    protected $storeManager;

    /**
     * @var CoreFactory
     */
    protected $coreFactory;

    /**
     * @var OrderInterface
     */
    protected $_orderInterface;

    /**
     * RefundRequest constructor.
     *
     * @param Config $config
     * @param TokenRequest $tokenRequest
     * @param StoreManagerInterface $storeManager
     * @param CoreFactory $coreFactory
     */
    public function __construct(
        Config $config,
        TokenRequest $tokenRequest,
        StoreManagerInterface $storeManager,
        CoreFactory $coreFactory,
        OrderInterface $orderInterface
    ) {
        $this->config          = $config;
        $this->tokenRequest    = $tokenRequest;
        $this->storeManager    = $storeManager;
        $this->coreFactory     = $coreFactory;
        $this->_orderInterface = $orderInterface;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     *
     * @return array
     * @throws CouldNotSaveException
     */
    public function build(array $buildSubject)
    {
        $paymentDO = SubjectReader::readPayment($buildSubject);
        $payment   = $paymentDO->getPayment();
        $order     = $paymentDO->getOrder();
        $storeId   = $order->getStoreId();

        $transactionId = $payment->getTransactionId();
        $txnId         = str_replace('-' . TransactionInterface::TYPE_REFUND, '', $transactionId);

        if ( ! $txnId) {
            throw new LocalizedException(__('No capture transaction to proceed refund.'));
        }

        $incrementId = $order->getOrderIncrementId();

        $collection = $this->coreFactory->create()->getCollection()->addFieldToFilter('order_id', $incrementId);
        $orderItem  = $collection->getFirstItem();

        $order_details = $this->_orderInterface->loadByIncrementId($incrementId);

        $token = $this->tokenRequest->getAccessToken($storeId);
        $refund_url = $this->get_refund_url($token,$orderItem->getReference());

        if ($this->config->isComplete($storeId)) {
            return [
                'token'   => $token,
                'request' => [
                    'data'   => [
                        'amount' => [
                            'currencyCode' => $order_details->getOrderCurrencyCode(),
                            'value'        => $this->formatPrice(SubjectReader::readAmount($buildSubject)) * 100
                        ]
                    ],
                    'method' => \Zend_Http_Client::POST,
                    'uri'    => $refund_url
                ]
            ];
        } else {
            throw new LocalizedException(__('Invalid configuration.'));
        }
    }

    /**
     * @return refund_url
     * Get response from api for order ref code end
     */
    public function get_refund_url($token,$order_ref)
    {
        $response = $this->get_response_api($token,$order_ref);

        if (isset($response->errors)) {
            return $response->errors[0]->message;
        }

        $cnpcapture = "cnp:capture";
        $cnprefund  = 'cnp:refund';

        $payment = $response->_embedded->payment[0];

        $refund_url = "";
        if ($payment->state == "PURCHASED" && isset($payment->_links->$cnprefund->href)) {
            $refund_url = $payment->_links->$cnprefund->href;
        } elseif ($payment->state == "CAPTURED" && isset($payment->_embedded->$cnpcapture[0]->_links->$cnprefund->href)) {
            $refund_url = $payment->_embedded->$cnpcapture[0]->_links->$cnprefund->href;
        } else {
            if (isset($payment->_links->$cnprefund->href)) {
                $refund_url = $payment->_embedded->$cnpcapture[0]->_links->$cnprefund->href;
            }
        }

        if(!$refund_url){
            throw new LocalizedException(__('Refund data not found.'));
        }

        return $refund_url;
    }

    public function get_response_api($token,$order_ref){
        $authorization = "Authorization: Bearer " . $token;
        $url = $this->config->getFetchRequestURL($order_ref);

        $headers = array(
            'Content-Type: application/vnd.ni-payment.v2+json',
            $authorization,
            'Accept: application/vnd.ni-payment.v2+json'
        );

        $ch         = curl_init();
        $curlConfig = array(
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
        );

        curl_setopt_array($ch, $curlConfig);
        $response = curl_exec($ch);

        return json_decode($response);
    }
}
