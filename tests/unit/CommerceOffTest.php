<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\unit;

use craft\base\ElementInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\SectionAndProductType;
use wmd\sectionandproducttype\tests\support\ProductTypeFieldProbe;
use wmd\sectionandproducttype\tests\support\ThingField;

/**
 * A Product Type field on an install where Commerce is not installed or is disabled. No product
 * type can be listed there, so a reference that is still good looks exactly like a deleted one:
 * the field has to keep what it holds instead of resolving it away.
 *
 * The tests run without a Craft application, so Commerce is never loaded.
 */
final class CommerceOffTest extends TestCase
{
    private const PRODUCT_TYPE_UID = 'eaa2390a-6a37-4b51-bdf8-b3f7fe4072a0';
    private const OTHER_PRODUCT_TYPE_UID = '0b6f3f0e-52a3-4c41-9d0e-6f1c2d3a4b5c';

    /** An ID as fields saved before 2.3.0 hold them. */
    private const LEGACY_PRODUCT_TYPE_ID = '7';

    private const NOT_LISTED = 'Craft Commerce is not installed or enabled';

    // Field settings
    // =========================================================================

    public function testTheSettingsFormShowsTheStoredLists(): void
    {
        $form = $this->fieldWithLists()->settingsForm();

        self::assertSame([self::PRODUCT_TYPE_UID, self::LEGACY_PRODUCT_TYPE_ID], $form['allowedValues']);
        self::assertSame([self::OTHER_PRODUCT_TYPE_UID], $form['excludedValues']);
    }

    /**
     * A checkbox group posts the options it ticks, so every reference the lists keep needs one.
     */
    public function testEveryStoredReferenceIsAnOptionOfTheLists(): void
    {
        self::assertEqualsCanonicalizing(
            [self::PRODUCT_TYPE_UID, self::LEGACY_PRODUCT_TYPE_ID, self::OTHER_PRODUCT_TYPE_UID],
            array_column($this->fieldWithLists()->settingsForm()['options'], 'value'),
        );
    }

    public function testSavingTheSettingsFormUntouchedKeepsTheLists(): void
    {
        $posted = $this->fieldWithLists()->postedLists();
        $saved = new ProductTypeFieldProbe([
            'handle' => 'productTypes',
            'allowProductTypes' => $posted['allowed'],
            'excludedProductTypes' => $posted['excluded'],
        ]);

        self::assertTrue($saved->validate(['allowProductTypes', 'excludedProductTypes']), implode(' ', $saved->getErrorSummary(true)));
        self::assertSame([self::PRODUCT_TYPE_UID, self::LEGACY_PRODUCT_TYPE_ID], $saved->getSettings()['allowProductTypes']);
        self::assertSame([self::OTHER_PRODUCT_TYPE_UID], $saved->getSettings()['excludedProductTypes']);
    }

    /**
     * With Select All on, the form of an install that lists its items ticks the whole Allowed
     * list. Here there is nothing to tick but what is stored, and that is all it may post.
     */
    public function testWithSelectAllTheFormStillPostsTheListsAsStored(): void
    {
        $field = $this->fieldWithLists(['selectAll' => true]);

        self::assertSame(
            ['allowed' => [self::PRODUCT_TYPE_UID, self::LEGACY_PRODUCT_TYPE_ID], 'excluded' => [self::OTHER_PRODUCT_TYPE_UID]],
            $field->postedLists(),
        );
    }

    /**
     * Saving the field from code, as a migration or a plugin does.
     */
    public function testAFieldWithStoredListsIsValid(): void
    {
        $field = $this->fieldWithLists();

        self::assertTrue($field->validate(['allowProductTypes']), implode(' ', $field->getErrors('allowProductTypes')));
    }

    /**
     * Nothing can be looked up, but what is saved still has to be shaped like a reference:
     * a UID, or an ID from before 2.3.0.
     */
    #[DataProvider('junk')]
    public function testAnAllowListEntryThatIsNoReferenceIsRejected(mixed $junk): void
    {
        $field = new ProductTypeFieldProbe(['handle' => 'productTypes', 'allowProductTypes' => [self::PRODUCT_TYPE_UID, $junk]]);

        self::assertFalse($field->validate(['allowProductTypes']));
        self::assertSame(['Invalid product type selected.'], $field->getErrors('allowProductTypes'));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function junk(): array
    {
        return [
            'markup' => ['<img src=x onerror=1>'],
            'a word' => ['not-a-uid'],
            'a UID with a line break' => [self::PRODUCT_TYPE_UID . "\n"],
            'a nested list' => [['nested' => 'array']],
        ];
    }

    /**
     * Settings read from project config are not validated, so the form has to open, and the
     * field has to save, whatever they hold.
     */
    public function testAListEntryThatCannotBeAReferenceIsSkipped(): void
    {
        $field = new ProductTypeFieldProbe(['handle' => 'productTypes', 'allowProductTypes' => [['nested' => 'array'], self::PRODUCT_TYPE_UID]]);

        self::assertSame([self::PRODUCT_TYPE_UID], $field->getSettings()['allowProductTypes']);
        self::assertSame([self::PRODUCT_TYPE_UID], $field->settingsForm()['allowedValues']);
    }

    public function testTheListsSayWhyTheyShowReferences(): void
    {
        $form = $this->fieldWithLists()->settingsForm();

        self::assertStringContainsString(self::NOT_LISTED, (string)($form['allowedWarning'] ?? null));
        self::assertStringContainsString(self::NOT_LISTED, (string)($form['excludedWarning'] ?? null));

        // A list with nothing stored has nothing to explain, and neither has a list that shows names.
        $empty = (new ProductTypeFieldProbe(['handle' => 'productTypes']))->settingsForm();
        $named = ThingField::over([14 => ['aaaaaaaa-0000-4000-8000-000000000014', 'News', 'news']], ['allowedThings' => ['14']])->settingsForm();

        self::assertNull($empty['allowedWarning'] ?? null);
        self::assertNull($empty['excludedWarning'] ?? null);
        self::assertNull($named['allowedWarning'] ?? null);
    }

    // Field values
    // =========================================================================

    /**
     * With Objects the value holds the product types themselves, and none can be loaded. The
     * IDs must come through all the same, or the next save of the entry writes the field empty.
     */
    public function testWithObjectsTheStoredProductTypesAreSavedAgain(): void
    {
        $field = $this->fieldWithValues(SectionAndProductType::VALUE_TYPE_OBJECTS, ['multiple' => true]);
        $value = $field->normalizeValue('[3,5]');

        self::assertInstanceOf(SelectedItems::class, $value);
        self::assertSame('[3,5]', $field->serializeValue($value));
        self::assertSame('[3,5]', $field->serializeValue($field->normalizeValue($value)));

        // Templates still get only what exists, which is nothing while Commerce is off.
        self::assertSame([], $value->ids());
        self::assertSame([], $value->all());
        self::assertTrue($value->isEmpty());
        self::assertSame([3, 5], $value->storedIds());
    }

    public function testWithObjectsASingleStoredProductTypeIsSavedAgain(): void
    {
        $field = $this->fieldWithValues(SectionAndProductType::VALUE_TYPE_OBJECTS);

        self::assertSame('3', $field->serializeValue($field->normalizeValue('3')));
    }

    public function testWithIdsTheStoredProductTypesAreSavedAgain(): void
    {
        $field = $this->fieldWithValues(SectionAndProductType::VALUE_TYPE_IDS, ['multiple' => true]);
        $value = $field->normalizeValue('[3,5]');

        self::assertSame([3, 5], $value);
        self::assertSame('[3,5]', $field->serializeValue($value));
    }

    /**
     * A required field that holds product types is not empty just because they cannot be listed.
     */
    #[DataProvider('templateValues')]
    public function testAFieldHoldingProductTypesIsNotEmpty(string $valueType): void
    {
        $field = $this->fieldWithValues($valueType, ['multiple' => true]);
        $element = self::createStub(ElementInterface::class);

        self::assertFalse($field->isValueEmpty($field->normalizeValue('[3,5]'), $element));
        self::assertTrue($field->isValueEmpty($field->normalizeValue('[]'), $element));
    }

    // Input
    // =========================================================================

    /**
     * The input shows what the field holds, each as its ID, ticked: an author sees it, and a
     * form that posts the field posts the same IDs back.
     */
    #[DataProvider('templateValues')]
    public function testTheInputShowsEachStoredProductTypeAsItsId(string $valueType): void
    {
        $field = $this->fieldWithValues($valueType, ['multiple' => true]);
        $input = $field->input($field->normalizeValue('[3,5]'));

        self::assertSame([3 => 'ID 3', 5 => 'ID 5'], $input['options']);
        self::assertSame([3, 5], $input['value']);
        self::assertStringContainsString(self::NOT_LISTED, (string)($input['warning'] ?? null));
        self::assertNull($input['notice']);
    }

    #[DataProvider('templateValues')]
    public function testASingleSelectShowsItsStoredProductTypeNextToNone(string $valueType): void
    {
        $field = $this->fieldWithValues($valueType, ['viewMode' => SectionAndProductType::VIEW_MODE_DROPDOWN]);
        $input = $field->input($field->normalizeValue('3'));

        // Not "Missing": nobody can tell from here whether product type 3 still exists.
        self::assertSame(['' => 'None', 3 => 'ID 3'], $input['options']);
        self::assertSame(3, $input['value']);
    }

    /**
     * Before, such a field said "No product types are allowed for this field yet", which is not
     * what is wrong, and showed nothing of what it holds.
     */
    public function testAFieldWithAnAllowListDoesNotClaimNothingIsAllowed(): void
    {
        $field = $this->fieldWithLists();
        $input = $field->input($field->normalizeValue('[3]'));

        self::assertNull($input['notice']);
        self::assertStringContainsString(self::NOT_LISTED, (string)($input['warning'] ?? null));
        self::assertSame([3 => 'ID 3'], $input['options']);
        self::assertSame([3], $input['value']);
    }

    public function testAFieldHoldingNothingSaysWhyAndShowsNoControl(): void
    {
        $input = $this->fieldWithValues(SectionAndProductType::VALUE_TYPE_IDS)->input(null);

        self::assertStringContainsString(self::NOT_LISTED, (string)($input['warning'] ?? null));
        self::assertNull($input['options']);
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

    // Helpers
    // =========================================================================

    /**
     * A field as project config from an environment with Commerce leaves it.
     *
     * @param array<string, mixed> $config
     */
    private function fieldWithLists(array $config = []): ProductTypeFieldProbe
    {
        return new ProductTypeFieldProbe($config + [
            'name' => 'Product types',
            'handle' => 'productTypes',
            'multiple' => true,
            'allowProductTypes' => [self::PRODUCT_TYPE_UID, self::LEGACY_PRODUCT_TYPE_ID],
            'excludedProductTypes' => [self::OTHER_PRODUCT_TYPE_UID],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function fieldWithValues(string $valueType, array $config = []): ProductTypeFieldProbe
    {
        return new ProductTypeFieldProbe($config + [
            'name' => 'Product types',
            'handle' => 'productTypes',
            'selectAll' => true,
            'valueType' => $valueType,
        ]);
    }
}
