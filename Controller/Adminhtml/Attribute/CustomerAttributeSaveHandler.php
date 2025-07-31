<?php
namespace MiniOrange\CustomerAttributeBuilder\Controller\Adminhtml\Attribute;

use Magento\Backend\App\Action;
use Magento\Customer\Model\Customer;
use Magento\Eav\Model\Entity\Attribute;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Eav\Model\Config as EavConfig;
use Magento\Eav\Model\Entity\Attribute\SetFactory as AttributeSetFactory;
use Magento\Framework\App\ResourceConnection;

class CustomerAttributeSaveHandler extends Action
{
    protected $jsonFactory;
    protected $attributeModel;
    protected $eavConfig;
    protected $storeManager;
    protected $customerFormFactory;
    protected $customerSetupFactory;
    protected $moduleDataSetup;
    protected $resource;
    protected $attributeSetFactory;

    public function __construct(
        Action\Context $context,
        JsonFactory $jsonFactory,
        Attribute $attributeModel,
        EavConfig $eavConfig,
        StoreManagerInterface $storeManager,
        \Magento\Customer\Model\ResourceModel\Form\AttributeFactory $customerFormFactory,
        \Magento\Customer\Setup\CustomerSetupFactory $customerSetupFactory,
        \Magento\Framework\Setup\ModuleDataSetupInterface $moduleDataSetup,
        ResourceConnection $resource,
        AttributeSetFactory $attributeSetFactory
    ) {
        parent::__construct($context);
        $this->jsonFactory = $jsonFactory;
        $this->attributeModel = $attributeModel;
        $this->eavConfig = $eavConfig;
        $this->storeManager = $storeManager;
        $this->customerFormFactory = $customerFormFactory;
        $this->customerSetupFactory = $customerSetupFactory;
        $this->moduleDataSetup = $moduleDataSetup;
        $this->resource = $resource;
        $this->attributeSetFactory = $attributeSetFactory;
    }

        public function execute()
    {
        $postData = $this->getRequest()->getPostValue();
        $response = ['success' => false, 'message' => '', 'post_data' => []];

        if ($postData) {
            try {
                $attributeCode = $postData['attribute_code'];
                $label = $postData['default_label'];
                $inputType = $postData['catalog_input'];
                $defaultValue = $postData['default_value'];
                $valuesRequired = $postData['values_required'];
                $isVisible = $postData['is_visible'];
                $sortingOrder = $postData['sorting_order'];
                $usedInForms = $postData['used_in_forms'] ?? [];
                $dropdownOptions = $postData['dropdown_options'] ?? '';
                $backendType = $this->getBackendType($inputType);
                $storeId = isset($postData['store_view']) ? (int)$postData['store_view'] : 0;
                $entityType = $this->eavConfig->getEntityType(Customer::ENTITY);

                
                
                $customerSetup = $this->customerSetupFactory->create(['setup' => $this->moduleDataSetup]);
                $customerEntity = $customerSetup->getEavConfig()->getEntityType('customer');
                $attributeSetId = $customerEntity->getDefaultAttributeSetId();
                $attributeSet = $this->attributeSetFactory->create();
                $attributeGroupId = $attributeSet->getDefaultGroupId($attributeSetId);

                // Map custom input types to Magento standard types
                $magentoInputType = $this->mapInputTypeToMagento($inputType);
                
                // Check if attribute already exists
                $existingAttribute = $this->eavConfig->getAttribute(Customer::ENTITY, $attributeCode);
                
                if ($existingAttribute && $existingAttribute->getId()) {
                    // Update existing attribute
                    $attribute = $existingAttribute;
                    $attribute->addData([
                        'label' => $label,
                        'input' => $magentoInputType,
                        'required' => $valuesRequired,
                        'is_required' => (bool)$valuesRequired,
                        'visible' => $isVisible,
                        'global' => ($storeId > 0)
                            ? \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE
                            : \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL,
                        'attribute_set_id' => $attributeSetId,
                        'attribute_group_id' => $attributeGroupId,
                        'used_in_forms' => $usedInForms,
                    ]);
                    
                    // Add source model for dropdown attributes if not already set
                    if ($inputType === 'dropdown' && !$attribute->getSourceModel()) {
                        $attribute->setSourceModel(\Magento\Eav\Model\Entity\Attribute\Source\Table::class);
                    }
                } else {
                    // Create new attribute
                    $attributeData = [
                        'type' => $backendType,   
                        'label' => $label, 
                        'input' => $magentoInputType,
                        'required' => $valuesRequired,
                        'is_required' => (bool)$valuesRequired,
                        'visible' => $isVisible,
                        'user_defined' => true,
                        'system' => 0,
                        'global' => ($storeId > 0)
                            ? \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_STORE
                            : \Magento\Eav\Model\Entity\Attribute\ScopedAttributeInterface::SCOPE_GLOBAL
                    ];

                    // Add source model for dropdown attributes
                    if ($inputType === 'dropdown') {
                        $attributeData['source'] = \Magento\Eav\Model\Entity\Attribute\Source\Table::class;
                    }
                    
                    $customerSetup->addAttribute(Customer::ENTITY, $attributeCode, $attributeData);

                    $attribute = $customerSetup->getEavConfig()->getAttribute(Customer::ENTITY, $attributeCode)
                        ->addData([
                            'attribute_set_id' => $attributeSetId,
                            'attribute_group_id' => $attributeGroupId,
                            'used_in_forms' => $usedInForms,
                        ]);
                }

                $attribute->save();

                // Save default value if provided
                if (!empty($defaultValue)) {
                    $attribute->setDefaultValue($defaultValue);
                    $attribute->save();
                }

                // Save sort_order to customer_eav_attribute table
                $this->saveSortOrder($attribute, $sortingOrder);

                // Handle dropdown options if input type is dropdown
                if ($inputType === 'dropdown' && !empty($dropdownOptions)) {
                    $this->updateDropdownOptions($attribute, $dropdownOptions);
                }

                // Register attribute with customer forms
                $this->registerAttributeWithForms($attribute, $usedInForms);

                if ($storeId > 0) {
                    $connection = $this->resource->getConnection();

                    $connection->delete(
                        $connection->getTableName('eav_attribute_label'),
                        [
                            'attribute_id = ?' => $attribute->getId(),
                            'store_id = ?' => $storeId
                        ]
                    );

                    $connection->insert(
                        $connection->getTableName('eav_attribute_label'),
                        [
                            'attribute_id' => $attribute->getId(),
                            'store_id' => $storeId,
                            'value' => $label
                        ]
                    );
                }

                $response['success'] = true;
                $response['message'] = 'Attribute saved successfully!';
                $response['post_data'] = $postData;

            } catch (\Exception $e) {
                $response['message'] = $e->getMessage();
            }
        } else {
            $response['message'] = 'Invalid request.';
        }

        return $this->jsonFactory->create()->setData($response);
    }

    protected function getBackendType($inputType)
    {
        switch ($inputType) {
            case 'text':
            case 'textarea':
            case 'file':
            case 'dropdown':
                return 'varchar';
            case 'integer':
                return 'int';
            case 'date':
                return 'datetime';
            default:
                return 'varchar';
        }
    }

    protected function mapInputTypeToMagento($inputType)
    {
        switch ($inputType) {
            case 'dropdown':
                return 'select';
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

    protected function updateDropdownOptions($attribute, $dropdownOptions)
    {
        $connection = $this->resource->getConnection();
        $optionTable = $connection->getTableName('eav_attribute_option');
        $optionValueTable = $connection->getTableName('eav_attribute_option_value');

        // First, delete existing options for this attribute
        $existingOptionIds = $connection->fetchCol(
            $connection->select()
                ->from($optionTable, ['option_id'])
                ->where('attribute_id = ?', $attribute->getId())
        );
        
        if (!empty($existingOptionIds)) {
            // Delete option values first (foreign key constraint)
            $connection->delete($optionValueTable, ['option_id IN (?)' => $existingOptionIds]);
            // Delete options
            $connection->delete($optionTable, ['option_id IN (?)' => $existingOptionIds]);
        }

        // Split options by newlines and filter out empty lines
        $options = array_filter(array_map('trim', explode("\n", $dropdownOptions)));
        
        foreach ($options as $index => $optionLabel) {
            if (empty($optionLabel)) {
                continue;
            }

            // Insert option
            $connection->insert($optionTable, [
                'attribute_id' => $attribute->getId(),
                'sort_order' => $index + 1
            ]);
            
            $optionId = $connection->lastInsertId($optionTable);

            // Insert option value for admin store (store_id = 0)
            $connection->insert($optionValueTable, [
                'option_id' => $optionId,
                'store_id' => 0,
                'value' => $optionLabel
            ]);
        }
    }

    protected function saveSortOrder($attribute, $sortingOrder)
    {
        $connection = $this->resource->getConnection();
        $customerEavAttributeTable = $connection->getTableName('customer_eav_attribute');
        
        $sortOrderValue = !empty($sortingOrder) ? (int)$sortingOrder : 999;
        
        // Check if record exists in customer_eav_attribute table
        $existingRecord = $connection->fetchOne(
            $connection->select()
                ->from($customerEavAttributeTable, ['attribute_id'])
                ->where('attribute_id = ?', $attribute->getId())
        );
        
        if ($existingRecord) {
            // Update existing record
            $connection->update(
                $customerEavAttributeTable,
                ['sort_order' => $sortOrderValue],
                ['attribute_id = ?' => $attribute->getId()]
            );
        } else {
            // Insert new record
            $connection->insert($customerEavAttributeTable, [
                'attribute_id' => $attribute->getId(),
                'sort_order' => $sortOrderValue,
                'is_visible' => 1,
                'is_system' => 0
            ]);
        }
    }

    protected function registerAttributeWithForms($attribute, $usedInForms)
    {
        $connection = $this->resource->getConnection();
        $formAttributeTable = $connection->getTableName('customer_form_attribute');

        // First, remove existing form registrations for this attribute
        $connection->delete(
            $formAttributeTable,
            ['attribute_id = ?' => $attribute->getId()]
        );

        // Register the attribute with the selected forms
        if (!empty($usedInForms)) {
            foreach ($usedInForms as $formCode) {
                $connection->insert($formAttributeTable, [
                    'form_code' => $formCode,
                    'attribute_id' => $attribute->getId()
                ]);
            }
        }
    }
}
