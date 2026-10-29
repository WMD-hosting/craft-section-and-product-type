<?php

namespace wmd\sectionandproducttype;

use Craft;
use craft\base\Plugin;
use craft\events\RegisterComponentTypesEvent;
use craft\services\Fields;
use wmd\sectionandproducttype\fields\EntryTypeField;
use wmd\sectionandproducttype\fields\ProductTypeField;
use wmd\sectionandproducttype\fields\SectionField;
use wmd\sectionandproducttype\fields\TagGroupField;
use yii\base\Event;

class SectionAndProductType extends Plugin
{
    /**
     * @var string View mode that renders a field's options as radio buttons or checkboxes.
     */
    public const VIEW_MODE_LIST = 'list';

    /**
     * @var string View mode that renders a field's options as a select menu.
     */
    public const VIEW_MODE_DROPDOWN = 'dropdown';

    /**
     * @var string Template value that returns the selected IDs, as this plugin has always done.
     */
    public const VALUE_TYPE_IDS = 'ids';

    /**
     * @var string Template value that returns a SelectedItems object wrapping the selected models.
     */
    public const VALUE_TYPE_OBJECTS = 'objects';

    /**
     * @var SectionAndProductType
     */
    public static $plugin;

    /**
     * @var string
     */
    public string $schemaVersion = '2.0.8';

    /**
     * @var bool
     */
    public bool $hasCpSettings = false;

    /**
     * @var bool
     */
    public bool $hasCpSection = false;

    /**
     * @inheritdoc
     */
    public function init()
    {
        parent::init();
        self::$plugin = $this;

        Event::on(
            Fields::class,
            Fields::EVENT_REGISTER_FIELD_TYPES,
            function(RegisterComponentTypesEvent $event) {
                $event->types[] = SectionField::class;
                $event->types[] = ProductTypeField::class;
                $event->types[] = TagGroupField::class;
                $event->types[] = EntryTypeField::class;
            }
        );

        Craft::info(
            Craft::t(
                'section-and-product-type',
                '{name} plugin loaded',
                ['name' => $this->name]
            ),
            __METHOD__
        );
    }

    /**
     * Return the view mode options offered by every field type of this plugin.
     *
     * @return array
     */
    public static function viewModeOptions(): array
    {
        return [
            [
                'label' => Craft::t('section-and-product-type', 'List'),
                'value' => self::VIEW_MODE_LIST,
            ],
            [
                'label' => Craft::t('section-and-product-type', 'Dropdown'),
                'value' => self::VIEW_MODE_DROPDOWN,
            ],
        ];
    }

    /**
     * Return the template value options offered by every field type of this plugin.
     *
     * @return array
     */
    public static function valueTypeOptions(): array
    {
        return [
            [
                'label' => Craft::t('section-and-product-type', 'IDs'),
                'value' => self::VALUE_TYPE_IDS,
            ],
            [
                'label' => Craft::t('section-and-product-type', 'Objects'),
                'value' => self::VALUE_TYPE_OBJECTS,
            ],
        ];
    }
}
