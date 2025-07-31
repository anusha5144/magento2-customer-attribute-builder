<?php

namespace MiniOrange\CustomerAttributeBuilder\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class Attribute extends AbstractDb
{
    protected function _construct()
    {
        $this->_init('eav_attribute', 'attribute_id'); 
    }
}
