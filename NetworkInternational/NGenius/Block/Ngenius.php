<?php

namespace NetworkInternational\NGenius\Block;

use Magento\Framework\Phrase;
use Magento\Payment\Block\ConfigurableInfo;
use Magento\Checkout\Model\Session;
use Magento\Sales\Api\Data\OrderInterface;
use NetworkInternational\NGenius\Gateway\Config\Config;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use NetworkInternational\NGenius\Gateway\Request\TokenRequest;
use Magento\Store\Model\StoreManagerInterface;
use NetworkInternational\NGenius\Gateway\Request\PurchaseRequest;
use Magento\Framework\App\Config\ScopeConfigInterface;
use NetworkInternational\NGenius\Gateway\Http\Client\TransactionPurchase;

/**
 * Class Info
 */
class Ngenius extends ConfigurableInfo
{

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var OrderInterface
     */
    protected $orderFactory;

    /**
     * @var TokenRequest
     */
    protected $tokenRequest;

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var PurchaseRequest
     */
    protected $_purchaseRequest;

    /**
     * @var ScopeConfigInterface
     */
    protected $_scopeConfig;

    protected $_transactionPurchase;

    /**
     * Ngenius constructor.
     *
     * @param Session $checkoutSession
     */
    public function __construct(
        OrderInterface $orderInterface,
        TokenRequest $tokenRequest,
        PurchaseRequest $purchaseRequest,
        ScopeConfigInterface $scopeConfig,
        TransactionPurchase $transactionPurchase,
        Session $checkoutSession
    ) {
        $this->checkoutSession = $checkoutSession;
        $this->orderFactory = $orderInterface;
        $this->tokenRequest = $tokenRequest;
        $this->_purchaseRequest = $purchaseRequest;
        $this->_scopeConfig = $scopeConfig;
        $this->_transactionPurchase = $transactionPurchase;
    }

    public function get_payment_url(){
        $checkoutSession = $this->checkoutSession;

        if($incrementId = $checkoutSession->getLastRealOrderId()) {
            $order = $this->orderFactory->loadByIncrementId($incrementId);
            $storeId = $order->getStoreId();
            $amount  = $order->getGrandTotal() * 100;

            $payment_action = $this->_scopeConfig->getValue('payment/ngeniusonline/payment_action');

            if ($payment_action == "order") {
                $request_data = array(
                    'token'   => $this->tokenRequest->getAccessToken($storeId),
                    'request' => $this->_purchaseRequest->getBuildArray($order, $storeId, $amount)
                );

                $data = $this->_transactionPurchase->placeRequest($request_data);

                if(isset($data['payment_url'])) {
                    return $data['payment_url'];
                }else{
                    throw new CouldNotSaveException(__('An error occurred in processing payment.'));
                }
            } else {
                throw new CouldNotSaveException(__('Invalid configuration.'));
            }
        }
    }
}
