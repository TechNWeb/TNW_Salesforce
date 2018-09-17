<?php

namespace  TNW\Salesforce\Model\ResourceModel;

use \Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Mapper extends AbstractDb
{
    public function _construct()
    {
        $this->_init('tnw_salesforce_mapper', 'map_id');
    }
}