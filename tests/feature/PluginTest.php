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
        Factory::guessFactoryNamesUsing(static fn(string $model) => 'CraftCms\\Cms\\Database\\Factories\\' . class_basename($model) . 'Factory');

        $this->news = SectionFactory::new()->create(['name' => 'News', 'handle' => 'news']);
        $this->blog = SectionFactory::new()->create(['name' => 'Blog', 'handle' => 'blog']);
        $this->newsCategories = SectionFactory::new()->create(['name' => 'News categories', 'handle' => 'newsCategories']);
    }

    public function testTheFieldTypesAreRegistered(): void
    {
        $types = $this->app->make(FieldTypes::class)->types()->all();

        self::assertContains(SectionField::class, $types);
        self::assertContains(EntryTypeField::class, $types);
        self::assertContains(ProductTypeField::class, $types);
    }

    public function testSelectAllOffersEverySectionMinusTheExcluded(): void
    {
        $field = new SectionField(['handle' => 'source', 'selectAll' => true, 'excludedSections' => [$this->blog->uid]]);

        self::assertSame(
            [$this->news->id => 'News', $this->newsCategories->id => 'News categories'],
            $field->getAllowedItems(),
        );
    }

    public function testHandleTextAndAllowListAddUpAndExcludedAlwaysWins(): void
    {
        $field = new SectionField([
            'handle' => 'source',
            'partOfHandle' => 'news',
            'allowedSections' => [$this->blog->uid],
            'excludedSections' => [$this->newsCategories->uid],
        ]);

        self::assertEqualsCanonicalizing([$this->news->id, $this->blog->id], array_keys($field->getAllowedItems()));
    }

    public function testListsAreSavedAsUidsEvenWhenGivenIds(): void
    {
        $field = new SectionField(['handle' => 'source', 'allowedSections' => [(string)$this->news->id, $this->blog->uid]]);

        self::assertSame([$this->news->uid, $this->blog->uid], $field->getSettings()['allowedSections']);
        self::assertSame([$this->news->id, $this->blog->id], $field->allowedIds());
    }

    public function testSharedSettingsSurviveASave(): void
    {
        $field = new SectionField(['handle' => 'source', 'multiple' => true, 'viewMode' => 'dropdown', 'valueType' => 'objects', 'selectAll' => true, 'partOfHandle' => 'x']);
        $settings = $field->getSettings();

        self::assertTrue($settings['multiple']);
        self::assertTrue($settings['selectAll']);
        self::assertSame('dropdown', $settings['viewMode']);
        self::assertSame('objects', $settings['valueType']);
        self::assertSame('x', $settings['partOfHandle']);
    }

    public function testAnUnknownAllowedSectionFailsValidation(): void
    {
        $field = new SectionField(['name' => 'Source', 'handle' => 'source', 'allowedSections' => ['not-a-uid']]);
        $rules = $field->getRules();

        self::assertTrue(Validator::make(['allowedSections' => ['not-a-uid']], ['allowedSections' => $rules['allowedSections']])->fails());
        self::assertFalse(Validator::make(['allowedSections' => [$this->news->uid]], ['allowedSections' => $rules['allowedSections']])->fails());
    }

    public function testASingleValueIsAnIdAndAMultipleValueIsAList(): void
    {
        $single = new SectionField(['handle' => 'source', 'selectAll' => true]);
        $multiple = new SectionField(['handle' => 'sources', 'selectAll' => true, 'multiple' => true]);

        self::assertSame($this->news->id, $single->normalizeValue((string)$this->news->id, null));
        self::assertSame($this->news->id, $single->normalizeValue([$this->news->id, $this->blog->id], null));
        self::assertNull($single->normalizeValue('', null));
        self::assertSame($this->news->id, $single->serializeValue($this->news->id, null));

        // JSON as fields saved before 3.0 stored it, and the strings a form posts.
        self::assertSame([$this->news->id, $this->blog->id], $multiple->normalizeValue('["' . $this->news->id . '",' . $this->blog->id . ']', null));
        self::assertSame([$this->blog->id], $multiple->normalizeValue([(string)$this->blog->id, ''], null));
        self::assertSame([], $multiple->normalizeValue(null, null));
        self::assertSame([$this->blog->id], $multiple->serializeValue([$this->blog->id], null));
    }

    public function testObjectsHandBackTheSectionsAndStillActLikeIds(): void
    {
        $field = new SectionField(['handle' => 'sources', 'selectAll' => true, 'multiple' => true, 'valueType' => SectionAndProductType::VALUE_TYPE_OBJECTS]);
        $value = $field->normalizeValue([$this->news->id, $this->blog->id], null);

        self::assertInstanceOf(SelectedItems::class, $value);
        self::assertSame(['news', 'blog'], $value->handles());
        self::assertSame([$this->news->id, $this->blog->id], iterator_to_array($value));
        self::assertSame([$this->news->id, $this->blog->id], $field->serializeValue($value, null));
    }

    public function testTheListInputIsRadiosWithNoneAndKeepsAnExcludedSelection(): void
    {
        $field = new SectionField(['handle' => 'source', 'allowedSections' => [$this->news->uid]]);
        $control = $field->uiControl(new FieldContext('source', $this->blog->id, new Entry()));

        self::assertInstanceOf(Choice::class, $control);
        $props = $control->props();
        self::assertSame(['', (string)$this->news->id, (string)$this->blog->id], array_column($props['options'], 'value'));
        self::assertSame((string)$this->blog->id, $control->getValue());
    }

    public function testTheMultipleDropdownIsASearchableCombobox(): void
    {
        $field = new SectionField(['handle' => 'sources', 'selectAll' => true, 'multiple' => true, 'viewMode' => 'dropdown']);
        $control = $field->uiControl(new FieldContext('sources', [$this->news->id], new Entry()));

        self::assertInstanceOf(Combobox::class, $control);
        self::assertSame([(string)$this->news->id], $control->getValue());
    }

    public function testAFieldWithNothingAllowedSaysSo(): void
    {
        self::assertNotNull((new SectionField(['handle' => 'source']))->uiWarning());
        self::assertNull((new SectionField(['handle' => 'source', 'selectAll' => true]))->uiWarning());
    }

    public function testTheSettingsFormRenders(): void
    {
        $field = new SectionField(['handle' => 'source', 'allowedSections' => [$this->news->uid]]);

        self::assertNotEmpty($field->settingsUi()->nodes());
    }

    public function testTheInputSchemaListsTheAllowedIds(): void
    {
        $field = new SectionField(['handle' => 'sources', 'multiple' => true, 'partOfHandle' => 'news']);
        $schema = $field->getInputSchema();

        self::assertSame('array', $schema['type']);
        self::assertSame([$this->news->id, $this->newsCategories->id], $schema['items']['enum']);
        self::assertStringContainsString('News (news)', $schema['items']['description']);
    }

    public function testAStoredIdThatNoLongerExistsFailsElementValidation(): void
    {
        $field = new SectionField(['name' => 'Source', 'handle' => 'source', 'selectAll' => true]);
        [$rule] = $field->getElementRules(new Entry());

        self::assertTrue(Validator::make(['source' => 999999], ['source' => [$rule]])->fails());
        self::assertFalse(Validator::make(['source' => $this->blog->id], ['source' => [$rule]])->fails());
    }

    public function testTheProductTypeFieldOffersNothingWithoutCommerce(): void
    {
        $field = new ProductTypeField(['handle' => 'productTypes', 'selectAll' => true]);

        self::assertSame([], $field->getAllowedItems());
        self::assertNull($field->normalizeValue('', null));
    }

    public function testTheEntryTypeFieldListsEntryTypes(): void
    {
        $field = new EntryTypeField(['handle' => 'types', 'selectAll' => true]);

        self::assertIsArray($field->getAllowedItems());
    }
}
