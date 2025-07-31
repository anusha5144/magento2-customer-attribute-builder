<?php
namespace MiniOrange\CustomerAttributeBuilder\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    protected $resultPageFactory;

    public function __construct(
        Action\Context $context,
        PageFactory $resultPageFactory
    ) {
        $this->resultPageFactory = $resultPageFactory;
        parent::__construct($context);
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->setActiveMenu('MiniOrange_CustomerAttributeBuilder::manage_customer_attribute');
        $resultPage->getConfig()->getTitle()->prepend(__());
        return $resultPage;
    }

    protected function _isAllowed()
    {
        return $this->_authorization->isAllowed('MiniOrange_CustomerAttributeBuilder::manage_customer_attribute');
    }
}
