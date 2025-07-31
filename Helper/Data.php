<?php
namespace MiniOrange\CustomerAttributeBuilder\Helper;

use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Eav\Model\ResourceModel\Entity\Attribute\CollectionFactory as AttributeCollectionFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Customer\Model\ResourceModel\Attribute\CollectionFactory as CustomerAttributeCollectionFactory;
use Magento\Framework\App\ResourceConnection;

class Data extends AbstractHelper
{
    protected $attributeCollectionFactory;
    protected $storeManager;
    protected $customerAttributeCollectionFactory;
    protected $resourceConnection;

    public function __construct(
        Context $context,
        AttributeCollectionFactory $attributeCollectionFactory,
        StoreManagerInterface $storeManager,
        CustomerAttributeCollectionFactory $customerAttributeCollectionFactory,
        ResourceConnection $resourceConnection
    ) {
        parent::__construct($context);
        $this->attributeCollectionFactory = $attributeCollectionFactory;
        $this->storeManager = $storeManager;
        $this->customerAttributeCollectionFactory = $customerAttributeCollectionFactory;
        $this->resourceConnection = $resourceConnection;
    }

    public function getCustomAttributesForForm($formCode)
    {
        $storeId = $this->storeManager->getStore()->getId();

        $connection = $this->resourceConnection->getConnection();
        $attributeTable = $connection->getTableName('eav_attribute');
        $formAttributeTable = $connection->getTableName('customer_form_attribute');

        $select = $connection->select()
            ->from(['cfa' => $formAttributeTable], ['attribute_id'])
            ->where('cfa.form_code = ?', $formCode);

        $attributeIds = $connection->fetchCol($select);

        if (empty($attributeIds)) {
            return [];
        }

        $collection = $this->customerAttributeCollectionFactory->create();
        
        $collection->getSelect()->joinLeft(
            ['cea' => $connection->getTableName('customer_eav_attribute')],
            'main_table.attribute_id = cea.attribute_id',
            ['is_visible']
        );

        $collection->getSelect()->joinLeft(
            ['eal' => $connection->getTableName('eav_attribute_label')],
            'main_table.attribute_id = eal.attribute_id',
            ['store_id']
        );

        $collection->addFieldToFilter('main_table.attribute_id', ['in' => $attributeIds])
                ->addFieldToFilter('is_user_defined', 1)
                ->addFieldToFilter('frontend_input', ['neq' => 'hidden'])
                ->setOrder('cea.sort_order', 'ASC'); // Sort by sort_order in customer_eav_attribute table

        $collection->getSelect()->where('(eal.store_id IS NULL OR eal.store_id = ?)', $storeId);

        $attributes = [];
        foreach ($collection as $attribute) {
            $storeLabel = $attribute->getStoreLabel($storeId);
            $label = $storeLabel ?: $attribute->getDefaultFrontendLabel();
            
            if ($label) {
                $options = [];
                if ($attribute->usesSource()) {
                    $options = $attribute->getSource()->getAllOptions();
                }
                
                $attributes[] = [
                    'attribute_code'   => $attribute->getAttributeCode(),
                    'label'            => $label,
                    'frontend_input'   => $attribute->getFrontendInput(),
                    'input_validation' => $attribute->getFrontendClass(),
                    'default_value'    => $attribute->getDefaultValue(),
                    'is_required'      => $attribute->getIsRequired(),
                    'options'          => $options,
                    'is_visible'       => $attribute->getData('is_visible'),
                    'store_id'         => $attribute->getData('store_id')
                ];
            }
        }

        return $attributes;
    }

}