<?php
namespace MiniOrange\CustomerAttributeBuilder\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Eav\Model\Config as EavConfig;

class CustomerAttributeDeletionHandler extends Action
{
    protected $jsonFactory;
    protected $attributeModel;
    protected $eavConfig;

    public function __construct(
        Action\Context $context,
        JsonFactory $jsonFactory,
        Attribute $attributeModel,
        EavConfig $eavConfig
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->attributeModel = $attributeModel;
        $this->eavConfig = $eavConfig;
    }

    public function execute()
    {
        $postData = $this->getRequest()->getPostValue();
        $response = ['success' => false, 'message' => ''];

        if ($postData && isset($postData['attribute_code'])) {
            try {
                $attributeCode = $postData['attribute_code'];
                $entityType = $this->eavConfig->getEntityType(\Magento\Customer\Model\Customer::ENTITY);

                $attribute = $this->attributeModel->getCollection()->addFieldToFilter('entity_type_id', $entityType->getId())->addFieldToFilter('attribute_code', $attributeCode)->getFirstItem();

                if ($attribute && $attribute->getId()) {
                    $attribute->delete();
                    $response['success'] = true;
                    $response['message'] = 'Attribute deleted successfully!';
                } else {
                    $response['message'] = 'Attribute not found.';
                }
            } catch (\Exception $e) {
                $response['message'] = $e->getMessage();
            }
        } else {
            $response['message'] = 'Invalid request.';
        }

        $resultJson = $this->jsonFactory->create();
        return $resultJson->setData($response);
    }
}
