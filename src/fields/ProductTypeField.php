<?php

namespace wmd\sectionandproducttype\fields;

use Craft;
use craft\commerce\Plugin as Commerce;

/**
 * Selects Craft Commerce product types. Offers nothing when Commerce is not installed.
 */
class ProductTypeField extends BaseTypeField
{
    /** @var array Product type IDs that may be chosen. */
    public array $allowProductTypes = [];

    /** @var array Product type IDs left out when Select All is on. */
    public array $excludedProductTypes = [];

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
    protected static function allowedAttribute(): string
    {
        return 'allowProductTypes';
    }

    /**
     * @inheritdoc
     */
    protected static function excludedAttribute(): string
    {
        return 'excludedProductTypes';
    }

    /**
     * @inheritdoc
     */
    protected static function itemWords(): array
    {
        return ['singular' => 'product type', 'plural' => 'product types'];
    }

    /**
     * @inheritdoc
     */
    protected function loadItems(): array
    {
        $commerce = $this->commerce();
        if (!$commerce) {
            return [];
        }

        $items = [];
        foreach ($commerce->getProductTypes()->getAllProductTypes() as $productType) {
            $items[$productType->id] = $productType;
        }

        return $items;
    }

    /**
     * @inheritdoc
     */
    protected function loadItem(int $id): ?object
    {
        return $this->commerce()?->getProductTypes()->getProductTypeById($id);
    }

    /**
     * The Commerce plugin, when it is installed and enabled.
     */
    private function commerce(): ?Commerce
    {
        if (!class_exists(Commerce::class)) {
            return null;
        }

        return Commerce::getInstance();
    }

    /**
     * @deprecated in 2.2.0. Use getAllowedItems().
     */
    public function getAllowedProductTypes(): array
    {
        return $this->getAllowedItems();
    }
}
