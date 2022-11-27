<?php

namespace NetworkInternational\NGenius\Gateway\Http\Client;

/*
 * Class TransactionCapture
 */

class TransactionCapture extends AbstractTransaction
{

    /**
     * Processing of API request body
     *
     * @param array $data
     * @return string
     */
    protected function preProcess(array $data)
    {
        return json_encode($data);
    }

    /**
     * Processing of API response
     *
     * @param array $responseEnc
     * @return null|array
     */
    protected function postProcess($responseEnc)
    {

        $response = json_decode($responseEnc, true);

        if (isset($response['errors']) && is_array($response['errors'])) {
            return null;
        } else {
            $transaction_data = getTransactionData($response);
            $amount = $transaction_data['amount'];
            $lastTransaction = $transaction_data['last_transaction'];
            $captured_amt = 0;
            if (isset($lastTransaction['state']) && ($lastTransaction['state'] == 'SUCCESS') && isset($lastTransaction['amount']['value'])) {
                $captured_amt = $lastTransaction['amount']['value'] / 100;
            }

            $transactionId = $this->getTransactionId($lastTransaction);
            $amount = ($amount > 0) ? $amount / 100 : 0;
            $collection = $this->coreFactory->create()->getCollection()->addFieldToFilter('reference', $response['orderReference']);
            $orderItem = $collection->getFirstItem();
            $state = isset($response['state']) ? $response['state'] : '';

            if ($state == 'PARTIALLY_CAPTURED') {
                $order_status = $this->orderStatus[6]['status'];
            } else {
                $order_status = $this->orderStatus[5]['status'];
            }
            $orderItem->setState($state);
            $orderItem->setStatus($order_status);
            $orderItem->setCapturedAmt($amount);
            $orderItem->save();
            return [
                'result' => [
                    'total_captured' => $amount,
                    'captured_amt' => $captured_amt,
                    'state' => $state,
                    'order_status' => $order_status,
                    'payment_id' => $transactionId
                ]
            ];
        }
    }

    /**
     * @param $lastTransaction
     *
     * @return false|mixed|string
     */
    public function getTransactionId($lastTransaction){
        if (isset($lastTransaction['_links']['self']['href'])) {
            $transactionArr = explode('/', $lastTransaction['_links']['self']['href']);
            return end($transactionArr);
        }
    }

    /**
     * @param $response
     *
     * @return array
     */
    public function getTransactionData($response){
        $embedded = "_embedded";
        $cnpcapture = "cnp:capture";
        $amount = 0;
        $lastTransaction = "";
        if (isset($response[$embedded][$cnpcapture]) && is_array($response[$embedded][$cnpcapture])) {
            $lastTransaction = end($response[$embedded][$cnpcapture]);
            foreach ($response[$embedded][$cnpcapture] as $capture) {
                if (isset($capture['state']) && ($capture['state'] == 'SUCCESS') && isset($capture['amount']['value'])) {
                    $amount += $capture['amount']['value'];
                }
            }
        }
        return array(
            'amount' =>$amount,
            'last_transaction' => $lastTransaction
        );
    }
}
