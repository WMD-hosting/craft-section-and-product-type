<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\unit;

use PHPUnit\Framework\TestCase;
use wmd\sectionandproducttype\tests\support\ThingField;

/**
 * What a field hands its settings form and its input, on an install that can tell which items
 * exist (sections, entry types, tag groups; product types while Commerce is on).
 */
final class TemplateDataTest extends TestCase
{
    private const NEWS = 14;
    private const BLOG = 18;
    private const SHOP = 31;

    private const THINGS = [
        self::NEWS => ['aaaaaaaa-0000-4000-8000-000000000014', 'News', 'news'],
        self::BLOG => ['bbbbbbbb-0000-4000-8000-000000000018', 'Blog', 'blog'],
        self::SHOP => ['cccccccc-0000-4000-8000-000000000031', 'Shop', 'shopPages'],
    ];

    // Settings form
    // =========================================================================

    public function testTheListsOfferEveryItemByUidWithItsHandleForTheFilter(): void
    {
        $form = ThingField::over(self::THINGS)->settingsForm();

        self::assertSame(
            [
                ['label' => 'News', 'value' => self::THINGS[self::NEWS][0], 'data' => ['handle' => 'news']],
                ['label' => 'Blog', 'value' => self::THINGS[self::BLOG][0], 'data' => ['handle' => 'blog']],
                ['label' => 'Shop', 'value' => self::THINGS[self::SHOP][0], 'data' => ['handle' => 'shopPages']],
            ],
            $form['options'],
        );
        self::assertSame('allowedThings', $form['allowedAttribute']);
        self::assertSame('excludedThings', $form['excludedAttribute']);
        self::assertSame(['singular' => 'thing', 'plural' => 'things'], $form['words']);
    }

    /**
     * Lists saved before 2.3.0 hold IDs; the form ticks the same items by UID, so its save writes UIDs.
     */
    public function testTheListsTickWhatIsStoredWhetherAsUidsOrLegacyIds(): void
    {
        $field = ThingField::over(self::THINGS, [
            'allowedThings' => [self::THINGS[self::BLOG][0], '14'],
            'excludedThings' => [31],
        ]);

        self::assertSame(
            ['allowed' => [self::THINGS[self::NEWS][0], self::THINGS[self::BLOG][0]], 'excluded' => [self::THINGS[self::SHOP][0]]],
            $field->postedLists(),
        );
    }

    public function testSelectAllTicksTheWholeAllowedList(): void
    {
        $field = ThingField::over(self::THINGS, ['selectAll' => true, 'allowedThings' => [self::THINGS[self::NEWS][0]]]);

        self::assertSame(array_column(self::THINGS, 0), $field->postedLists()['allowed']);
        self::assertSame([], $field->postedLists()['excluded']);
    }

    // Input
    // =========================================================================

    public function testAFieldWithNothingAllowedSaysSoAndShowsNoControl(): void
    {
        $input = ThingField::over(self::THINGS)->input(null);

        self::assertSame('No things are allowed for this field yet. Allow some in the field settings.', $input['notice']);
        self::assertNull($input['options']);
    }

    public function testASingleSelectOffersNoneAndTheAllowedItems(): void
    {
        $field = ThingField::over(self::THINGS, ['allowedThings' => [self::THINGS[self::NEWS][0], self::THINGS[self::SHOP][0]]]);
        $input = $field->input($field->normalizeValue('31'));

        self::assertNull($input['notice']);
        self::assertSame(['' => 'None', self::NEWS => 'News', self::SHOP => 'Shop'], $input['options']);
        self::assertSame(self::SHOP, $input['value']);
        self::assertFalse($input['dropdown']);
    }

    public function testARequiredSingleSelectDoesNotOfferNone(): void
    {
        $field = ThingField::over(self::THINGS, ['selectAll' => true, 'viewMode' => 'dropdown']);
        $field->required = true;
        $input = $field->input(null);

        self::assertSame([self::NEWS => 'News', self::BLOG => 'Blog', self::SHOP => 'Shop'], $input['options']);
        self::assertSame('', $input['value']);
        self::assertTrue($input['dropdown']);
    }

    public function testAMultiSelectHandsTheControlItsIdList(): void
    {
        $field = ThingField::over(self::THINGS, ['multiple' => true, 'partOfHandle' => 'PAGES', 'excludedThings' => ['14']]);
        $input = $field->input($field->normalizeValue('[31]'));

        self::assertSame([self::SHOP => 'Shop'], $input['options']);
        self::assertSame([self::SHOP], $input['value']);
    }

    /**
     * Excluding an item in the field settings must not drop it from entries that already hold it.
     */
    public function testAStoredSelectionThatHasSinceBeenExcludedStaysVisible(): void
    {
        $field = ThingField::over(self::THINGS, ['multiple' => true, 'selectAll' => true, 'excludedThings' => [self::THINGS[self::BLOG][0]]]);
        $input = $field->input($field->normalizeValue('[18,14]'));

        self::assertSame([self::NEWS => 'News', self::SHOP => 'Shop', self::BLOG => 'Blog'], $input['options']);
        self::assertSame([self::BLOG, self::NEWS], $input['value']);
    }
}
