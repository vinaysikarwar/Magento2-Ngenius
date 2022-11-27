<?php

namespace NetworkInternational\NGenius\Model\ResourceModel;

/**
 * Class Core
 */
class Core extends \Magento\Framework\Model\ResourceModel\Db\AbstractDb
{

    /**
     * Initialize resource model
     *
     * @return void
     */
    protected function _construct()
    {
        $this->_init('ngenius_networkinternational', 'nid');
    }
}
