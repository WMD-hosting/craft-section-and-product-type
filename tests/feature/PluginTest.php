<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\feature;

use CraftCms\Cms\Database\Factories\SectionFactory;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\FieldContext;
use CraftCms\Cms\Field\FieldTypes;
use CraftCms\Cms\Plugin\Testing\PluginTestCase;
use CraftCms\Cms\Section\Models\Section;
use CraftCms\Cms\Ui\Controls\Choice;
use CraftCms\Cms\Ui\Controls\Combobox;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Validator;
use wmd\sectionandproducttype\fields\EntryTypeField;
use wmd\sectionandproducttype\fields\ProductTypeField;
use wmd\sectionandproducttype\fields\SectionField;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\SectionAndProductType;

/**
 * The plugin inside a real Craft 6 install (Testbench, SQLite in memory).
 */
final class PluginTest extends PluginTestCase
{
    private Section $news;

    private Section $blog;

    private Section $newsCategories;

    protected function setUp(): void
    {
        parent::setUp();

        // Craft's model factories live in one namespace, not next to each model.
        Factory::guessFactoryNamesUsing(static fn (string $model) => 'CraftCms\\Cms\\Database\\Factories\\'.class_basename($model).'Factory');

        $this->news = SectionFactory::new()->create(['name' => 'News', 'handle' => 'news']);
        $this->blog = SectionFactory::new()->create(['name' => 'Blog', 'handle' => 'blog']);
        $this->newsCategories = SectionFactory::new()->create(['name' => 'News categories', 'handle' => 'newsCategories']);
    }

    public function test_the_field_types_are_registered(): void
    {
        $types = $this->app->make(FieldTypes::class)->types()->all();

        self::assertContains(SectionField::class, $types);
        self::assertContains(EntryTypeField::class, $types);
        self::assertContains(ProductTypeField::class, $types);
    }

    public function test_select_all_offers_every_section_minus_the_excluded(): void
    {
        $field = new SectionField(['handle' => 'source', 'selectAll' => true, 'excludedSections' => [$this->blog->uid]]);

        self::assertSame(
            [$this->news->id => 'News', $this->newsCategories->id => 'News categories'],
            $field->getAllowedItems(),
        );
    }

    public function test_handle_text_and_allow_list_add_up_and_excluded_always_wins(): void
    {
        $field = new SectionField([
            'handle' => 'source',
            'partOfHandle' => 'news',
            'allowedSections' => [$this->blog->uid],
            'excludedSections' => [$this->newsCategories->uid],
        ]);

        self::assertEqualsCanonicalizing([$this->news->id, $this->blog->id], array_keys($field->getAllowedItems()));
    }

    public function test_lists_are_saved_as_uids_even_when_given_ids(): void
    {
        $field = new SectionField(['handle' => 'source', 'allowedSections' => [(string) $this->news->id, $this->blog->uid]]);

        self::assertSame([$this->news->uid, $this->blog->uid], $field->getSettings()['allowedSections']);
        self::assertSame([$this->news->id, $this->blog->id], $field->allowedIds());
    }

    public function test_shared_settings_survive_a_save(): void
    {
        $field = new SectionField(['handle' => 'source', 'multiple' => true, 'viewMode' => 'dropdown', 'valueType' => 'objects', 'selectAll' => true, 'partOfHandle' => 'x']);
        $settings = $field->getSettings();

        self::assertTrue($settings['multiple']);
        self::assertTrue($settings['selectAll']);
        self::assertSame('dropdown', $settings['viewMode']);
        self::assertSame('objects', $settings['valueType']);
        self::assertSame('x', $settings['partOfHandle']);
    }

    public function test_an_unknown_allowed_section_fails_validation(): void
    {
        $field = new SectionField(['name' => 'Source', 'handle' => 'source', 'allowedSections' => ['not-a-uid']]);
        $rules = $field->getRules();

        self::assertTrue(Validator::make(['allowedSections' => ['not-a-uid']], ['allowedSections' => $rules['allowedSections']])->fails());
        self::assertFalse(Validator::make(['allowedSections' => [$this->news->uid]], ['allowedSections' => $rules['allowedSections']])->fails());
    }

    public function test_a_single_value_is_an_id_and_a_multiple_value_is_a_list(): void
    {
        $single = new SectionField(['handle' => 'source', 'selectAll' => true]);
        $multiple = new SectionField(['handle' => 'sources', 'selectAll' => true, 'multiple' => true]);

        self::assertSame($this->news->id, $single->normalizeValue((string) $this->news->id, null));
        self::assertSame($this->news->id, $single->normalizeValue([$this->news->id, $this->blog->id], null));
        self::assertNull($single->normalizeValue('', null));
        self::assertSame($this->news->id, $single->serializeValue($this->news->id, null));

        // JSON as fields saved before 3.0 stored it, and the strings a form posts.
        self::assertSame([$this->news->id, $this->blog->id], $multiple->normalizeValue('["'.$this->news->id.'",'.$this->blog->id.']', null));
        self::assertSame([$this->blog->id], $multiple->normalizeValue([(string) $this->blog->id, ''], null));
        self::assertSame([], $multiple->normalizeValue(null, null));
        self::assertSame([$this->blog->id], $multiple->serializeValue([$this->blog->id], null));
    }

    public function test_objects_hand_back_the_sections_and_still_act_like_ids(): void
    {
        $field = new SectionField(['handle' => 'sources', 'selectAll' => true, 'multiple' => true, 'valueType' => SectionAndProductType::VALUE_TYPE_OBJECTS]);
        $value = $field->normalizeValue([$this->news->id, $this->blog->id], null);

        self::assertInstanceOf(SelectedItems::class, $value);
        self::assertSame(['news', 'blog'], $value->handles());
        self::assertSame([$this->news->id, $this->blog->id], iterator_to_array($value));
        self::assertSame([$this->news->id, $this->blog->id], $field->serializeValue($value, null));
    }

    public function test_the_list_input_is_radios_with_none_and_keeps_an_excluded_selection(): void
    {
        $field = new SectionField(['handle' => 'source', 'allowedSections' => [$this->news->uid]]);
        $control = $field->uiControl(new FieldContext('source', $this->blog->id, new Entry));

        self::assertInstanceOf(Choice::class, $control);
        $props = $control->props();
        self::assertSame(['', (string) $this->news->id, (string) $this->blog->id], array_column($props['options'], 'value'));
        self::assertSame((string) $this->blog->id, $control->getValue());
    }

    public function test_the_multiple_dropdown_is_a_searchable_combobox(): void
    {
        $field = new SectionField(['handle' => 'sources', 'selectAll' => true, 'multiple' => true, 'viewMode' => 'dropdown']);
        $control = $field->uiControl(new FieldContext('sources', [$this->news->id], new Entry));

        self::assertInstanceOf(Combobox::class, $control);
        self::assertSame([(string) $this->news->id], $control->getValue());
    }

    public function test_a_field_with_nothing_allowed_says_so(): void
    {
        self::assertNotNull((new SectionField(['handle' => 'source']))->uiWarning());
        self::assertNull((new SectionField(['handle' => 'source', 'selectAll' => true]))->uiWarning());
    }

    public function test_the_settings_form_renders(): void
    {
        $field = new SectionField(['handle' => 'source', 'allowedSections' => [$this->news->uid]]);

        self::assertNotEmpty($field->settingsUi()->nodes());
    }

    public function test_the_input_schema_lists_the_allowed_ids(): void
    {
        $field = new SectionField(['handle' => 'sources', 'multiple' => true, 'partOfHandle' => 'news']);
        $schema = $field->getInputSchema();

        self::assertSame('array', $schema['type']);
        self::assertSame([$this->news->id, $this->newsCategories->id], $schema['items']['enum']);
        self::assertStringContainsString('News (news)', $schema['items']['description']);
    }

    public function test_a_stored_id_that_no_longer_exists_fails_element_validation(): void
    {
        $field = new SectionField(['name' => 'Source', 'handle' => 'source', 'selectAll' => true]);
        [$rule] = $field->getElementRules(new Entry);

        self::assertTrue(Validator::make(['source' => 999999], ['source' => [$rule]])->fails());
        self::assertFalse(Validator::make(['source' => $this->blog->id], ['source' => [$rule]])->fails());
    }

    public function test_the_product_type_field_offers_nothing_without_commerce(): void
    {
        $field = new ProductTypeField(['handle' => 'productTypes', 'selectAll' => true]);

        self::assertSame([], $field->getAllowedItems());
        self::assertNull($field->normalizeValue('', null));
    }

    public function test_the_entry_type_field_lists_entry_types(): void
    {
        $field = new EntryTypeField(['handle' => 'types', 'selectAll' => true]);

        self::assertIsArray($field->getAllowedItems());
    }
}
