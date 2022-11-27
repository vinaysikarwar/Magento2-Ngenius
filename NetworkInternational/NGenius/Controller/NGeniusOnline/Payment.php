<?php

namespace NetworkInternational\NGenius\Controller\NGeniusOnline;

use Magento\Framework\App\Action\Context;
use NetworkInternational\NGenius\Gateway\Config\Config;
use NetworkInternational\NGenius\Gateway\Request\TokenRequest;
use Magento\Store\Model\StoreManagerInterface;
use NetworkInternational\NGenius\Gateway\Http\TransferFactory;
use NetworkInternational\NGenius\Gateway\Http\Client\TransactionFetch;
use NetworkInternational\NGenius\Model\CoreFactory;
use Magento\Sales\Model\Order\Payment\Transaction\BuilderInterface;
use Magento\Framework\Controller\ResultFactory;
use Magento\Sales\Model\Service\InvoiceService;
use Magento\Framework\DB\TransactionFactory;
use Magento\Sales\Model\Order\Email\Sender\InvoiceSender;
use Magento\Sales\Model\Order\Email\Sender\OrderSender;
use Magento\Sales\Model\OrderFactory;
use \Psr\Log\LoggerInterface;
use Magento\CatalogInventory\Api\StockManagementInterface;
use Magento\Checkout\Model\Session;
use Magento\CatalogInventory\Api\StockRegistryInterface;

/**
 * Class Payment
 */
class Payment extends \Magento\Framework\App\Action\Action
{

    /**
     * N-Genius states
     */
    const NGENIUS_STARTED = 'STARTED';
    const NGENIUS_AUTHORISED = 'AUTHORISED';
    const NGENIUS_PURCHASED = 'PURCHASED';
    const NGENIUS_CAPTURED = 'CAPTURED';
    const NGENIUS_FAILED = 'FAILED';

    const NGENIUS_EMBEDED = "_embedded";

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
     * @var TransferFactory
     */
    protected $transferFactory;

    /**
     * @var TransactionFetch
     */
    protected $transaction;

    /**
     * @var CoreFactory
     */
    protected $coreFactory;

    /**
     * @var BuilderInterface
     */
    protected $transactionBuilder;

    /**
     * @var ResultFactory
     */
    protected $resultRedirect;

    /**
     * @var error flag
     */
    protected $error = null;

    /**
     * @var InvoiceService
     */
    protected $invoiceService;

    /**
     * @var TransactionFactory
     */
    protected $transactionFactory;

    /**
     * @var InvoiceSender
     */
    protected $invoiceSender;

    /**
     * @var \NetworkInternational\NGenius\Setup\InstallData::get_statuses()
     */
    protected $orderStatus;

    /**
     * @var N-Genius state
     */
    protected $ngeniusState;

    /**
     * @var OrderSender
     */
    protected $orderSender;

    /**
     * @var OrderFactory
     */
    protected $orderFactory;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var StockManagement
     */
    protected $stockManagement;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     *
     * @var ProductRepository
     */
    protected $productRepository;


    /**
     * Payment constructor.
     *
     * @param Context $context
     * @param Config $config
     * @param TokenRequest $tokenRequest
     * @param StoreManagerInterface $storeManager
     * @param TransferFactory $transferFactory
     * @param TransactionFetch $transaction
     * @param CoreFactory $coreFactory
     * @param BuilderInterface $transactionBuilder
     * @param ResultFactory $resultRedirect
     * @param InvoiceService $invoiceService
     * @param TransactionFactory $transactionFactory
     * @param InvoiceSender $invoiceSender
     * @param OrderSender $orderSender
     * @param OrderFactory $orderFactory
     * @param LoggerInterface $logger
     * @param StockManagementInterface $stockManagement
     * @param Session $checkoutSession
     * @param Session $productRepository
     */
    public function __construct(
        Context $context,
        Config $config,
        TokenRequest $tokenRequest,
        StoreManagerInterface $storeManager,
        TransferFactory $transferFactory,
        TransactionFetch $transaction,
        CoreFactory $coreFactory,
        BuilderInterface $transactionBuilder,
        ResultFactory $resultRedirect,
        InvoiceService $invoiceService,
        TransactionFactory $transactionFactory,
        InvoiceSender $invoiceSender,
        OrderSender $orderSender,
        OrderFactory $orderFactory,
        LoggerInterface $logger,
        StockManagementInterface $stockManagement,
        Session $checkoutSession,
        StockRegistryInterface $stockRegistry,
	    \Magento\Catalog\Model\Product $productCollection
    ) {
        $this->config = $config;
        $this->tokenRequest = $tokenRequest;
        $this->storeManager = $storeManager;
        $this->transferFactory = $transferFactory;
        $this->transaction = $transaction;
        $this->coreFactory = $coreFactory;
        $this->transactionBuilder = $transactionBuilder;
        $this->resultRedirect = $resultRedirect;
        $this->invoiceService = $invoiceService;
        $this->transactionFactory = $transactionFactory;
        $this->invoiceSender = $invoiceSender;
        $this->orderSender = $orderSender;
        $this->orderFactory = $orderFactory;
        $this->logger = $logger;
        $this->orderStatus = \NetworkInternational\NGenius\Setup\InstallData::get_statuses();
        $this->stockManagement = $stockManagement;
        $this->checkoutSession = $checkoutSession;
        $this->stockRegistry = $stockRegistry;
	    $this->productCollection = $productCollection;
        return parent::__construct($context);
    }

    /**
     * Default execute function.
     * @return URL
     */
    public function execute()
    {
        $orderRef = $this->getRequest()->getParam('ref');
        $resultRedirectFactory = $this->resultRedirect->create(ResultFactory::TYPE_REDIRECT);

        if ($orderRef) {
            $result = $this->getResponseAPI($orderRef);

            $embedded = self::NGENIUS_EMBEDED;
            if ($result && isset($result[$embedded]['payment']) && is_array($result[$embedded]['payment'])) {
                $action = isset($result['action']) ? $result['action'] : '';
                $paymentResult = $result[$embedded]['payment'][0];
                $orderItem = $this->fetchOrder('reference', $orderRef)->getFirstItem();
                $this->processOrder($paymentResult, $orderItem, $orderRef, $action);
            }
            if ($this->error) {
                $this->messageManager->addError(__('Failed! There is an issue with your payment transaction.'));
                return $resultRedirectFactory->setPath('checkout/onepage/failure');
            } else {
                return $resultRedirectFactory->setPath('checkout/onepage/success');
            }
        } else {
            return $resultRedirectFactory->setPath('checkout');
        }
    }

    /**
     * Process Order.
     *
     * @param array $paymentResult
     * @param object $orderItem
     * @param string $orderRef
     * @param string $action
     * @return null|boolean true
     */
    public function processOrder($paymentResult, $orderItem, $orderRef, $action)
    {
        $dataTable = [];
        $incrementId = $orderItem->getOrderId();

        if ($incrementId) {
            $paymentId = $this->getPaymentId($paymentResult);

            $order = $this->orderFactory->create()->loadByIncrementId($incrementId);
            if ($order->getId()) {
                $dataTable = $this->get_capture_payment($order,$paymentResult,$paymentId,$action,$dataTable);
                $dataTable['entity_id'] = $order->getId();
                $dataTable['payment_id'] = $paymentId;
                return $this->updateTable($dataTable, $orderItem);
            } else {
                $orderItem->setPaymentId($paymentId);
                $orderItem->setState($this->ngeniusState);
                $orderItem->setStatus($this->ngeniusState);
                $orderItem->save();
            }
        }
    }

    public function get_capture_payment($order,$paymentResult,$paymentId,$action,$dataTable){
        if ($this->ngeniusState != self::NGENIUS_FAILED) {
            if ($this->ngeniusState != self::NGENIUS_STARTED) {
                $order->setState(\NetworkInternational\NGenius\Setup\InstallData::STATE);
                $order->setStatus($this->orderStatus[1]['status'])->save();
                $this->orderSender->send($order, true);

                if($action == "AUTH"){
                    $this->orderAuthorize($order, $paymentResult, $paymentId);
                }elseif($action == "SALE"){
                    $dataTable['captured_amt'] = $this->orderSale($order, $paymentResult, $paymentId);
                }elseif($action == "PURCHASE"){
                    $this->orderPurchase($order, $paymentResult, $paymentId);
                }
                $dataTable['status'] = $order->getStatus();

            } else {
                $dataTable['status'] = $this->orderStatus[0]['status'];
            }
        } else {
            $this->error = true;
            $this->updateInvoice($order, false);
            $order->setStatus(\Magento\Sales\Model\Order::STATE_PAYMENT_REVIEW);
            $order->addStatusHistoryComment('The payment on order has failed.')
                  ->setIsCustomerNotified(false)->save();
            $dataTable['status'] = $this->orderStatus[2]['status'];

            $this->restore_cart($order);
            $this->checkoutSession->clearQuote();
            $this->checkoutSession->clearStorage();
        }
        return $dataTable;
    }

    /**
     * @param $paymentResult
     * Get payment id from payment response
     * @return false|mixed|string
     */
    public function getPaymentId($paymentResult){
        if (isset($paymentResult['_id'])) {
            $paymentIdArr = explode(':', $paymentResult['_id']);
            return end($paymentIdArr);
        }
    }

    /**
     * @param $order
     * @param $lastRealOrder
     * Restore cart if order fails
     */
    public function restore_cart($order){
        $lastRealOrder = $this->checkoutSession->getLastRealOrder();
        if ($lastRealOrder->getPayment()) {
            foreach ($order->getAllVisibleItems() as $item) {
                $product_id = $this->productCollection->getIdBySku($item->getSku());
                $qty = $item->getQtyOrdered();
                $this->stockManagement->backItemQty($product_id, $qty, "NULL");
            }
        }
    }

    /**
     * Order Authorize.
     *
     * @param object $order
     * @param array $paymentResult
     * @param string $paymentId
     * @return null
     */
    public function orderAuthorize($order, $paymentResult, $paymentId)
    {
        if ($this->ngeniusState == self::NGENIUS_AUTHORISED) {
            $payment = $order->getPayment();
            $payment->setLastTransId($paymentId);
            $payment->setTransactionId($paymentId);
            $payment->setIsTransactionClosed(false);
            $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());

            $paymentData = [
                'Card Type' => isset($paymentResult['paymentMethod']['name']) ? $paymentResult['paymentMethod']['name'] : '',
                'Card Number' => isset($paymentResult['paymentMethod']['pan']) ? $paymentResult['paymentMethod']['pan'] : '',
                'Amount' => $formatedPrice
            ];

            $transaction_builder = $this->transactionBuilder->setPayment($payment)
                    ->setOrder($order)
                    ->setTransactionId($paymentId)
                    ->setAdditionalInformation(
                        [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]
                    )
                    ->setFailSafe(true)
                    ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);

            $payment->addTransactionCommentsToOrder($transaction_builder, null);
            $payment->setParentTransactionId(null);
            $payment->save();

            $message = 'The payment has been approved and the authorized amount is ' . $formatedPrice;
            $order->addStatusToHistory($this->orderStatus[4]['status'], $message, true);
            $order->save();
        }
    }

    /**
     * Order Authorize.
     *
     * @param object $order
     * @param array $paymentResult
     * @param string $paymentId
     * @return null
     */
    public function orderPurchase($order, $paymentResult, $paymentId)
    {
        if ($this->ngeniusState == self::NGENIUS_PURCHASED) {
            $payment = $order->getPayment();

            $payment->setLastTransId($paymentId);
            $payment->setTransactionId($paymentId);
            $payment->setIsTransactionClosed(false);
            $formatedPrice = $order->getBaseCurrency()->formatTxt($order->getGrandTotal());

            $paymentData = [
                'Card Type' => isset($paymentResult['paymentMethod']['name']) ? $paymentResult['paymentMethod']['name'] : '',
                'Card Number' => isset($paymentResult['paymentMethod']['pan']) ? $paymentResult['paymentMethod']['pan'] : '',
                'Amount' => $formatedPrice
            ];

            $transaction_builder = $this->transactionBuilder->setPayment($payment)
                                                            ->setOrder($order)
                                                            ->setTransactionId($paymentId)
                                                            ->setAdditionalInformation(
                                                                [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]
                                                            )
                                                            ->setFailSafe(true)
                                                            ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_AUTH);

            $payment->addTransactionCommentsToOrder($transaction_builder, null);
            $payment->setParentTransactionId(null);
            $payment->save();

            $message = 'The payment has been approved and the authorized amount is ' . $formatedPrice;
            $order->addStatusToHistory($this->orderStatus[4]['status'], $message, true);
            $order->save();
        }
    }

    /**
     * Order Sale.
     *
     * @param object $order
     * @param array $paymentResult
     * @param string $paymentId
     * @return null|float
     */
    public function orderSale($order, $paymentResult, $paymentId)
    {
        if ($this->ngeniusState == self::NGENIUS_CAPTURED) {
            $payment = $order->getPayment();
            $payment->setLastTransId($paymentId);
            $payment->setTransactionId($paymentId);
            $payment->setIsTransactionClosed(true);
            $grandTotal = $order->getGrandTotal();
            $formatedPrice = $order->getBaseCurrency()->formatTxt($grandTotal);

            $paymentData = [
                'Card Type' => isset($paymentResult['paymentMethod']['name']) ? $paymentResult['paymentMethod']['name'] : '',
                'Card Number' => isset($paymentResult['paymentMethod']['pan']) ? $paymentResult['paymentMethod']['pan'] : '',
                'Amount' => $formatedPrice
            ];

            $transactionId = '';
            $links = "_links";

            $embedded = self::NGENIUS_EMBEDED;

            if (isset($paymentResult[$embedded]['cnp:capture'][0])) {
                $lastTransaction = $paymentResult[$embedded]['cnp:capture'][0];
                if (isset($lastTransaction[$links]['self']['href'])) {
                    $transactionArr = explode('/', $lastTransaction[$links]['self']['href']);
                    $transactionId = end($transactionArr);
                }elseif ($lastTransaction[$links]['cnp:refund']['href']) {
                    $transactionArr = explode('/', $lastTransaction[$links]['cnp:refund']['href']);
                    $transactionId = $transactionArr[count($transactionArr)-2];
                }
            }

            $transaction_builder = $this->transactionBuilder->setPayment($payment)
                    ->setOrder($order)
                    ->setTransactionId($transactionId)
                    ->setAdditionalInformation(
                        [\Magento\Sales\Model\Order\Payment\Transaction::RAW_DETAILS => (array) $paymentData]
                    )
                    ->setFailSafe(true)
                    ->build(\Magento\Sales\Model\Order\Payment\Transaction::TYPE_CAPTURE);

            $payment->addTransactionCommentsToOrder($transaction_builder, null);
            $payment->setParentTransactionId(null);
            $payment->save();

            $message = 'The payment has been approved and the captured amount is ' . $formatedPrice;
            $order->addStatusToHistory($this->orderStatus[3]['status'], $message, true);
            $order->save();

            $this->updateInvoice($order, true, $transactionId);
            return $grandTotal;
        }
    }

    /**
     * Update Invoice.
     *
     * @param object $order
     * @param bool $flag
     * @param string $transactionId
     * @return null
     */
    public function updateInvoice($order, $flag, $transactionId = null)
    {
        if ($order->hasInvoices()) {
            if ($flag === false) {
                foreach ($order->getInvoiceCollection() as $invoice) {
                    $invoice->cancel()->save();
                }
            } else {
                foreach ($order->getInvoiceCollection() as $invoice) {
                    $invoice->setRequestedCaptureCase(\Magento\Sales\Model\Order\Invoice::CAPTURE_ONLINE);
                    $invoice->setTransactionId($transactionId);
                    $invoice->pay()->save();
                    $transactionSave = $this->transactionFactory->create()->addObject($invoice)->addObject($invoice->getOrder());
                    $transactionSave->save();
                    try {
                        $this->invoiceSender->send($invoice);
                        $order->addStatusHistoryComment(__('Notified the customer about invoice #%1.', $invoice->getIncrementId()))
                                ->setIsCustomerNotified(true)->save();
                    } catch (\Exception $e) {
                        $this->messageManager->addError(__('We can\'t send the invoice email right now.'));
                    }
                }
            }
        }
    }

    /**
     * Update Table.
     *
     * @param array $data
     * @param object $orderItem
     * @return bool true
     */
    public function updateTable(array $data, $orderItem)
    {
        $orderItem->setEntityId($data['entity_id']);
        $orderItem->setState($this->ngeniusState);
        $orderItem->setStatus($data['status']);
        $orderItem->setPaymentId($data['payment_id']);
        if(isset($data['captured_amt'])) {
            $orderItem->setCapturedAmt($data['captured_amt']);
        }
        $orderItem->save();
        return true;
    }

    /**
     * Fetch  order details.
     *
     * @param string $orderRef
     * @return array
     */
    public function getResponseAPI($orderRef)
    {

        $storeId = $this->storeManager->getStore()->getId();
        $request = [
            'token' => $this->tokenRequest->getAccessToken($storeId),
            'request' => [
                'data' => [],
                'method' => \Zend_Http_Client::GET,
                'uri' => $this->config->getFetchRequestURL($orderRef, $storeId)
            ]
        ];

        $result = $this->transaction->placeRequest($request);
        return $this->resultValidator($result);
    }

    /**
     * Validate API response.
     *
     * @param array $result
     * @return array
     */
    public function resultValidator($result)
    {

        if (isset($result['errors']) && is_array($result['errors'])) {
            $this->error = true;
            return false;
        } else {
            $this->error = false;
            $this->ngeniusState = isset($result[self::NGENIUS_EMBEDED]['payment'][0]['state']) ? $result[self::NGENIUS_EMBEDED]['payment'][0]['state'] : '';
            return $result;
        }
    }

    /**
     * Fetch order details.
     *
     * @param string $key
     * @param string $value
     * @return object
     */
    public function fetchOrder($key, $value)
    {
        return $this->coreFactory->create()->getCollection()->addFieldToFilter($key, $value);
    }

    /**
     * Cron Task.
     *
     * @return null
     */
    public function cronTask()
    {

        $orderItems = $this->fetchOrder('state', self::NGENIUS_STARTED)->addFieldToFilter('payment_id', null)->addFieldToFilter('created_at', ['lteq' => date('Y-m-d H:i:s', strtotime('-1 hour'))])->setOrder('nid', 'DESC');
        if ($orderItems) {
            foreach ($orderItems as $orderItem) {
                $orderRef = $orderItem->getReference();
                $result = $this->getResponseAPI($orderRef);
                $embedded = self::NGENIUS_EMBEDED;
                if ($result && isset($result[$embedded]['payment']) && is_array($result[$embedded]['payment'])) {
                    $action = isset($result['action']) ? $result['action'] : '';
                    $paymentResult = $result[$embedded]['payment'][0];
                    $this->processOrder($paymentResult, $orderItem, $orderRef, $action);
                }
            }
        }
    }
}
