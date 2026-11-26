<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\feature;

use CraftCms\Cms\Database\Factories\SectionFactory;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Field\FieldContext;
use CraftCms\Cms\Plugin\Testing\PluginTestCase;
use CraftCms\Cms\Section\Models\Section;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Nodes\Field as UiField;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use wmd\sectionandproducttype\fields\BaseTypeField;
use wmd\sectionandproducttype\fields\ProductTypeField;
use wmd\sectionandproducttype\fields\SectionField;
use wmd\sectionandproducttype\SectionAndProductType;

/**
 * References that name nothing on this install: product types while Commerce is off (the test install has no
 * Commerce, so no product type resolves), and a section deleted after it was selected.
 */
final class MissingItemsTest extends PluginTestCase
{
    private const string PRODUCT_TYPE_UID = 'eaa2390a-6a37-4b51-bdf8-b3f7fe4072a0';

    private const string OTHER_PRODUCT_TYPE_UID = '0b6f3f0e-52a3-4c41-9d0e-6f1c2d3a4b5c';

    /** An ID as fields saved before 2.3.0 hold them. */
    private const string LEGACY_PRODUCT_TYPE_ID = '7';

    /** An ID no section has. */
    private const int GONE = 999999;

    private Section $news;

    protected function setUp(): void
    {
        parent::setUp();

        // Craft's model factories live in one namespace, not next to each model.
        Factory::guessFactoryNamesUsing(static fn (string $model) => 'CraftCms\\Cms\\Database\\Factories\\'.class_basename($model).'Factory');

        $this->news = SectionFactory::new()->create(['name' => 'News', 'handle' => 'news']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function templateValues(): array
    {
        return [
            'IDs' => [SectionAndProductType::VALUE_TYPE_IDS],
            'Objects' => [SectionAndProductType::VALUE_TYPE_OBJECTS],
        ];
    }

    // Field settings while Commerce is off
    // =========================================================================

    public function test_the_settings_form_shows_the_stored_product_type_lists_while_commerce_is_off(): void
    {
        $field = $this->productTypeField();
        $form = $this->settingsFormValues($field);

        self::assertSame([self::PRODUCT_TYPE_UID, self::LEGACY_PRODUCT_TYPE_ID], $form['allowProductTypes']);
        self::assertSame([self::OTHER_PRODUCT_TYPE_UID], $form['excludedProductTypes']);
    }

    /**
     * The lists require an option match, so every reference kept in them has an option of its own.
     */
    public function test_the_stored_product_type_references_are_options_of_their_lists_while_commerce_is_off(): void
    {
        $field = $this->productTypeField();

        foreach ($this->settingsFormControls($field) as $name => $control) {
            if (! in_array($name, ['allowProductTypes', 'excludedProductTypes'], true)) {
                continue;
            }

            self::assertEqualsCanonicalizing(
                [self::PRODUCT_TYPE_UID, self::LEGACY_PRODUCT_TYPE_ID, self::OTHER_PRODUCT_TYPE_UID],
                array_column($control->props()['options'], 'value'),
                $name,
            );
        }
    }

    public function test_saving_the_settings_form_keeps_the_product_type_lists_while_commerce_is_off(): void
    {
        $field = $this->productTypeField();
        // Stored as a site that had Commerce left it, so without the validation the next test is about.
        self::assertTrue(Fields::saveField($field, false));

        $saved = $this->saveFromTheSettingsForm($this->reload($field));

        self::assertSame([self::PRODUCT_TYPE_UID, self::LEGACY_PRODUCT_TYPE_ID], $saved->allowProductTypes);
        self::assertSame([self::OTHER_PRODUCT_TYPE_UID], $saved->excludedProductTypes);
    }

    public function test_a_product_type_field_with_stored_lists_can_be_saved_while_commerce_is_off(): void
    {
        $field = $this->productTypeField();

        self::assertTrue(Fields::saveField($field), implode(' ', $field->errors()->all()));
        self::assertSame(
            [self::PRODUCT_TYPE_UID, self::LEGACY_PRODUCT_TYPE_ID],
            $this->reload($field)->allowProductTypes,
        );
    }

    /**
     * Where the install does have items, a reference that names none of them is an item deleted since: the
     * form leaves it out and the save drops it, as before.
     */
    public function test_the_settings_form_of_a_section_field_leaves_out_a_section_that_is_gone(): void
    {
        $field = new SectionField([
            'name' => 'Source',
            'handle' => 'source',
            'allowedSections' => [$this->news->uid, self::PRODUCT_TYPE_UID],
            'excludedSections' => [self::OTHER_PRODUCT_TYPE_UID],
        ]);
        $form = $this->settingsFormValues($field);

        self::assertSame([$this->news->uid], $form['allowedSections']);
        self::assertSame([], $form['excludedSections']);
        self::assertFalse(Fields::saveField($field));
        self::assertSame(['Invalid section selected.'], $field->errors()->get('allowedSections'));
    }

    // Field values that name nothing
    // =========================================================================

    #[DataProvider('templateValues')]
    public function test_a_stored_id_that_names_nothing_is_rejected_and_kept(string $valueType): void
    {
        $field = new SectionField(['name' => 'Sources', 'handle' => 'sources', 'selectAll' => true, 'multiple' => true, 'valueType' => $valueType]);
        [$rule] = $field->getElementRules(new Entry);
        $value = $field->normalizeValue([$this->news->id, self::GONE], null);

        self::assertTrue(Validator::make(['sources' => $value], ['sources' => [$rule]])->fails());
        // Not dropped behind the author's back either: it stays until they remove it.
        self::assertSame([$this->news->id, self::GONE], $field->serializeValue($value, null));
        self::assertFalse($field->isValueEmpty($field->normalizeValue(self::GONE, null), new Entry));
    }

    #[DataProvider('templateValues')]
    public function test_the_input_shows_a_stored_id_that_names_nothing_so_it_can_be_removed(string $valueType): void
    {
        $field = new SectionField(['handle' => 'sources', 'selectAll' => true, 'multiple' => true, 'valueType' => $valueType]);
        $value = $field->normalizeValue([$this->news->id, self::GONE], null);
        $control = $field->uiControl(new FieldContext('sources', $value, new Entry));

        self::assertSame(
            [(string) $this->news->id => 'News', (string) self::GONE => 'Missing section (ID 999999)'],
            array_column($control->props()['options'], 'label', 'value'),
        );
        self::assertSame([(string) $this->news->id, (string) self::GONE], $control->getValue());
    }

    #[DataProvider('templateValues')]
    public function test_a_single_select_shows_a_stored_id_that_names_nothing_next_to_none(string $valueType): void
    {
        $field = new SectionField(['handle' => 'source', 'selectAll' => true, 'valueType' => $valueType]);
        $control = $field->uiControl(new FieldContext('source', $field->normalizeValue(self::GONE, null), new Entry));

        self::assertSame(
            ['', (string) $this->news->id, (string) self::GONE],
            array_column($control->props()['options'], 'value'),
        );
        self::assertSame((string) self::GONE, $control->getValue());
    }

    /**
     * With Objects, templates get the sections that exist; the ID that names nothing is reported apart.
     */
    public function test_objects_hand_back_what_exists_and_name_what_is_missing(): void
    {
        $field = new SectionField(['handle' => 'sources', 'selectAll' => true, 'multiple' => true, 'valueType' => SectionAndProductType::VALUE_TYPE_OBJECTS]);
        $value = $field->normalizeValue([self::GONE, $this->news->id], null);

        self::assertSame([$this->news->id], $value->ids());
        self::assertSame([$this->news->id], iterator_to_array($value));
        self::assertSame(['news'], $value->handles());
        self::assertSame([self::GONE], $value->missingIds());
        self::assertCount(1, $value);
    }

    // Field values while Commerce is off
    // =========================================================================

    /**
     * No product type resolves while Commerce is off, so none can be told from a deleted one: an entry
     * holding one still saves, and keeps it.
     */
    #[DataProvider('templateValues')]
    public function test_a_stored_product_type_survives_an_entry_save_while_commerce_is_off(string $valueType): void
    {
        $field = new ProductTypeField(['name' => 'Product type', 'handle' => 'productType', 'selectAll' => true, 'valueType' => $valueType]);
        [$rule] = $field->getElementRules(new Entry);
        $value = $field->normalizeValue(3, null);

        self::assertFalse(Validator::make(['productType' => $value], ['productType' => [$rule]])->fails());
        self::assertSame(3, $field->serializeValue($value, null));
        self::assertSame(
            '3',
            $field->uiControl(new FieldContext('productType', $value, new Entry))->getValue(),
        );
    }

    // Helpers
    // =========================================================================

    private function productTypeField(): ProductTypeField
    {
        return new ProductTypeField([
            'name' => 'Product types',
            'handle' => 'productTypes',
            'multiple' => true,
            'allowProductTypes' => [self::PRODUCT_TYPE_UID, self::LEGACY_PRODUCT_TYPE_ID],
            'excludedProductTypes' => [self::OTHER_PRODUCT_TYPE_UID],
        ]);
    }

    /**
     * The controls of a field's settings form, keyed by the setting they post.
     *
     * @return array<string, Control>
     */
    private function settingsFormControls(BaseTypeField $field): array
    {
        $controls = [];

        foreach ($field->settingsUi()->nodes() as $node) {
            if ($node instanceof UiField && $node->getControl()) {
                $controls[(string) $node->getControl()->path()] = $node->getControl();
            }
        }

        return $controls;
    }

    /**
     * What a field's settings form holds when it opens, and so posts back when saved untouched.
     *
     * @return array<string, mixed>
     */
    private function settingsFormValues(BaseTypeField $field): array
    {
        return array_map(static fn ($control) => $control->getValue(), $this->settingsFormControls($field));
    }

    /**
     * Save a stored field the way the control panel does (FieldsController::store()): a new field of the
     * same type and UID, its settings replaced by what the form posts.
     */
    private function saveFromTheSettingsForm(BaseTypeField $field): FieldInterface
    {
        $posted = Fields::createField([
            'type' => $field::class,
            'id' => $field->id,
            'uid' => $field->uid,
            'name' => $field->name,
            'handle' => $field->handle,
            'settings' => array_replace($field->getSettings(), $this->settingsFormValues($field)),
        ]);

        self::assertTrue(Fields::saveField($posted), implode(' ', $posted->errors()->all()));

        return $this->reload($posted);
    }

    /**
     * The field as the database has it.
     */
    private function reload(FieldInterface $field): FieldInterface
    {
        Fields::refreshFields();

        $reloaded = Fields::getFieldById($field->id);
        self::assertInstanceOf($field::class, $reloaded);

        return $reloaded;
    }
}
