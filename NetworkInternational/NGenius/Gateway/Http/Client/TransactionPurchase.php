<?php

namespace NetworkInternational\NGenius\Gateway\Http\Client;

use Magento\Framework\HTTP\ZendClientFactory;
use Magento\Payment\Gateway\Http\ClientInterface;
use Magento\Payment\Gateway\Http\TransferInterface;
use Magento\Payment\Model\Method\Logger;
use NetworkInternational\NGenius\Model\CoreFactory;
use Magento\Checkout\Model\Session;
use Magento\Framework\Message\ManagerInterface;
use Magento\Framework\Exception\CouldNotSaveException;

/*
 * Class TransactionPurchase
 */

class TransactionPurchase
{

    /**
     * @var Logger
     */
    private $logger;

    /**
     * @var ZendClientFactory
     */
    protected $clientFactory;

    /**
     * @var Session
     */
    protected $checkoutSession;

    /**
     * @var CoreFactory
     */
    protected $coreFactory;

    /**
     * @var \NetworkInternational\NGenius\Setup\InstallData::get_statuses()
     */
    protected $orderStatus;

    /**
     * @var ManagerInterface
     */
    protected $messageManager;

    /**
     * AbstractTransaction constructor.
     *
     * @param ZendClientFactory $clientFactory
     * @param Logger $logger
     * @param Session $checkoutSession
     * @param CoreFactory $coreFactory
     * @param ManagerInterface $messageManager
     */
    public function __construct(
        ZendClientFactory $clientFactory,
        Logger $logger,
        Session $checkoutSession,
        CoreFactory $coreFactory,
        ManagerInterface $messageManager
    ) {
        $this->logger = $logger;
        $this->clientFactory = $clientFactory;
        $this->checkoutSession = $checkoutSession;
        $this->coreFactory = $coreFactory;
        $this->orderStatus = \NetworkInternational\NGenius\Setup\InstallData::get_statuses();
        $this->messageManager = $messageManager;
    }

    /**
     * Processing of API response
     *
     * @param array $responseEnc
     * @return null|array
     */
    protected function postProcess($responseEnc)
    {
        $response = json_decode($responseEnc);
        try {
            if (isset($response->_links->payment->href)) {
                $data = $this->checkoutSession->getData();

                $data['reference'] = isset($response->reference) ? $response->reference : '';
                $data['action']    = isset($response->action) ? $response->action : '';
                $data['state']     = isset($response->_embedded->payment[0]->state) ? $response->_embedded->payment[0]->state : '';
                $data['status']    = $this->orderStatus[0]['status'];
                $data['order_id']  = $data['last_real_order_id'];
                $data['entity_id'] = $data['last_order_id'];

                $model = $this->coreFactory->create();
                $model->setData($data);
                $model->save();

                $this->checkoutSession->setPaymentURL($response->_links->payment->href);

                $data = ['payment_url' => $response->_links->payment->href];
            } else {
                $data = null;
            }
        }catch(\Exception $ex){
            $data = null;
        }

        return $data;
    }

    /**
     * Places request to gateway. Returns result as ENV array
     *
     * @param TransferInterface $transferObject
     * @return array
     */
    public function placeRequest($request)
    {
        $authorization = "Authorization: Bearer " . $request['token'];
        $url = $request['request']['uri'];

        $headers = array(
            'Content-Type: application/vnd.ni-payment.v2+json',
            $authorization,
            'Accept: application/vnd.ni-payment.v2+json'
        );

        $data  = json_encode($request['request']['data']);

        $ch         = curl_init();
        $curlConfig = array(
            CURLOPT_URL            => $url,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $data
        );

        curl_setopt_array($ch, $curlConfig);
        $response = curl_exec($ch);

        return $this->postProcess($response);
    }
}
