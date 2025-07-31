<?php
namespace MiniOrange\CustomerAttributeBuilder\Block\Customer;

use Magento\Framework\View\Element\Template;
use MiniOrange\CustomerAttributeBuilder\Helper\Data;

class CustomAttributes extends Template
{
    protected $helper;

    public function __construct(
        Template\Context $context,
        Data $helper,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->helper = $helper;
    }

    public function getCustomAttributes()
    {
        $formCode = $this->getData('form_code');
        return $this->helper->getCustomAttributesForForm($formCode);
    }

    public function getStoreId()
    {
        return $this->_storeManager->getStore()->getId();
    }
}
