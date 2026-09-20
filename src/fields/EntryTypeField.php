<?php

namespace wmd\sectionandproducttype\fields;

use Craft;

/**
 * Selects entry types. Craft 5 decoupled these from sections.
 */
class EntryTypeField extends BaseTypeField
{
    /** @var array Entry type IDs that may be chosen. */
    public array $allowedEntryTypes = [];

    /** @var array Entry type IDs left out when Select All is on. */
    public array $excludedEntryTypes = [];

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
    protected static function allowedAttribute(): string
    {
        return 'allowedEntryTypes';
    }

    /**
     * @inheritdoc
     */
    protected static function excludedAttribute(): string
    {
        return 'excludedEntryTypes';
    }

    /**
     * @inheritdoc
     */
    protected static function itemWords(): array
    {
        return ['singular' => 'entry type', 'plural' => 'entry types'];
    }

    /**
     * @inheritdoc
     */
    protected function loadItems(): array
    {
        $items = [];
        foreach (Craft::$app->getEntries()->getAllEntryTypes() as $entryType) {
            $items[$entryType->id] = $entryType;
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    protected function loadItem(int $id): ?object
    {
        return Craft::$app->getEntries()->getEntryTypeById($id);
    }

    /**
     * @deprecated in 2.2.0. Use getAllowedItems().
     */
    public function getAllowedEntryTypes(): array
    {
        return $this->getAllowedItems();
    }
}
