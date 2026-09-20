<?php

namespace wmd\sectionandproducttype\fields;

use Craft;
use craft\base\ElementInterface;
use craft\base\Field;
use craft\base\PreviewableFieldInterface;
use craft\helpers\Html;
use craft\helpers\Json;
use GraphQL\Type\Definition\Type;
use wmd\sectionandproducttype\assetbundles\FieldSettingsAsset;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\SectionAndProductType;
use yii\db\Schema;

/**
 * Everything the four field types share.
 *
 * A subclass names the two settings attributes that hold its allow and
 * exclude lists (they differ per type for historical reasons and are part of
 * project config, so they stay), says how to load its items, and provides the
 * words used in the control panel.
 */
abstract class BaseTypeField extends Field implements PreviewableFieldInterface
{
    /** @var bool Make every item available, minus the exclude list. */
    public bool $selectAll = false;

    /** @var bool Let the author pick more than one item. */
    public bool $multiple = false;

    /** @var string Also allow every item whose handle contains this text. */
    public string $partOfHandle = '';

    /** @var string `list` (radio buttons or checkboxes) or `dropdown` (a select menu). */
    public string $viewMode = SectionAndProductType::VIEW_MODE_LIST;

    /** @var string `ids` (the selected IDs) or `objects` (a SelectedItems wrapper). */
    public string $valueType = SectionAndProductType::VALUE_TYPE_IDS;

    /**
     * Name of the settings attribute holding the allow list, e.g. `allowedSections`.
     */
    abstract protected static function allowedAttribute(): string;

    /**
     * Name of the settings attribute holding the exclude list, e.g. `excludedSections`.
     */
    abstract protected static function excludedAttribute(): string;

    /**
     * Every item this field can offer, keyed by ID. Each item exposes `id`,
     * `name` and `handle`.
     *
     * @return array<int, object>
     */
    abstract protected function loadItems(): array;

    /**
     * One item by ID, or null when it no longer exists.
     */
    abstract protected function loadItem(int $id): ?object;

    /**
     * English words for the control panel: `['singular' => 'section', 'plural' => 'sections']`.
     *
     * @return array{singular: string, plural: string}
     */
    abstract protected static function itemWords(): array;

    /**
     * @inheritdoc
     */
    public static function dbType(): string
    {
        return Schema::TYPE_STRING;
    }

    /**
     * @inheritdoc
     */
    public function rules(): array
    {
        $rules = parent::rules();
        $rules[] = [[static::allowedAttribute()], 'validateAllowedItems'];
        $rules[] = [
            ['valueType'],
            'in',
            'range' => [SectionAndProductType::VALUE_TYPE_IDS, SectionAndProductType::VALUE_TYPE_OBJECTS],
        ];
        $rules[] = [
            ['viewMode'],
            'in',
            'range' => [SectionAndProductType::VIEW_MODE_LIST, SectionAndProductType::VIEW_MODE_DROPDOWN],
        ];

        return $rules;
    }

    /**
     * Every ID in the allow list must still exist.
     */
    public function validateAllowedItems(string $attribute): void
    {
        $items = $this->items();

        foreach ((array)$this->$attribute as $id) {
            if (!isset($items[(int)$id])) {
                $this->addError($attribute, Craft::t(
                    'section-and-product-type',
                    'Invalid {singular} selected.',
                    ['singular' => static::itemWords()['singular']]
                ));
            }
        }
    }

    /**
     * The allow list, as integer IDs.
     *
     * @return int[]
     */
    public function allowedIds(): array
    {
        return array_map('intval', (array)$this->{static::allowedAttribute()});
    }

    /**
     * The exclude list, as integer IDs.
     *
     * @return int[]
     */
    public function excludedIds(): array
    {
        return array_map('intval', (array)$this->{static::excludedAttribute()});
    }

    /**
     * Names of every item, keyed by ID.
     *
     * @return array<int, string>
     */
    protected function items(): array
    {
        $names = [];
        foreach ($this->loadItems() as $id => $item) {
            $names[(int)$id] = Craft::t('site', (string)$item->name);
        }

        return $names;
    }

    /**
     * Handles of every item, keyed by ID.
     *
     * @return array<int, string>
     */
    protected function handles(): array
    {
        $handles = [];
        foreach ($this->loadItems() as $id => $item) {
            $handles[(int)$id] = (string)($item->handle ?? '');
        }

        return $handles;
    }

    /**
     * Items as option arrays carrying the handle, so the settings lists can
     * be filtered by name or handle.
     */
    protected function options(): array
    {
        $handles = $this->handles();
        $options = [];

        foreach ($this->items() as $id => $name) {
            $options[] = [
                'label' => $name,
                'value' => $id,
                'data' => ['handle' => $handles[$id] ?? ''],
            ];
        }

        return $options;
    }

    /**
     * Names of the items an author may pick from, keyed by ID: the allow list,
     * plus handle matches, or everything minus the exclude list.
     *
     * @return array<int, string>
     */
    public function getAllowedItems(): array
    {
        $items = $this->items();

        if ($this->selectAll) {
            foreach ($this->excludedIds() as $id) {
                unset($items[$id]);
            }

            return $items;
        }

        $allowed = $this->allowedIds();

        if ($this->partOfHandle !== '') {
            $excluded = $this->excludedIds();
            foreach ($this->handles() as $id => $handle) {
                if (stripos($handle, $this->partOfHandle) !== false && !in_array($id, $excluded, true)) {
                    $allowed[] = $id;
                }
            }
        }

        return array_intersect_key($items, array_flip($allowed));
    }

    /**
     * @inheritdoc
     */
    public function normalizeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        if ($value instanceof SelectedItems) {
            $value = $value->ids();
        }

        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (is_array($value)) {
            $value = array_values(array_map('intval', array_filter($value, static fn($id) => $id !== '' && $id !== null)));
        }

        if ($this->multiple) {
            $value = $value === null || $value === '' ? [] : (is_array($value) ? $value : [(int)$value]);
        } elseif (is_array($value)) {
            $value = $value[0] ?? null;
        } elseif ($value !== null && $value !== '') {
            $value = (int)$value;
        }

        if ($this->valueType === SectionAndProductType::VALUE_TYPE_OBJECTS) {
            return $this->toSelectedItems($value);
        }

        return $value;
    }

    /**
     * @inheritdoc
     */
    public function serializeValue(mixed $value, ?ElementInterface $element = null): mixed
    {
        $ids = $this->valueIds($value);

        return Json::encode($this->multiple ? $ids : ($ids[0] ?? ''));
    }

    /**
     * @inheritdoc
     */
    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        return $this->valueIds($value) === [];
    }

    /**
     * @inheritdoc
     */
    public function getPreviewHtml(mixed $value, ElementInterface $element): string
    {
        $items = $this->items();
        $names = [];

        foreach ($this->valueIds($value) as $id) {
            if (isset($items[$id])) {
                $names[] = $items[$id];
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
    public function getSettingsHtml(): ?string
    {
        $view = Craft::$app->getView();
        $view->registerAssetBundle(FieldSettingsAsset::class);

        return $view->renderTemplate('section-and-product-type/_components/fields/_settings', [
            'field' => $this,
            'words' => static::itemWords(),
            'allowedAttribute' => static::allowedAttribute(),
            'excludedAttribute' => static::excludedAttribute(),
            'allowedValues' => $this->selectAll ? [true] : $this->allowedIds(),
            'excludedValues' => $this->excludedIds(),
            'options' => $this->options(),
            'viewModes' => SectionAndProductType::viewModeOptions(),
            'valueTypes' => SectionAndProductType::valueTypeOptions(),
        ]);
    }

    /**
     * @inheritdoc
     */
    public function getInputHtml(mixed $value, ?ElementInterface $element = null): string
    {
        $words = static::itemWords();

        if (!$this->selectAll && $this->allowedIds() === [] && $this->partOfHandle === '') {
            return Html::tag('p', Craft::t(
                'section-and-product-type',
                'No {plural} are allowed for this field yet. Allow some in the field settings.',
                ['plural' => $words['plural']]
            ), ['class' => 'light']);
        }

        $options = $this->getAllowedItems();
        $ids = $this->valueIds($value);

        // Keep a stored selection visible even if it has since been excluded,
        // so saving the entry does not silently drop it.
        $items = $this->items();
        foreach ($ids as $id) {
            if (!isset($options[$id]) && isset($items[$id])) {
                $options[$id] = $items[$id];
            }
        }

        if (!$this->multiple && !$this->required) {
            $options = ['' => Craft::t('app', 'None')] + $options;
        }

        return Craft::$app->getView()->renderTemplate('section-and-product-type/_components/fields/_input', [
            'field' => $this,
            'value' => $this->multiple ? $ids : ($ids[0] ?? ''),
            'options' => $options,
            'words' => $words,
            'dropdown' => $this->viewMode === SectionAndProductType::VIEW_MODE_DROPDOWN,
        ]);
    }

    /**
     * Wrap the selected IDs in a SelectedItems object holding the models.
     */
    protected function toSelectedItems(mixed $value): SelectedItems
    {
        $items = [];

        foreach ($this->valueIds($value) as $id) {
            $item = $this->loadItem($id);
            if ($item) {
                $items[] = $item;
            }
        }

        return new SelectedItems($items);
    }

    /**
     * Reduce any shape this field's value can take to a list of integer IDs.
     *
     * @return int[]
     */
    protected function valueIds(mixed $value): array
    {
        if ($value instanceof SelectedItems) {
            return $value->ids();
        }

        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (!is_array($value)) {
            $value = ($value === null || $value === '') ? [] : [$value];
        }

        return array_values(array_map('intval', array_filter($value, static fn($id) => $id !== '' && $id !== null)));
    }
}
