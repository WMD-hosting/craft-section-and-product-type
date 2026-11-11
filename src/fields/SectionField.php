<?php

namespace wmd\sectionandproducttype\fields;

use CraftCms\Cms\Support\Facades\Sections;
use Override;

use function CraftCms\Cms\t;

/**
 * Selects entry sections.
 */
class SectionField extends BaseTypeField
{
    /** @var array Section UIDs that may be chosen (IDs in fields saved before 2.3.0). */
    public array $allowedSections = [];

    /** @var array Section UIDs never offered (IDs in fields saved before 2.3.0). */
    public array $excludedSections = [];

    #[Override]
    public static function displayName(): string
    {
        return t('Section', category: 'section-and-product-type');
    }

    #[Override]
    protected static function allowedAttribute(): string
    {
        return 'allowedSections';
    }

    #[Override]
    protected static function excludedAttribute(): string
    {
        return 'excludedSections';
    }

    #[Override]
    protected static function itemWords(): array
    {
        return ['singular' => 'section', 'plural' => 'sections'];
    }

    #[Override]
    protected function loadItems(): array
    {
        $items = [];
        foreach (Sections::getAllSections() as $section) {
            $items[$section->id] = $section;
        }

        return $items;
    }

    #[Override]
    protected function loadItem(int $id): ?object
    {
        return Sections::getSectionById($id);
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
