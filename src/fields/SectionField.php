<?php

namespace wmd\sectionandproducttype\fields;

use Craft;

/**
 * Selects entry sections.
 */
class SectionField extends BaseTypeField
{
    /** @var array Section UIDs that may be chosen (IDs in fields saved before 2.3.0). */
    public array $allowedSections = [];

    /** @var array Section UIDs never offered (IDs in fields saved before 2.3.0). */
    public array $excludedSections = [];

    /**
     * @inheritdoc
     */
    public static function displayName(): string
    {
        return Craft::t('section-and-product-type', 'Section');
    }

    /**
     * @inheritdoc
     */
    protected static function allowedAttribute(): string
    {
        return 'allowedSections';
    }

    /**
     * @inheritdoc
     */
    protected static function excludedAttribute(): string
    {
        return 'excludedSections';
    }

    /**
     * @inheritdoc
     */
    protected static function itemWords(): array
    {
        return ['singular' => 'section', 'plural' => 'sections'];
    }

    /**
     * @inheritdoc
     */
    protected function loadItems(): array
    {
        $items = [];
        foreach (Craft::$app->getEntries()->getAllSections() as $section) {
            $items[$section->id] = $section;
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    protected function loadItem(int $id): ?object
    {
        return Craft::$app->getEntries()->getSectionById($id);
    }

    /**
     * Names of the sections an author may pick from, keyed by ID.
     *
     * @deprecated in 2.2.0. Use getAllowedItems().
     */
    public function getAllowedSections(): array
    {
        return $this->getAllowedItems();
    }
}
