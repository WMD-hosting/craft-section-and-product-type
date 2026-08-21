<?php

namespace wmd\sectionandproducttype\fields;

use Craft;
use yii\db\Schema;
use craft\base\Field;
use craft\helpers\Json;
use craft\base\ElementInterface;
use craft\base\PreviewableFieldInterface;
use craft\helpers\Html;
use GraphQL\Type\Definition\Type;
use wmd\sectionandproducttype\SectionAndProductType;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\assetbundles\FieldSettingsAsset;


class TagGroupField extends Field implements PreviewableFieldInterface
{
    /**
     * @var bool Contains  values for select all groups.
     */
    public bool $selectAll = false;

    /**
     * @var bool Contains multi-select values for groups.
     */
    public bool $multiple = false;

    /**
     * @var array Sections that are allowed for selection in the field settings.
     */
    public array $allowedGroups = [];

    /**
     * @var array Sections that are allowed for selection in the field settings.
     */
    public array $excludedGroups = [];

    /**
     * @var string Part of handle for selected tag group by this part
     */
    public string $partOfHandle = '';

    /**
     * @var string How the tag groups are presented when editing an entry.
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
        return Craft::t('section-and-product-type', 'Tag Group');
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [
            ['allowedGroups'],
            'validateAllowedSections'
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
     * Checking for the existence of groupss for selection.
     *
     * @param string $attribute Attribute validated.
     *
     * @return void
     */
    public function validateAllowedSections(string $attribute)
    {
        $groups = $this->getGroups();

        foreach ($this->allowedGroups as $group) {
            if (!isset($groups[$group])) {
                $this->addError($attribute, Craft::t('section-and-product-type', 'Invalid groups selected.'));
            }
        }
    }

    /**
     * Return all groupss.
     *
     * @return array
     */
    private function getGroups()
    {
        $groups = [];

        $editableTagGroups = Craft::$app->getTags()->getAllTagGroups();

        if (!empty($editableTagGroups)) {
            foreach ($editableTagGroups as $group) {
                $groups[$group->id] = Craft::t('site', $group->name);
            }
        }

        return $groups;
    }

    /**
     * Return all tag group handles.
     *
     * @return array
     */
    private function getGroupsHandles()
    {
        $groups = [];

        $editableTagGroups = Craft::$app->getTags()->getAllTagGroups();

        if (!empty($editableTagGroups)) {
            foreach ($editableTagGroups as $group) {
                $groups[$group->id] = $group->handle;
            }
        }

        return $groups;
    }

    /**
     * Return all tag groups as option arrays carrying each group's handle, so
     * the field settings can be filtered by name or by handle.
     *
     * @return array
     */
    private function getGroupOptions(): array
    {
        $handles = $this->getGroupsHandles();

        $options = [];
        foreach ($this->getGroups() as $id => $name) {
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
     * Return groups without excluded groups
     *
     * @return array
     */
    public function getAllowedGroups(): array
    {
        $groups = $this->getGroups();
        $excludedGroups = $this->excludedGroups;

        if (!empty($excludedGroups)  && !empty($this->selectAll) ) {
            $excludedGroups = array_map(function($value) {
                return intval($value);
            }, $excludedGroups);

            foreach ($excludedGroups as $value) {
                unset($groups[$value]);
            }
        }

        return $groups;
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
            $item = Craft::$app->getTags()->getTagGroupById($id);
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
        $options = $this->getGroups();

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
            'section-and-product-type/_components/fields/taggroup/_settings',
            [
                'field' => $this,
                'groups' => $this->getGroups(),
                'groupOptions' => $this->getGroupOptions(),
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
        if (empty($this->allowedGroups) && empty($this->selectAll) && empty($this->partOfHandle)) {
            return 'You have not selected any groups for selection, select in the field settings.';
        }

        $groups = $this->getGroups();
        $allowGroupsConfig = $this->allowedGroups;

        if ($this->selectAll) {
            if (is_array($this->excludedGroups)) {
                foreach ($this->excludedGroups as $groupsId) {
                    unset($groups[$groupsId]);
                }
            }
            $allowGroupsConfig = array_keys($groups);
        } else if(!empty($this->partOfHandle)) {
            foreach ($this->getGroupsHandles() as $id => $handle) {
                if (stripos($handle, $this->partOfHandle) !== false
                    && !in_array($id, $allowGroupsConfig)
                    && !in_array($id, $this->excludedGroups)) {
                    $allowGroupsConfig[] = $id;
                }
            }
        }

        $allowGroups = array_flip($allowGroupsConfig);
        $allowGroups[''] = true;
        if (!$this->multiple && !$this->required) {
            $groups = ['' => Craft::t('app', 'None')] + $groups;
        }
        $allowGroups = array_intersect_key($groups, $allowGroups);

        return Craft::$app->getView()->renderTemplate(
            'section-and-product-type/_components/fields/taggroup/_input', [
                'field' => $this,
                'value' => $value,
                'groups' => $allowGroups,
                'dropdown' => $this->viewMode === SectionAndProductType::VIEW_MODE_DROPDOWN,
            ]
        );
    }
}
