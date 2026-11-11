<?php

namespace wmd\sectionandproducttype;

use CraftCms\Cms\Plugin\Plugin;
use wmd\sectionandproducttype\fields\EntryTypeField;
use wmd\sectionandproducttype\fields\ProductTypeField;
use wmd\sectionandproducttype\fields\SectionField;

use function CraftCms\Cms\t;

/**
 * Field types that select parts of the content model: sections, entry types
 * and Commerce product types.
 *
 * @author WMD Hosting
 * @since 1.0.0
 */
class SectionAndProductType extends Plugin
{
    // Const Properties
    // =========================================================================

    /** View mode that renders a field's options as radio buttons or checkboxes. */
    public const VIEW_MODE_LIST = 'list';

    /** View mode that renders a field's options as a select menu. */
    public const VIEW_MODE_DROPDOWN = 'dropdown';

    /** Template value that returns the selected IDs, as this plugin has always done. */
    public const VALUE_TYPE_IDS = 'ids';

    /** Template value that returns a SelectedItems object wrapping the selected models. */
    public const VALUE_TYPE_OBJECTS = 'objects';

    /** Translation category of the plugin's own strings. */
    public const T9N = 'section-and-product-type';

    // Public Properties
    // =========================================================================

    public string $schemaVersion = '3.0.0';

    // Protected Properties
    // =========================================================================

    protected array $fieldTypes = [
        SectionField::class,
        EntryTypeField::class,
        ProductTypeField::class,
    ];

    // Public Methods
    // =========================================================================

    /**
     * The view mode options offered by every field type of this plugin.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function viewModeOptions(): array
    {
        return [
            ['label' => t('List', category: self::T9N), 'value' => self::VIEW_MODE_LIST],
            ['label' => t('Dropdown', category: self::T9N), 'value' => self::VIEW_MODE_DROPDOWN],
        ];
    }

    /**
     * The template value options offered by every field type of this plugin.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function valueTypeOptions(): array
    {
        return [
            ['label' => t('IDs', category: self::T9N), 'value' => self::VALUE_TYPE_IDS],
            ['label' => t('Objects', category: self::T9N), 'value' => self::VALUE_TYPE_OBJECTS],
        ];
    }
}
