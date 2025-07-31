<?php

namespace MiniOrange\CustomerAttributeBuilder\Model;

use Magento\Framework\Model\AbstractModel;

class Attribute extends AbstractModel
{
    protected function _construct()
    {
        $this->_init(\MiniOrange\CustomerAttributeBuilder\Model\ResourceModel\Attribute::class);
    }
}
