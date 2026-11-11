<?php

namespace wmd\sectionandproducttype\fields;

use CraftCms\Cms\Support\Facades\Plugins;
use CraftCms\Commerce\Product\ProductType\ProductTypes;
use Override;

use function CraftCms\Cms\t;

/**
 * Selects Craft Commerce product types. Offers nothing when Commerce is not installed.
 */
class ProductTypeField extends BaseTypeField
{
    /** @var array Product type UIDs that may be chosen (IDs in fields saved before 2.3.0). */
    public array $allowProductTypes = [];

    /** @var array Product type UIDs never offered (IDs in fields saved before 2.3.0). */
    public array $excludedProductTypes = [];

    #[Override]
    public static function displayName(): string
    {
        return t('Product Type', category: 'section-and-product-type');
    }

    #[Override]
    protected static function allowedAttribute(): string
    {
        return 'allowProductTypes';
    }

    #[Override]
    protected static function excludedAttribute(): string
    {
        return 'excludedProductTypes';
    }

    #[Override]
    protected static function itemWords(): array
    {
        return ['singular' => 'product type', 'plural' => 'product types'];
    }

    #[Override]
    protected function loadItems(): array
    {
        $commerce = $this->commerce();
        if (!$commerce) {
            return [];
        }

        $items = [];
        foreach ($commerce->getAllProductTypes() as $productType) {
            $items[$productType->id] = $productType;
        }

        return $items;
    }

    #[Override]
    protected function loadItem(int $id): ?object
    {
        return $this->commerce()?->getProductTypeById($id);
    }

    /**
     * Commerce's product type service, when Commerce is installed and enabled.
     * Without it the field offers nothing instead of failing.
     */
    private function commerce(): ?ProductTypes
    {
        if (!class_exists(ProductTypes::class) || !Plugins::isPluginEnabled('commerce')) {
            return null;
        }

        return app(ProductTypes::class);
    }

    /**
     * @deprecated in 2.2.0. Use getAllowedItems().
     */
    public function getAllowedProductTypes(): array
    {
        return $this->getAllowedItems();
    }
}
