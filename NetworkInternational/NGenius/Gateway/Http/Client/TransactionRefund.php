<?php

namespace NetworkInternational\NGenius\Gateway\Http\Client;

/*
 * Class TransactionRefund
 */

class TransactionRefund extends AbstractTransaction
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
     * @return array
     */
    protected function postProcess($responseEnc)
    {
        $response = json_decode($responseEnc, true);

        if (isset($response['errors']) && is_array($response['errors'])) {
            return null;
        } else {
            $captured_amt = 0;
            $refund_amount_array = $this->get_refund_amount_data($response);
            $lastTransaction = $refund_amount_array['lastTransaction'];
            $refunded_amt = $refund_amount_array['refunded_amt'];
            $captured_amt = $refund_amount_array['captured_amt'];

            $refund_data = $this->get_refund_data($lastTransaction);
            $last_refunded_amt = $refund_data['last_refunded_amt'];
            $transactionId = $refund_data['transactionId'];

            $collection = $this->coreFactory->create()->getCollection()->addFieldToFilter('reference', $response['orderReference']);
            $orderItem = $collection->getFirstItem();
            $state = isset($response['state']) ? $response['state'] : '';

            if ($captured_amt == $refunded_amt) {
                $order_status = $this->orderStatus[7]['status'];
            } else {
                $order_status = $this->orderStatus[8]['status'];
            }
            $orderItem->setState($state);
            $orderItem->setStatus($order_status);
            $orderItem->setCapturedAmt(($captured_amt - $refunded_amt) / 100);
            $orderItem->save();
            return [
                'result' => [
                    'total_refunded' => $refunded_amt,
                    'refunded_amt' => $last_refunded_amt,
                    'state' => $state,
                    'order_status' => $order_status,
                    'payment_id' => $transactionId
                ]
            ];
        }
    }

    /**
     * @param $response
     *
     * @return array
     */
    public function get_refund_amount_data($response){
        $embedded = "_embedded";
        $cnpcapture = "cnp:capture";
        $cnprefund = "cnp:refund";
        $captured_amt = 0;
        $lastTransaction = "";
        if (isset($response[$embedded][$cnpcapture]) && is_array($response[$embedded][$cnpcapture])) {
            foreach ($response[$embedded][$cnpcapture] as $capture) {
                $captured_amt += $this->get_amount_value($capture,$captured_amt);
            }
        }

        $refunded_amt = 0;
        if (isset($response[$embedded][$cnprefund]) && is_array($response[$embedded][$cnprefund])) {
            $lastTransaction = end($response[$embedded][$cnprefund]);
            foreach ($response[$embedded][$cnprefund] as $refund) {
                $refunded_amt += $this->get_amount_value($refund,$refunded_amt);
            }
        }

        return array(
            'refunded_amt' => $refunded_amt,
            'captured_amt' => $captured_amt,
            'lastTransaction' => $lastTransaction,
        );
    }

    public function get_amount_value($refund,$refunded_amt){
        if (isset($refund['state']) && ($refund['state'] == 'SUCCESS') && isset($refund['amount']['value'])) {
            return $refund['amount']['value'];
        }
    }

    /**
     * @param $lastTransaction
     *
     * @return array
     */
    public function get_refund_data($lastTransaction){
        $refund_data = array();
        if (isset($lastTransaction['state']) && ($lastTransaction['state'] == 'SUCCESS') && isset($lastTransaction['amount']['value'])) {
            $refund_data['last_refunded_amt'] = $lastTransaction['amount']['value'] / 100;
        }

        if (isset($lastTransaction['_links']['self']['href'])) {
            $transactionArr = explode('/', $lastTransaction['_links']['self']['href']);
            $refund_data['transactionId'] = end($transactionArr);
        }

        return $refund_data;
    }
}
