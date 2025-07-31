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
                        case 'store_info':
                            // This will be filtered after fetching the data
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

                // Get store information
                $data['store_info'] = $this->getStoreInformation($fullAttribute);

                $attributeData[] = $data;
            }

            // Post-filter by store_info if specified
            if (isset($filters['store_info']) && !empty($filters['store_info'])) {
                $storeFilter = strtolower($filters['store_info']);
                $attributeData = array_filter($attributeData, function($attr) use ($storeFilter) {
                    return strpos(strtolower($attr['store_info']), $storeFilter) !== false;
                });
                $attributeData = array_values($attributeData); // Re-index array
            }

            $response['success'] = true;
            $response['attributes'] = $attributeData;

        } catch (\Exception $e) {
            $response['message'] = $e->getMessage();
        }

        return $result->setData($response);
    }

    protected function getStoreInformation($attribute)
    {
        $storeInfo = [];
        
        // Get the global scope setting
        $global = $attribute->getGlobal();
        
        if ($global == \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL) {
            $storeInfo[] = 'All Store Views';
        } else {
            // Get store-specific labels from eav_attribute_label table
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $resource = $objectManager->get(\Magento\Framework\App\ResourceConnection::class);
            $connection = $resource->getConnection();
            
            $storeLabels = $connection->fetchAll(
                $connection->select()
                    ->from(['eal' => $connection->getTableName('eav_attribute_label')], ['store_id'])
                    ->join(['s' => $connection->getTableName('store')], 'eal.store_id = s.store_id', ['name'])
                    ->where('eal.attribute_id = ?', $attribute->getId())
            );
            
            foreach ($storeLabels as $storeLabel) {
                $storeInfo[] = $storeLabel['name'];
            }
            
            if (empty($storeInfo)) {
                $storeInfo[] = 'Default Store View';
            }
        }
        
        return implode(', ', $storeInfo);
    }
    
}
