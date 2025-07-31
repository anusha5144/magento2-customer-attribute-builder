<?php

namespace MiniOrange\CustomerAttributeBuilder\Block\Adminhtml;

use Magento\Backend\Block\Template;
use Magento\Backend\Block\Template\Context;
use Magento\Framework\Registry;
use Magento\Customer\Model\AttributeFactory as CustomerAttributeFactory;
use Magento\Framework\App\ResourceConnection;

class AttributeFormBuilder extends Template
{
    protected $_storeManager;
    protected $registry;
    protected $attributeFactory;
    protected $customerAttributeFactory;
    protected $resource;

    public function __construct(
        Context $context,
        Registry $registry,
        \MiniOrange\CustomerAttributeBuilder\Model\AttributeFactory $attributeFactory,
        CustomerAttributeFactory $customerAttributeFactory,
        ResourceConnection $resource,
        array $data = []
    ) {
        parent::__construct($context, $data);
        $this->registry = $registry;
        $this->attributeFactory = $attributeFactory;
        $this->customerAttributeFactory = $customerAttributeFactory;
        $this->resource = $resource;
    }

    public function getStoreOptionsHtml()
    {
        $storeManager = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Store\Model\StoreManagerInterface::class);
        $stores = $storeManager->getStores();
        $options = '';
        
        // Get the selected store view for this attribute
        $selectedStoreId = $this->getSelectedStoreId();
        
        // Add a default option for global attributes
        $selected = ($selectedStoreId === null) ? 'selected' : '';
        $options .= '<option value="0" ' . $selected . '>All Store Views</option>';
        
        foreach ($stores as $store) {
            $selected = ($store->getId() == $selectedStoreId) ? 'selected' : '';
            $options .= '<option value="' . $store->getId() . '" ' . $selected . '>' . $store->getName() . '</option>';
        }
        return $options;
    }

    protected function getSelectedStoreId()
    {
        $attribute = $this->getAttributeData();
        if (!$attribute || !$attribute->getId()) {
            return null;
        }

        // Check if the attribute is global or store-specific
        $isGlobal = $attribute->getIsGlobal() == \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL;
        
        if ($isGlobal) {
            return null; // Global attributes don't have a specific store view
        }

        $connection = $this->resource->getConnection();
        $attributeLabelTable = $connection->getTableName('eav_attribute_label');
        
        $storeId = $connection->fetchOne(
            $connection->select()
                ->from($attributeLabelTable, ['store_id'])
                ->where('attribute_id = ?', $attribute->getId())
                ->limit(1)
        );
        
        return $storeId ? (int)$storeId : null;
    }

    public function getInputTypeHtml()
    {
        $inputTypes = [
            'text' => 'Text Field',
            'textarea' => 'Text Area',
            'date' => 'Date',
            'dropdown' => 'Dropdown',
            'file' => 'Single File Upload'
        ];
        $options = '';
        foreach ($inputTypes as $value => $label) {
            $options .= '<option value="' . $value . '">' . $label . '</option>';
        }
        return $options;
    }

    public function getAttributeData()
    {
        $id = $this->getRequest()->getParam('id');
        if ($id) {
            $attribute = $this->customerAttributeFactory->create()->load($id);
            $usedInForms = $attribute->getUsedInForms(); 
            $attribute->setData('used_in_forms', $usedInForms);
            return $attribute;
        }
        return null;
    }

    public function getFormAction()
    {
        return $this->getUrl('customerattribute/attribute/customerattributesavehandler');
    }

    public function mapMagentoInputTypeToCustom($magentoInputType)
    {
        switch ($magentoInputType) {
            case 'select':
                return 'dropdown';
            case 'boolean':
                return 'yesno';
            case 'file':
                return 'file';
            case 'date':
                return 'date';
            case 'textarea':
                return 'textarea';
            case 'text':
            default:
                return 'text';
        }
    }
} 