<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\feature;

use CraftCms\Cms\Database\Factories\SectionFactory;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\FieldContext;
use CraftCms\Cms\Plugin\Testing\PluginTestCase;
use CraftCms\Cms\Section\Models\Section;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use wmd\sectionandproducttype\fields\EntryTypeField;
use wmd\sectionandproducttype\fields\ProductTypeField;
use wmd\sectionandproducttype\fields\SectionField;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\SectionAndProductType;

/**
 * References that name nothing on this install: product types while Commerce is off (the test install has no
 * Commerce, so no product type can be told), and a section deleted after it was selected.
 */
final class MissingItemsTest extends PluginTestCase
{
    use InspectsFields;

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
        $saved = $this->saveFromTheSettingsForm($this->productTypeField());

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
     * Nothing can be looked up while Commerce is off, but what is saved still has to be shaped like a
     * reference: a UID, or an ID from before 2.3.0.
     */
    public function test_an_allow_list_entry_that_is_no_reference_is_rejected_while_commerce_is_off(): void
    {
        foreach (['<img src=x onerror=1>', 'not-a-uid', ['nested' => 'array']] as $junk) {
            $field = new ProductTypeField(['name' => 'Product types', 'handle' => 'productTypes', 'allowProductTypes' => [self::PRODUCT_TYPE_UID, $junk]]);

            self::assertFalse(Fields::saveField($field));
            self::assertSame(['Invalid product type selected.'], $field->errors()->get('allowProductTypes'));
        }
    }

    /**
     * Settings from project config are not validated, so the form has to open whatever they hold.
     */
    public function test_the_settings_form_opens_with_a_list_entry_that_is_no_reference(): void
    {
        $field = new ProductTypeField(['handle' => 'productTypes', 'allowProductTypes' => [['nested' => 'array'], self::PRODUCT_TYPE_UID]]);

        self::assertSame([self::PRODUCT_TYPE_UID], $this->settingsFormValues($field)['allowProductTypes']);
        self::assertSame([self::PRODUCT_TYPE_UID], $field->getSettings()['allowProductTypes']);
    }

    public function test_the_settings_form_says_why_the_product_type_lists_show_references(): void
    {
        $warnings = $this->settingsFormWarnings($this->productTypeField());

        self::assertStringContainsString('Craft Commerce is not installed or enabled', (string) $warnings['allowProductTypes']);
        self::assertStringContainsString('Craft Commerce is not installed or enabled', (string) $warnings['excludedProductTypes']);

        // A list with nothing stored has nothing to explain, and neither has a list that shows names.
        self::assertNull($this->settingsFormWarnings(new ProductTypeField(['handle' => 'productTypes']))['allowProductTypes']);
        self::assertNull($this->settingsFormWarnings(new SectionField(['handle' => 'source', 'allowedSections' => [$this->news->uid]]))['allowedSections']);
    }

    // Field settings of a field whose items can be told
    // =========================================================================

    /**
     * A reference that names no section is a section deleted since: the form leaves it out, so saving the
     * form drops it, and a save that still carries it is rejected. As before.
     */
    public function test_a_section_that_is_gone_is_left_out_of_the_settings_form_and_dropped_by_its_save(): void
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

        self::assertFalse(Fields::saveField(clone $field));

        $saved = $this->saveFromTheSettingsForm($field);

        self::assertSame([$this->news->uid], $saved->allowedSections);
        self::assertSame([], $saved->excludedSections);
    }

    // Field values that name nothing
    // =========================================================================

    #[DataProvider('templateValues')]
    public function test_an_entry_holding_a_deleted_section_does_not_save_until_the_author_removes_it(string $valueType): void
    {
        $field = new SectionField(['name' => 'Sources', 'handle' => 'sources', 'selectAll' => true, 'multiple' => true, 'valueType' => $valueType]);
        $entry = $this->entryHolding($field, [self::GONE, $this->news->id]);

        self::assertFalse(Elements::saveElement($entry));
        self::assertSame(['Sources is invalid.'], $entry->errors()->get('sources'));
        self::assertSame([self::GONE, $this->news->id], $this->storedValue($entry));

        // The author unticks "Missing section" and saves: the form posts what is left.
        $entry->setFieldValue('sources', [(string) $this->news->id]);

        self::assertTrue(Elements::saveElement($entry), implode(' ', $entry->errors()->all()));
        self::assertSame([$this->news->id], $this->storedValue($entry));
    }

    /**
     * Not dropped behind the author's back, and not moved either: a save writes the IDs back in the order
     * they were selected.
     */
    #[DataProvider('templateValues')]
    public function test_an_id_that_names_nothing_keeps_its_place_in_the_stored_value(string $valueType): void
    {
        $field = new SectionField(['name' => 'Sources', 'handle' => 'sources', 'selectAll' => true, 'multiple' => true, 'valueType' => $valueType]);
        $value = $field->normalizeValue([self::GONE, $this->news->id], null);

        self::assertSame([self::GONE, $this->news->id], $field->serializeValue($value, null));
        self::assertFalse($field->isValueEmpty($field->normalizeValue(self::GONE, null), new Entry));

        // Through a save that skips validation, as a draft's is.
        $entry = $this->entryHolding($field, [self::GONE, $this->news->id]);
        $entry->setFieldValue('sources', $entry->getFieldValue('sources'));

        self::assertTrue(Elements::saveElement($entry, false));
        self::assertSame([self::GONE, $this->news->id], $this->storedValue($entry));
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
     * A single select reads a list left over from when Multiple Select was on as its first ID, with either
     * Template Value. What follows the first ID is not selected, so it is not validated or shown.
     */
    #[DataProvider('templateValues')]
    public function test_a_single_select_holding_an_old_list_goes_by_its_first_id(string $valueType): void
    {
        $field = new SectionField(['name' => 'Source', 'handle' => 'source', 'selectAll' => true, 'valueType' => $valueType]);

        $good = $this->entryHolding($field, [$this->news->id, self::GONE]);
        $control = $field->uiControl(new FieldContext('source', $good->getFieldValue('source'), $good));

        self::assertSame((string) $this->news->id, $control->getValue());
        self::assertNotContains((string) self::GONE, array_column($control->props()['options'], 'value'));
        self::assertTrue(Elements::saveElement($good), implode(' ', $good->errors()->all()));

        $bad = $this->entryHolding(new SectionField(['name' => 'Origin', 'handle' => 'origin', 'selectAll' => true, 'valueType' => $valueType]), [self::GONE, $this->news->id]);
        $control = $field->uiControl(new FieldContext('origin', $bad->getFieldValue('origin'), $bad));

        self::assertSame((string) self::GONE, $control->getValue());
        self::assertFalse(Elements::saveElement($bad));
        self::assertSame(['Origin is invalid.'], $bad->errors()->get('origin'));
    }

    /**
     * With Objects, templates get the sections that exist; the ID that names nothing is reported apart.
     */
    public function test_objects_hand_back_what_exists_and_name_what_is_missing(): void
    {
        $field = new SectionField(['handle' => 'sources', 'selectAll' => true, 'multiple' => true, 'valueType' => SectionAndProductType::VALUE_TYPE_OBJECTS]);
        $value = $field->normalizeValue([self::GONE, $this->news->id], null);

        self::assertInstanceOf(SelectedItems::class, $value);
        self::assertSame([$this->news->id], $value->ids());
        self::assertSame([$this->news->id], iterator_to_array($value));
        self::assertSame(['news'], $value->handles());
        self::assertSame([self::GONE], $value->missingIds());
        self::assertSame([self::GONE, $this->news->id], $value->storedIds());
        self::assertCount(1, $value);

        // Built without the stored IDs, as code written before this change does: nothing is missing.
        $plain = new SelectedItems([$this->news]);

        self::assertSame([], $plain->missingIds());
        self::assertSame([$this->news->id], $plain->storedIds());
    }

    #[DataProvider('templateValues')]
    public function test_an_entry_type_that_is_gone_is_rejected_and_shown_too(string $valueType): void
    {
        $field = new EntryTypeField(['name' => 'Types', 'handle' => 'types', 'selectAll' => true, 'multiple' => true, 'valueType' => $valueType]);
        [$rule] = $field->getElementRules(new Entry);
        $value = $field->normalizeValue([self::GONE], null);
        $control = $field->uiControl(new FieldContext('types', $value, new Entry));

        self::assertTrue(Validator::make(['types' => $value], ['types' => [$rule]])->fails());
        self::assertSame('Missing entry type (ID 999999)', array_column($control->props()['options'], 'label', 'value')[(string) self::GONE]);
    }

    // Field values while Commerce is off
    // =========================================================================

    /**
     * No product type can be told while Commerce is off, so a stored one is not known to be gone: an entry
     * holding some still saves, and keeps them.
     */
    #[DataProvider('templateValues')]
    public function test_an_entry_holding_product_types_saves_and_keeps_them_while_commerce_is_off(string $valueType): void
    {
        $field = new ProductTypeField(['name' => 'Product types', 'handle' => 'productTypes', 'selectAll' => true, 'multiple' => true, 'valueType' => $valueType]);
        $entry = $this->entryHolding($field, [3, 5]);

        self::assertTrue(Elements::saveElement($entry), implode(' ', $entry->errors()->all()));

        // And when the author saves the entry with the field as it shows, the form posts both back.
        $control = $field->uiControl(new FieldContext('productTypes', $entry->getFieldValue('productTypes'), $entry));
        $entry->setFieldValue('productTypes', $control->getValue());

        self::assertTrue(Elements::saveElement($entry), implode(' ', $entry->errors()->all()));
        self::assertSame([3, 5], $this->storedValue($entry));
    }

    #[DataProvider('templateValues')]
    public function test_a_single_product_type_is_kept_and_shown_as_its_id_while_commerce_is_off(string $valueType): void
    {
        $field = new ProductTypeField(['name' => 'Product type', 'handle' => 'productType', 'selectAll' => true, 'valueType' => $valueType]);
        $value = $field->normalizeValue(3, null);
        $control = $field->uiControl(new FieldContext('productType', $value, new Entry));

        self::assertSame(3, $field->serializeValue($value, null));
        self::assertSame('3', $control->getValue());
        // Not "Missing": nobody can tell from here whether product type 3 still exists.
        self::assertSame(['' => 'None', '3' => 'ID 3'], array_column($control->props()['options'], 'label', 'value'));
        self::assertStringContainsString('Craft Commerce is not installed or enabled', (string) $field->uiWarning());
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
}
