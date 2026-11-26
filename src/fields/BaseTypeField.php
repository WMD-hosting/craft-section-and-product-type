<?php

namespace wmd\sectionandproducttype\fields;

use Closure;
use CraftCms\Cms\Element\Contracts\ElementInterface;
use CraftCms\Cms\Field\Contracts\InputSchemaFieldInterface;
use CraftCms\Cms\Field\Contracts\PreviewableFieldInterface;
use CraftCms\Cms\Field\Field;
use CraftCms\Cms\Field\FieldContext;
use CraftCms\Cms\Support\Html;
use CraftCms\Cms\Support\Json;
use CraftCms\Cms\Support\Query;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Combobox;
use CraftCms\Cms\Ui\Controls\Lightswitch;
use CraftCms\Cms\Ui\Controls\Text;
use CraftCms\Cms\Ui\Enums\ChoicePresentation;
use CraftCms\Cms\Ui\Nodes\Field as UiField;
use CraftCms\Cms\Ui\Ui;
use CraftCms\Cms\Ui\UiContext;
use GraphQL\Type\Definition\Type;
use Illuminate\Validation\Rule;
use Override;
use wmd\sectionandproducttype\helpers\ItemReferences;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\SectionAndProductType as Plugin;

use function CraftCms\Cms\t;

/**
 * Everything the field types share.
 *
 * A subclass names the two settings attributes that hold its allow and
 * exclude lists (they differ per type for historical reasons and are part of
 * project config, so they stay), says how to load its items, and provides the
 * words used in the control panel.
 *
 * The lists are saved as UIDs, because project config travels between installs
 * and an ID names a different item on each. A field value on an element is
 * content and stays IDs: an integer, or a list of integers with Multiple Select.
 *
 * @author WMD Hosting
 *
 * @since 2.2.0
 */
abstract class BaseTypeField extends Field implements InputSchemaFieldInterface, PreviewableFieldInterface
{
    // Public Properties
    // =========================================================================

    /** Make every item available. The exclude list still applies. */
    public bool $selectAll = false;

    /** Let the author pick more than one item. */
    public bool $multiple = false;

    /** Also allow every item whose handle contains this text. */
    public string $partOfHandle = '';

    /** `list` (radio buttons or checkboxes) or `dropdown` (a select menu). */
    public string $viewMode = Plugin::VIEW_MODE_LIST;

    /** `ids` (the selected IDs) or `objects` (a SelectedItems wrapper). */
    public string $valueType = Plugin::VALUE_TYPE_IDS;

    // Abstract Methods
    // =========================================================================

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
     * `uid`, `name` and `handle`.
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

    // Static Methods
    // =========================================================================

    #[Override]
    public static function phpType(): string
    {
        return sprintf('int|int[]|%s|null', SelectedItems::class);
    }

    #[Override]
    public static function dbType(): string
    {
        return Query::TYPE_JSON;
    }

    // Settings
    // =========================================================================

    /**
     * {@inheritdoc}
     *
     * Craft only saves public properties declared outside abstract classes, so the shared
     * settings declared here must be listed, or every field save drops them.
     */
    #[Override]
    public function settingsAttributes(): array
    {
        return array_values(array_unique([
            ...parent::settingsAttributes(),
            'selectAll',
            'multiple',
            'partOfHandle',
            'viewMode',
            'valueType',
        ]));
    }

    /**
     * {@inheritdoc}
     *
     * The allow and exclude lists are written as UIDs, so project config means the
     * same items on every install.
     */
    #[Override]
    public function getSettings(): array
    {
        $settings = parent::getSettings();
        $uids = $this->uids();

        foreach ([static::allowedAttribute(), static::excludedAttribute()] as $attribute) {
            if (array_key_exists($attribute, $settings)) {
                $settings[$attribute] = ItemReferences::toUids((array) $settings[$attribute], $uids);
            }
        }

        return $settings;
    }

    #[Override]
    public function getRules(): array
    {
        return array_merge(parent::getRules(), [
            'viewMode' => [Rule::in([Plugin::VIEW_MODE_LIST, Plugin::VIEW_MODE_DROPDOWN])],
            'valueType' => [Rule::in([Plugin::VALUE_TYPE_IDS, Plugin::VALUE_TYPE_OBJECTS])],
            static::allowedAttribute() => ['array', $this->existingItemsRule()],
            static::excludedAttribute() => ['array'],
        ]);
    }

    #[Override]
    public function settingsUi(UiContext $context = new UiContext): Ui
    {
        $words = static::itemWords();
        $uids = $this->uids();
        $options = $this->settingsOptions();
        $resolvable = $this->canResolveItems();
        $allowed = ItemReferences::forForm((array) $this->{static::allowedAttribute()}, $uids, $resolvable);
        $excluded = ItemReferences::forForm((array) $this->{static::excludedAttribute()}, $uids, $resolvable);
        // The lists then hold references instead of names, so say why.
        $keptAsStored = $resolvable ? null : $this->unresolvableReason().' '.t('The saved list is shown as it is stored and stays that way when you save.', category: Plugin::T9N);

        return Ui::make([
            UiField::make(t('Multiple Select', category: Plugin::T9N))
                ->instructions(t('Let the author pick more than one {singular}.', ['singular' => $words['singular']], Plugin::T9N))
                ->control(Lightswitch::make('multiple')->value($this->multiple)),
            UiField::make(t('View Mode', category: Plugin::T9N))
                ->instructions(t('How the {plural} are presented when editing an entry. Choose Dropdown when there are many to pick from.', ['plural' => $words['plural']], Plugin::T9N))
                ->control(Choice::make('viewMode')
                    ->options(Plugin::viewModeOptions())
                    ->presentation(ChoicePresentation::Select)
                    ->withoutPlaceholder()
                    ->value($this->viewMode)),
            UiField::make(t('Template Value', category: Plugin::T9N))
                ->instructions(t('What the field hands back in templates. IDs returns the selected IDs. Objects returns an object whose all(), ids(), handles() and names() methods give you the {plural} themselves.', ['plural' => $words['plural']], Plugin::T9N))
                ->control(Choice::make('valueType')
                    ->options(Plugin::valueTypeOptions())
                    ->presentation(ChoicePresentation::Select)
                    ->withoutPlaceholder()
                    ->value($this->valueType)),
            UiField::make(t('Select All', category: Plugin::T9N))
                ->instructions(t('Make every {singular} available, minus the exclude list below.', ['singular' => $words['singular']], Plugin::T9N))
                ->control(Lightswitch::make('selectAll')->value($this->selectAll)),
            UiField::make(t('Select By Text In Handle', category: Plugin::T9N))
                ->instructions(t('Also allow every {singular} whose handle contains this text.', ['singular' => $words['singular']], Plugin::T9N))
                ->control(Text::make('partOfHandle')->value($this->partOfHandle)),
            UiField::make(t('Allowed', category: Plugin::T9N))
                ->instructions(t('The {plural} an author may choose. Ignored while Select All is on. Type to filter by name or handle.', ['plural' => $words['plural']], Plugin::T9N))
                ->control(Combobox::make(static::allowedAttribute())
                    ->multiple()
                    ->requireOptionMatch()
                    ->showAllOnEmpty()
                    ->options($options)
                    ->placeholder(t('Search {plural}', ['plural' => $words['plural']], Plugin::T9N))
                    ->value($allowed))
                ->warning($allowed !== [] ? $keptAsStored : null),
            UiField::make(t('Excluded', category: Plugin::T9N))
                ->instructions(t('Never offered to authors, whether Select All, the Allowed list or the handle text would offer them.', category: Plugin::T9N))
                ->control(Combobox::make(static::excludedAttribute())
                    ->multiple()
                    ->requireOptionMatch()
                    ->showAllOnEmpty()
                    ->options($options)
                    ->placeholder(t('Search {plural}', ['plural' => $words['plural']], Plugin::T9N))
                    ->value($excluded))
                ->warning($excluded !== [] ? $keptAsStored : null),
        ]);
    }

    // Allow and exclude lists
    // =========================================================================

    /**
     * The allow list, as the IDs of the items this install has.
     *
     * @return int[]
     */
    public function allowedIds(): array
    {
        return ItemReferences::toIds((array) $this->{static::allowedAttribute()}, $this->uids());
    }

    /**
     * The exclude list, as the IDs of the items this install has.
     *
     * @return int[]
     */
    public function excludedIds(): array
    {
        return ItemReferences::toIds((array) $this->{static::excludedAttribute()}, $this->uids());
    }

    /**
     * Names of the items an author may pick from, keyed by ID: everything with
     * Select All on, otherwise the allow list plus the handle matches; minus the
     * exclude list either way.
     *
     * @return array<int, string>
     */
    public function getAllowedItems(): array
    {
        $items = $this->items();
        $handleMatches = [];

        if (! $this->selectAll && $this->partOfHandle !== '') {
            foreach ($this->handles() as $id => $handle) {
                if (stripos($handle, $this->partOfHandle) !== false) {
                    $handleMatches[] = $id;
                }
            }
        }

        $ids = ItemReferences::availableIds(
            all: array_keys($items),
            selectAll: $this->selectAll,
            allowed: $this->allowedIds(),
            handleMatches: $handleMatches,
            excluded: $this->excludedIds(),
        );

        return array_intersect_key($items, array_flip($ids));
    }

    // Values
    // =========================================================================

    #[Override]
    public function normalizeValue(mixed $value, ?ElementInterface $element): mixed
    {
        $ids = $this->valueIds($value);

        if (! $this->multiple) {
            // A single select holds one ID, whichever Template Value is set. A list left over
            // from when Multiple Select was on is read as its first ID.
            $ids = array_slice($ids, 0, 1);
        }

        if ($this->valueType === Plugin::VALUE_TYPE_OBJECTS) {
            return $this->toSelectedItems($ids);
        }

        return $this->multiple ? $ids : ($ids[0] ?? null);
    }

    /**
     * {@inheritdoc}
     *
     * Stored as the field's own JSON: an integer, or a list of integers with Multiple Select.
     */
    #[Override]
    public function serializeValue(mixed $value, ?ElementInterface $element): mixed
    {
        $ids = $this->valueIds($value);

        return $this->multiple ? $ids : ($ids[0] ?? null);
    }

    #[Override]
    public function isValueEmpty(mixed $value, ElementInterface $element): bool
    {
        return $this->valueIds($value) === [];
    }

    /**
     * Every stored ID must name an item this install has. Items left out of the
     * field settings since are accepted, so an existing selection survives a save.
     *
     * Both Template Value settings hand this the same IDs, the ones that name nothing
     * included. Nothing is judged while the items cannot be told (see canResolveItems()).
     */
    #[Override]
    public function getElementRules(ElementInterface $element): array
    {
        return [
            function (string $attribute, mixed $value, Closure $fail): void {
                if (! $this->canResolveItems()) {
                    return;
                }

                $items = $this->items();

                foreach ($this->valueIds($value) as $id) {
                    if (! isset($items[$id])) {
                        $fail(t('{attribute} is invalid.', ['attribute' => t($this->name, category: 'site')]));

                        return;
                    }
                }
            },
        ];
    }

    #[Override]
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

    #[Override]
    protected function searchKeywords(mixed $value, ElementInterface $element): string
    {
        return strip_tags($this->getPreviewHtml($value, $element));
    }

    // Input
    // =========================================================================

    #[Override]
    public function uiControl(FieldContext $context): Control
    {
        $ids = $this->valueIds($context->value);
        $options = [];

        foreach ($this->inputItems($ids) as $id => $name) {
            $options[] = ['label' => $name, 'value' => (string) $id];
        }

        $dropdown = $this->viewMode === Plugin::VIEW_MODE_DROPDOWN;

        if ($this->multiple) {
            $values = array_map('strval', $ids);

            if ($dropdown) {
                return Combobox::make($context->path)
                    ->multiple()
                    ->requireOptionMatch()
                    ->showAllOnEmpty()
                    ->options($options)
                    ->value($values);
            }

            return Choice::make($context->path)
                ->options($options)
                ->multiple()
                ->presentation(ChoicePresentation::Checkboxes)
                ->value($values);
        }

        // Craft 6 keeps "required" on the field layout, which the control is not told about,
        // so a single select always offers None; a required field still fails validation empty.
        array_unshift($options, ['label' => t('None'), 'value' => '']);

        return Choice::make($context->path)
            ->options($options)
            ->presentation($dropdown ? ChoicePresentation::Select : ChoicePresentation::Radios)
            ->withoutPlaceholder()
            ->value(isset($ids[0]) ? (string) $ids[0] : '');
    }

    #[Override]
    public function uiWarning(?ElementInterface $element = null): ?string
    {
        if (! $this->canResolveItems()) {
            return $this->unresolvableReason().' '.t('What is selected here is kept.', category: Plugin::T9N);
        }

        if ($this->selectAll || $this->allowedIds() !== [] || $this->partOfHandle !== '') {
            return null;
        }

        return t('No {plural} are allowed for this field yet. Allow some in the field settings.', ['plural' => static::itemWords()['plural']], Plugin::T9N);
    }

    #[Override]
    public function useFieldset(): bool
    {
        return $this->viewMode === Plugin::VIEW_MODE_LIST;
    }

    // API
    // =========================================================================

    /**
     * {@inheritdoc}
     *
     * The IDs an author may pick, so MCP clients and agents send a valid value.
     */
    #[Override]
    public function getInputSchema(): array
    {
        $items = $this->getAllowedItems();
        $handles = $this->handles();
        $item = ['type' => 'integer'];

        if ($items !== []) {
            $item['enum'] = array_keys($items);
        }

        $item['description'] = implode('; ', array_map(
            static fn (int $id, string $name) => sprintf('%d = %s (%s)', $id, $name, $handles[$id] ?? ''),
            array_keys($items),
            $items,
        ));

        return $this->multiple ? ['type' => 'array', 'items' => $item] : $item;
    }

    #[Override]
    public function getContentGqlType(): Type|array
    {
        return [
            'name' => $this->handle,
            'type' => $this->multiple ? Type::listOf(Type::int()) : Type::int(),
            'resolve' => function ($source) {
                $ids = $this->valueIds($source->getFieldValue($this->handle));

                return $this->multiple ? $ids : ($ids[0] ?? null);
            },
        ];
    }

    #[Override]
    public function getContentGqlMutationArgumentType(): Type|array
    {
        return $this->multiple ? Type::listOf(Type::int()) : Type::int();
    }

    // Protected Methods
    // =========================================================================

    /**
     * Whether this install can tell which items of this kind exist.
     *
     * It always can for sections and entry types. It cannot for product types while Commerce is
     * not installed or is disabled: none is listed then, so a reference that is still good looks
     * exactly like a deleted one. While that lasts the lists and the values are kept as they are
     * stored instead of being resolved, validated or cleaned up.
     *
     * An install that can tell and simply has no items is a different case: every reference
     * there is known to name nothing, and is treated like any deleted item.
     */
    protected function canResolveItems(): bool
    {
        return true;
    }

    /**
     * Why the items cannot be told, as a sentence for the control panel. Only asked while
     * canResolveItems() is false.
     */
    protected function unresolvableReason(): string
    {
        return t('The {plural} cannot be listed on this install.', ['plural' => static::itemWords()['plural']], Plugin::T9N);
    }

    /**
     * UIDs of every item, keyed by ID.
     *
     * @return array<int, string>
     */
    protected function uids(): array
    {
        $uids = [];
        foreach ($this->loadItems() as $id => $item) {
            $uids[(int) $id] = (string) $item->uid;
        }

        return $uids;
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
            $names[(int) $id] = t((string) $item->name, category: 'site');
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
            $handles[(int) $id] = (string) ($item->handle ?? '');
        }

        return $handles;
    }

    /**
     * Options for the settings lists: the UID as value (that is what the lists
     * save), labelled with the name and handle so typing either filters.
     *
     * @return list<array{label: string, value: string}>
     */
    protected function settingsOptions(): array
    {
        $handles = $this->handles();
        $uids = $this->uids();
        $options = [];

        foreach ($this->items() as $id => $name) {
            $handle = $handles[$id] ?? '';
            $options[] = [
                'label' => $handle !== '' ? sprintf('%s (%s)', $name, $handle) : $name,
                'value' => $uids[$id] ?? (string) $id,
            ];
        }

        if (! $this->canResolveItems()) {
            // The lists are shown as stored. They require an option match, so every reference
            // they keep is an option, labelled as itself.
            $stored = [...(array) $this->{static::allowedAttribute()}, ...(array) $this->{static::excludedAttribute()}];

            foreach (ItemReferences::forForm($stored, [], false) as $reference) {
                $options[] = ['label' => $reference, 'value' => $reference];
            }
        }

        return $options;
    }

    /**
     * The items the input offers: the allowed ones, plus every stored selection that is not
     * among them, so the author sees what the field holds and saving the entry does not
     * silently drop it. A selection that has since been excluded keeps its name. One that names
     * nothing on this install is labelled as missing; it fails validation until the author
     * removes it (see getElementRules()).
     *
     * @param  int[]  $selected
     * @return array<int, string>
     */
    protected function inputItems(array $selected): array
    {
        $options = $this->getAllowedItems();
        $items = $this->items();

        // While the items cannot be told, a stored ID is not known to be missing: it is shown as
        // what it is. The ID goes in as a string, or the message formatter groups its digits.
        $unnamed = $this->canResolveItems() ? 'Missing {singular} (ID {id})' : 'ID {id}';

        foreach ($selected as $id) {
            $options[$id] ??= $items[$id] ?? t($unnamed, ['singular' => static::itemWords()['singular'], 'id' => (string) $id], Plugin::T9N);
        }

        return $options;
    }

    /**
     * Wrap the selected IDs in a SelectedItems object holding the models. The IDs that name
     * nothing on this install travel with it, so validating and saving see the same IDs as
     * with Template Value set to IDs.
     *
     * @param  int[]  $ids
     */
    protected function toSelectedItems(array $ids): SelectedItems
    {
        $items = [];

        foreach ($ids as $id) {
            $item = $this->loadItem($id);
            if ($item) {
                $items[] = $item;
            }
        }

        return new SelectedItems($items, $ids);
    }

    /**
     * Reduce any shape this field's value can take to a list of integer IDs:
     * an ID, a list of IDs, their JSON (as fields saved before 3.0 stored them),
     * the strings a form posts, or a SelectedItems object.
     *
     * @return int[]
     */
    protected function valueIds(mixed $value): array
    {
        if ($value instanceof SelectedItems) {
            return $value->storedIds();
        }

        if (is_string($value)) {
            $value = Json::decodeIfJson($value);
        }

        if (! is_array($value)) {
            $value = ($value === null || $value === '') ? [] : [$value];
        }

        $ids = [];
        foreach ($value as $id) {
            if (is_int($id) || (is_string($id) && ctype_digit($id))) {
                $ids[(int) $id] = (int) $id;
            }
        }

        return array_values($ids);
    }

    /**
     * Every item in the allow list must exist on this install. While the items cannot be told
     * (see canResolveItems()) there is nothing to look them up in, and each one only has to be
     * shaped like a reference.
     */
    private function existingItemsRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $wrong = $this->canResolveItems()
                ? ItemReferences::unresolved((array) $value, $this->uids())
                : ItemReferences::malformed((array) $value);

            if ($wrong === []) {
                return;
            }

            $fail(t('Invalid {singular} selected.', ['singular' => static::itemWords()['singular']], Plugin::T9N));
        };
    }
}
