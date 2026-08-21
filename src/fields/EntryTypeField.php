<?php

namespace wmd\sectionandproducttype\fields;

use Craft;
use yii\db\Schema;
use craft\base\Field;
use craft\helpers\Json;
use craft\helpers\Html;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;
use GraphQL\Type\Definition\Type;
use wmd\sectionandproducttype\SectionAndProductType;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\assetbundles\FieldSettingsAsset;


class EntryTypeField extends Field implements PreviewableFieldInterface
{
    /**
     * @var bool Contains values for select all entry types.
     */
    public bool $selectAll = false;

    /**
     * @var bool Contains multi-select values for entry types.
     */
    public bool $multiple = false;

    /**
     * @var array Entry types that are allowed for selection in the field settings.
     */
    public array $allowedEntryTypes = [];

    /**
     * @var array Entry types that are excluded from selection in the field settings.
     */
    public array $excludedEntryTypes = [];

    /**
     * @var string Part of handle for selected entry type by this part
     */
    public string $partOfHandle = '';

    /**
     * @var string How the entry types are presented when editing an entry.
     *
     * Either `list` (radio buttons or checkboxes) or `dropdown` (a select menu).
     */
    public string $viewMode = SectionAndProductType::VIEW_MODE_LIST;

    /**
     * @var string What the field hands back in templates.
     *
     * Either `ids` (the selected entry type IDs) or `objects` (a SelectedItems
     * object wrapping the EntryType models).
     */
    public string $valueType = SectionAndProductType::VALUE_TYPE_IDS;

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('section-and-product-type', 'Entry Type');
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [
            ['allowedEntryTypes'],
            'validateAllowedEntryTypes'
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

    /**
     * Checking for the existence of entry types for selection.
     *
     * @param string $attribute Attribute validated.
     *
     * @return void
     */
    public function validateAllowedEntryTypes(string $attribute)
    {
        $entryTypes = $this->getEntryTypes();

        foreach ($this->allowedEntryTypes as $entryType) {
            if (!isset($entryTypes[$entryType])) {
                $this->addError($attribute, Craft::t('section-and-product-type', 'Invalid entry type selected.'));
            }
        }
    }

    /**
     * Return all entry types.
     *
     * @return array
     */
    private function getEntryTypes()
    {
        $entryTypes = [];

        foreach (Craft::$app->getEntries()->getAllEntryTypes() as $entryType) {
            $entryTypes[$entryType->id] = Craft::t('site', $entryType->name);
        }

        return $entryTypes;
    }

    /**
     * Return all entry type handles.
     *
     * @return array
     */
    private function getEntryTypesHandles()
    {
        $entryTypes = [];

        foreach (Craft::$app->getEntries()->getAllEntryTypes() as $entryType) {
            $entryTypes[$entryType->id] = $entryType->handle;
        }

        return $entryTypes;
    }

    /**
     * Return all entry types as option arrays carrying each entry type's handle,
     * so the field settings can be filtered by name or by handle.
     *
     * @return array
     */
    private function getEntryTypeOptions(): array
    {
        $handles = $this->getEntryTypesHandles();

        $options = [];
        foreach ($this->getEntryTypes() as $id => $name) {
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
    public static function dbType(): string
    {
        return Schema::TYPE_STRING;
    }

    /**
     * Return entry types without excluded entry types
     *
     * @return array
     */
    public function getAllowedEntryTypes(): array
    {
        $entryTypes = $this->getEntryTypes();
        $excludedEntryTypes = $this->excludedEntryTypes;

        if (!empty($excludedEntryTypes) && !empty($this->selectAll)) {
            $excludedEntryTypes = array_map(function($value) {
                return intval($value);
            }, $excludedEntryTypes);

            foreach ($excludedEntryTypes as $value) {
                unset($entryTypes[$value]);
            }
        }

        return $entryTypes;
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
            $item = Craft::$app->getEntries()->getEntryTypeById($id);
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
        $options = $this->getEntryTypes();

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
            'section-and-product-type/_components/fields/entrytype/_settings',
            [
                'field' => $this,
                'entryTypes' => $this->getEntryTypes(),
                'entryTypeOptions' => $this->getEntryTypeOptions(),
                'viewModes' => SectionAndProductType::viewModeOptions(),
                'valueTypes' => SectionAndProductType::valueTypeOptions(),
                'selectAll' => $this->selectAll,
                'partOfHandle' => $this->partOfHandle,
            ]
        );
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml($value, ElementInterface $element = null): string
    {
        if (empty($this->allowedEntryTypes) && empty($this->selectAll) && empty($this->partOfHandle)) {
            return 'You have not selected any entry types for selection, select in the field settings.';
        }

        $entryTypes = $this->getEntryTypes();
        $allowEntryTypesConfig = $this->allowedEntryTypes;

        if ($this->selectAll) {
            if (is_array($this->excludedEntryTypes)) {
                foreach ($this->excludedEntryTypes as $entryTypeId) {
                    unset($entryTypes[$entryTypeId]);
                }
            }
            $allowEntryTypesConfig = array_keys($entryTypes);
        } else if(!empty($this->partOfHandle)) {
            foreach ($this->getEntryTypesHandles() as $id => $handle) {
                if (stripos($handle, $this->partOfHandle) !== false
                    && !in_array($id, $allowEntryTypesConfig)
                    && !in_array($id, $this->excludedEntryTypes)) {
                    $allowEntryTypesConfig[] = $id;
                }
            }
        }

        $allowEntryTypes = array_flip($allowEntryTypesConfig);
        $allowEntryTypes[''] = true;
        if (!$this->multiple && !$this->required) {
            $entryTypes = ['' => Craft::t('app', 'None')] + $entryTypes;
        }
        $allowEntryTypes = array_intersect_key($entryTypes, $allowEntryTypes);

        return Craft::$app->getView()->renderTemplate(
            'section-and-product-type/_components/fields/entrytype/_input', [
                'field' => $this,
                'value' => $value,
                'entryTypes' => $allowEntryTypes,
                'dropdown' => $this->viewMode === SectionAndProductType::VIEW_MODE_DROPDOWN,
            ]
        );
    }
}
