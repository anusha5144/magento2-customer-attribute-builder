<?php
namespace MiniOrange\CustomerAttributeBuilder\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Eav\Model\Config as EavConfig;
 

class CustomerAttributeFetchHandler extends Action
{
    protected $jsonFactory;
    protected $eavConfig;

    public function __construct(
        Action\Context $context,
        JsonFactory $jsonFactory,
        EavConfig $eavConfig
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->eavConfig = $eavConfig;
    }

    public function execute()
    {
        $result = $this->jsonFactory->create(); 
        $response = ['success' => false, 'attributes' => []];

        try {
            $filters = $this->getRequest()->getParams();

            $entityType = $this->eavConfig->getEntityType(\Magento\Customer\Model\Customer::ENTITY);
            $attributes = $entityType->getAttributeCollection()
                ->addFieldToFilter('is_user_defined', 1) 
                ->setEntityTypeFilter($entityType->getId())
                ->setOrder('sort_order', 'ASC'); // Sort by sort_order in ascending order

            if (!empty($filters)) {
                foreach ($filters as $field => $value) {
                    if ($value === '') {
                        continue;
                    }

                    switch ($field) {
                        case 'is_required':
                        case 'is_system':
                        case 'is_visible':
                            $attributes->addFieldToFilter($field, ['eq' => (int)$value]);
                            break;

                        case 'attribute_code':
                        case 'frontend_label':
                        case 'sort_order':
                            $attributes->addFieldToFilter($field, ['like' => '%' . $value . '%']);
                            break;
                    }
                }
            }

            $attributeData = [];

            foreach ($attributes as $attribute) {
                $fullAttribute = $this->eavConfig->getAttribute('customer', $attribute->getAttributeCode());
                $data = $fullAttribute->getData();

                if (method_exists($fullAttribute, 'getUsedInForms')) {
                    $data['used_in_forms'] = $fullAttribute->getUsedInForms();
                }

                $attributeData[] = $data;
            }

            $response['success'] = true;
            $response['attributes'] = $attributeData;

        } catch (\Exception $e) {
            $response['message'] = $e->getMessage();
        }

        return $result->setData($response);
    }
    
}
