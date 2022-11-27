<?php

namespace NetworkInternational\NGenius\Model\Config;

/**
 * Class OrderStatus
 */
class OrderStatus implements \Magento\Framework\Option\ArrayInterface
{

    /**
     * Options getter
     *
     * @return array
     */
    public function toOptionArray()
    {

        $status = \NetworkInternational\NGenius\Setup\InstallData::get_statuses();

        return [['value' => $status[0]['status'], 'label' => __($status[0]['label'])]];
    }
}
