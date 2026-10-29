<?php

namespace wmd\sectionandproducttype\fields;

use Craft;

/**
 * Selects tag groups.
 */
class TagGroupField extends BaseTypeField
{
    /** @var array Tag group UIDs that may be chosen (IDs in fields saved before 2.3.0). */
    public array $allowedGroups = [];

    /** @var array Tag group UIDs never offered (IDs in fields saved before 2.3.0). */
    public array $excludedGroups = [];

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
    protected static function allowedAttribute(): string
    {
        return 'allowedGroups';
    }

    /**
     * @inheritdoc
     */
    protected static function excludedAttribute(): string
    {
        return 'excludedGroups';
    }

    /**
     * @inheritdoc
     */
    protected static function itemWords(): array
    {
        return ['singular' => 'tag group', 'plural' => 'tag groups'];
    }

    /**
     * @inheritdoc
     */
    protected function loadItems(): array
    {
        $items = [];
        foreach (Craft::$app->getTags()->getAllTagGroups() as $group) {
            $items[$group->id] = $group;
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    protected function loadItem(int $id): ?object
    {
        return Craft::$app->getTags()->getTagGroupById($id);
    }

    /**
     * @deprecated in 2.2.0. Use getAllowedItems().
     */
    public function getAllowedGroups(): array
    {
        return $this->getAllowedItems();
    }
}
