<?php

namespace NetworkInternational\NGenius\Gateway\Request;

use NetworkInternational\NGenius\Gateway\Config\Config;
use Magento\Payment\Gateway\Request\BuilderInterface;
use Magento\Framework\Exception\CouldNotSaveException;
use NetworkInternational\NGenius\Gateway\Request\TokenRequest;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Checkout\Model\Session;
use Magento\Payment\Gateway\Helper\SubjectReader;
use Magento\Framework\UrlInterface;
use Magento\Payment\Helper\Formatter;

/**
 * Class AbstractRequest
 */
abstract class AbstractRequest implements BuilderInterface
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
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var UrlInterface
     */
    protected $urlBuilder;

    /**
     * AbstractRequest constructor.
     *
     * @param Config $config
     * @param TokenRequest $tokenRequest
     * @param StoreManagerInterface $storeManager
     * @param Session $checkoutSession
     * @param UrlInterface $urlBuilder
     */
    public function __construct(
        Config $config,
        TokenRequest $tokenRequest,
        StoreManagerInterface $storeManager,
        Session $checkoutSession,
        UrlInterface $urlBuilder
    ) {
        $this->config = $config;
        $this->tokenRequest = $tokenRequest;
        $this->storeManager = $storeManager;
        $this->checkoutSession = $checkoutSession;
        $this->urlBuilder = $urlBuilder;
    }

    /**
     * Builds ENV request
     *
     * @param array $buildSubject
     * @throws CouldNotSaveException
     * @return array
     */
    public function build(array $buildSubject)
    {

        $paymentDO = SubjectReader::readPayment($buildSubject);
        $paymentDO->getPayment()->setIsTransactionPending(true);
        $order = $paymentDO->getOrder();
        $storeId = $order->getStoreId();
        $amount = $this->formatPrice(SubjectReader::readAmount($buildSubject)) * 100;

        if ($this->config->isComplete($storeId)) {
            $this->setTableData($order);

            return[
                'token' => $this->tokenRequest->getAccessToken($storeId),
                'request' => $this->getBuildArray($order, $storeId, $amount)
            ];
        } else {
            throw new CouldNotSaveException(__('Invalid configuration.'));
        }
    }

    /**
     * Set Table Data
     *
     * @param object $order
     * @return null
     */
    protected function setTableData($order)
    {
        $data = [
            'order_id' => $order->getOrderIncrementId(),
            'currency' => $order->getCurrencyCode(),
            'amount' => $order->getGrandTotalAmount()
        ];
        $this->checkoutSession->setTableData($data);
    }

    /**
     * Gets array of data for API request
     *
     * @param object $order
     * @param int $storeId
     * @param float $amount
     * @return array
     */
    abstract public function getBuildArray($order, $storeId, $amount);
}
