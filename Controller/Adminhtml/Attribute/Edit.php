<?php
namespace MiniOrange\CustomerAttributeBuilder\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;
use Magento\Framework\Registry;
use Magento\Eav\Api\AttributeRepositoryInterface;
use Magento\Eav\Model\Entity\TypeFactory;

class Edit extends Action
{
    protected $resultPageFactory;
    protected $coreRegistry;
    protected $attributeRepository;
    protected $entityTypeFactory;

    public function __construct(
        Action\Context $context,
        PageFactory $resultPageFactory,
        Registry $coreRegistry,
        AttributeRepositoryInterface $attributeRepository,
        TypeFactory $entityTypeFactory
    ) {
        parent::__construct($context);
        $this->resultPageFactory = $resultPageFactory;
        $this->coreRegistry = $coreRegistry;
        $this->attributeRepository = $attributeRepository;
        $this->entityTypeFactory = $entityTypeFactory;
    }

    public function execute()
    {
        $resultPage = $this->resultPageFactory->create();
        $resultPage->getConfig()->getTitle()->prepend(__('Edit Custom Attribute'));
        return $resultPage;
    }
}
