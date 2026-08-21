<?php

namespace wmd\sectionandproducttype\fields;

use Craft;
use yii\db\Schema;
use craft\base\Field;
use craft\helpers\Json;
use craft\base\ElementInterface;
use craft\errors\InvalidFieldException;
use craft\base\PreviewableFieldInterface;
use craft\commerce\services\ProductTypes;
use craft\helpers\Html;
use GraphQL\Type\Definition\Type;
use wmd\sectionandproducttype\SectionAndProductType;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\assetbundles\FieldSettingsAsset;


class ProductTypeField extends Field implements PreviewableFieldInterface
{
    /**
     * @var bool Contains  values for select all product types.
     */
    public bool $selectAll = false;

    /**
     * @var bool Contains multi-select values for product types.
     */
    public bool $multiple = false;

    /**
     * @var array Product types that are allowed for selection in the field settings.
     */
    public array $allowProductTypes = [];

    /**
     * @var array Product types that are allowed for selection in the field settings.
     */
    public array $excludedProductTypes = [];

    /**
     * @var string Part of handle for selected product type by this part
     */
    public string $partOfHandle = '';

    /**
     * @var string How the product types are presented when editing an entry.
     *
     * Either `list` (radio buttons or checkboxes) or `dropdown` (a select menu).
     */
    public string $viewMode = SectionAndProductType::VIEW_MODE_LIST;

    /**
     * @var string What the field hands back in templates.
     *
     * Either `ids` (the selected IDs, as this plugin has always done) or
     * `objects` (a SelectedItems object wrapping the models).
     */
    public string $valueType = SectionAndProductType::VALUE_TYPE_IDS;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('section-and-product-type', 'Product Type');
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules[] = [
            ['allowProductTypes'],
            'validateAllowProductTypes'
        ];

        $rules[] = [
            ['valueType'],
            'in',
            'range' => [
                SectionAndProductType::VALUE_TYPE_IDS,
                SectionAndProductType::VALUE_TYPE_OBJECTS,
            ],
        ];
        $rules[] = [
            ['viewMode'],
            'in',
            'range' => [
                SectionAndProductType::VIEW_MODE_LIST,
                SectionAndProductType::VIEW_MODE_DROPDOWN,
            ],
        ];

        return $rules;
    }

    public function validateAllowProductTypes(string $attribute)
    {
        $productTypes = $this->getProductTypes();

        foreach ($this->allowProductTypes as $productType) {
            if (!isset($productTypes[$productType])) {
                $this->addError($attribute, Craft::t('section-and-product-type', 'Invalid product type selected.'));
            }
        }
    }


    /**
     * @inheritdoc
     */
    public static function dbType(): string
    {
        return Schema::TYPE_STRING;
    }

    /**
     * Returns the validation rules.
     *
     * @return array
     */
    public function getElementValidationRules(): array
    {
        return [
            ['validateProductType'],
        ];
    }

    /**
     * Product type validation.
     *
     * @param ElementInterface $element Validated element.
     *
     * @return void
     * @throws InvalidFieldException
     */
    public function validateProductType(ElementInterface $element)
    {
        $value = $element->getFieldValue($this->handle);

        if (!is_array($value)) {
            $value = [$value];
        }

        $productTypes = $this->getProductTypes();

        foreach ($value as $productType) {
            if (!isset($productTypes[$productType])) {
                $element->addError($this->handle, Craft::t('section-and-product-type', 'Invalid product type selected.'));
            }
        }
    }

    /**
     * Return sections without excluded sections
     *
     * @return array
     */
    public function getAllowedProductTypes(): array
    {
        $productTypes = $this->getProductTypes();
        $excludedProductTypes = $this->excludedProductTypes;

        if (!empty($excludedProductTypes) && !empty($this->selectAll)) {
            $excludedProductTypes = array_map(function($value) {
                return intval($value);
            }, $excludedProductTypes);

            foreach ($excludedProductTypes as $value) {
                unset($productTypes[$value]);
            }
        }

        return $productTypes;
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue($value, ?ElementInterface $element = null): Mixed
    {
        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (is_int($value) && $this->multiple) {
            $value = [$value];
        } else if (is_array($value) && !$this->multiple && count($value) == 1) {
            $value = intval($value[0]);
        }
        
        if (is_array($value)) {
            foreach ($value as $key => $id) {
                $value[$key] = intval($id);
            }
        }

        if ($this->valueType === SectionAndProductType::VALUE_TYPE_OBJECTS) {
            return $this->toSelectedItems($value);
        }

        return $value;
    }

    /**
     * Wrap the selected IDs in a SelectedItems object.
     *
     * @param mixed $value
     *
     * @return SelectedItems
     */
    private function toSelectedItems($value): SelectedItems
    {
        $items = [];

        foreach ($this->valueIds($value) as $id) {
            $item = (new ProductTypes)->getProductTypeById($id);
            if ($item) {
                $items[] = $item;
            }
        }

        return new SelectedItems($items);
    }

    /**
     * Reduce any shape this field's value can take to a list of integer IDs.
     *
     * @param mixed $value
     *
     * @return array
     */
    private function valueIds($value): array
    {
        if ($value instanceof SelectedItems) {
            return $value->ids();
        }

        if (!is_array($value)) {
            $value = ($value === null || $value === '') ? [] : [$value];
        }

        return array_map('intval', array_filter($value, static fn($id) => $id !== '' && $id !== null));
    }

    /**
     * @inheritdoc
     */
    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        $options = $this->getProductTypes();

        $names = [];
        foreach ($this->valueIds($value) as $id) {
            if (isset($options[$id])) {
                $names[] = $options[$id];
            }
        }

        return Html::encode(implode(', ', $names));
    }

    /**
     * @inheritdoc
     */
    public function getContentGqlType(): Type|array
    {
        return [
            'name' => $this->handle,
            'type' => $this->multiple ? Type::listOf(Type::int()) : Type::int(),
            'resolve' => function($source) {
                $ids = $this->valueIds($source->getFieldValue($this->handle));

                return $this->multiple ? $ids : ($ids[0] ?? null);
            },
        ];
    }

    /**
     * @inheritdoc
     */
    public function getContentGqlMutationArgumentType(): Type|array
    {
        return $this->multiple ? Type::listOf(Type::int()) : Type::int();
    }

    /**
     * @inheritdoc
     */
    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        if ($value instanceof SelectedItems) {
            return $value->isEmpty();
        }

        return parent::isValueEmpty($value, $element);
    }

    /**
     * @inheritdoc
     */
    public function serializeValue($value, ?ElementInterface $element = null): Mixed
    {
        if ($value instanceof SelectedItems) {
            $ids = $value->ids();
            $value = $this->multiple ? $ids : ($ids[0] ?? '');
        }

        if (is_array($value)) {
            foreach ($value as $key => $id) {
                $value[$key] = intval($id);
            }
        }

        return Json::encode($value);
    }

    /**
     * @inheritdoc
     */
    public function getSettingsHtml(): ?string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(FieldSettingsAsset::class);

        return $view->renderTemplate(
            'section-and-product-type/_components/fields/producttype/_settings',
            [
                'field' => $this,
                'productTypes' => $this->getProductTypes(),
                'productTypeOptions' => $this->getProductTypeOptions(),
                'viewModes' => SectionAndProductType::viewModeOptions(),
                'valueTypes' => SectionAndProductType::valueTypeOptions(),
                'selectAll' => $this->selectAll,
                'partOfHandle' => $this->partOfHandle,
            ]
        );
    }

    /**
     * Return all product types.
     *
     * @return array
     */
    private function getProductTypes()
    {
        $allProductTypes = (new ProductTypes)->getAllProductTypes();

        $productTypes = [];
        foreach ($allProductTypes as $productType) {
            $productTypes[$productType->id] = Craft::t('site', $productType->name);
        }
        return $productTypes;
    }

    /**
     * Return all product types handles.
     *
     * @return array
     */
    private function getProductTypesHandles()
    {
        $allProductTypes = (new ProductTypes)->getAllProductTypes();

        $productTypes = [];
        foreach ($allProductTypes as $productType) {
            $productTypes[$productType->id] = $productType->handle;
        }
        return $productTypes;
    }

    /**
     * Return all product types as option arrays carrying each product type's
     * handle, so the field settings can be filtered by name or by handle.
     *
     * @return array
     */
    private function getProductTypeOptions(): array
    {
        $handles = $this->getProductTypesHandles();

        $options = [];
        foreach ($this->getProductTypes() as $id => $name) {
            $options[] = [
                'label' => $name,
                'value' => $id,
                'data' => ['handle' => $handles[$id] ?? ''],
            ];
        }

        return $options;
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        if (empty($this->allowProductTypes) && empty($this->selectAll) && empty($this->partOfHandle)) {
            return 'You have not selected any product types for selection, select in the field settings.';
        }

        $productTypes = $this->getProductTypes();
        $allowProductTypesConfig = $this->allowProductTypes;

        if ($this->selectAll) {
            if (is_array($this->excludedProductTypes)) {
                foreach ($this->excludedProductTypes as $typeId) {
                    unset($productTypes[$typeId]);
                }
            }
            $allowProductTypesConfig = array_keys($productTypes);
        } else if(!empty($this->partOfHandle)) {
            foreach ($this->getProductTypesHandles() as $id => $handle) {
                if (stripos($handle, $this->partOfHandle) !== false
                    && !in_array($id, $allowProductTypesConfig)
                    && !in_array($id, $this->excludedProductTypes)) {
                    $allowProductTypesConfig[] = $id;
                }
            }
        }

        $allowProductTypes = array_flip($allowProductTypesConfig);
        $allowProductTypes[''] = true;
        if (!$this->multiple && !$this->required) {
            $productTypes =  ['' => Craft::t('app', 'None')] + $productTypes;
        }
        $allowProductTypes = array_intersect_key($productTypes, $allowProductTypes);

        return Craft::$app->getView()->renderTemplate(
            'section-and-product-type/_components/fields/producttype/_input', [
                'field' => $this,
                'value' => $value,
                'productTypes' => $allowProductTypes,
                'dropdown' => $this->viewMode === SectionAndProductType::VIEW_MODE_DROPDOWN,
            ]
        );
    }
}
