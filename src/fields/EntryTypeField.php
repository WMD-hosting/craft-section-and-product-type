<?php

namespace wmd\sectionandproducttype\fields;

use CraftCms\Cms\Support\Facades\EntryTypes;
use Override;

use function CraftCms\Cms\t;

/**
 * Selects entry types. Craft 5 decoupled these from sections.
 */
class EntryTypeField extends BaseTypeField
{
    /** @var array Entry type UIDs that may be chosen (IDs in fields saved before 2.3.0). */
    public array $allowedEntryTypes = [];

    /** @var array Entry type UIDs never offered (IDs in fields saved before 2.3.0). */
    public array $excludedEntryTypes = [];

    #[Override]
    public static function displayName(): string
    {
        return t('Entry Type', category: 'section-and-product-type');
    }

    #[Override]
    protected static function allowedAttribute(): string
    {
        return 'allowedEntryTypes';
    }

    #[Override]
    protected static function excludedAttribute(): string
    {
        return 'excludedEntryTypes';
    }

    #[Override]
    protected static function itemWords(): array
    {
        return ['singular' => 'entry type', 'plural' => 'entry types'];
    }

    #[Override]
    protected function loadItems(): array
    {
        $items = [];
        foreach (EntryTypes::getAllEntryTypes() as $entryType) {
            $items[$entryType->id] = $entryType;
        }

        return $items;
    }

    #[Override]
    protected function loadItem(int $id): ?object
    {
        return EntryTypes::getEntryTypeById($id);
    }

    /**
     * @deprecated in 2.2.0. Use getAllowedItems().
     */
    public function getAllowedEntryTypes(): array
    {
        return $this->getAllowedItems();
    }
}
