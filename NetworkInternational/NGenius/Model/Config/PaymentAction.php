<?php

namespace NetworkInternational\NGenius\Model\Config;

use Magento\Payment\Model\Method\AbstractMethod;

/**
 * Class PaymentAction
 */
class PaymentAction implements \Magento\Framework\Option\ArrayInterface
{

    const ACTION_PURCHASE = 'purchased';

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {
        return [
            [
                'value' => AbstractMethod::ACTION_AUTHORIZE,
                'label' => __('Authorize'),
            ],
            [
                'value' => AbstractMethod::ACTION_AUTHORIZE_CAPTURE,
                'label' => __('Sale'),
            ],
            [
                'value' => AbstractMethod::ACTION_ORDER,
                'label' => __('Purchase'),
            ]
        ];
    }
}
