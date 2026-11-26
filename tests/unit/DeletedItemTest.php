<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\SectionAndProductType;
use wmd\sectionandproducttype\tests\support\ThingField;

/**
 * A stored ID whose section (entry type, product type, tag group) has been deleted, on an install
 * that can tell which items exist. This pins what 2.3.0 does, so the 2.x line keeps doing it:
 * version 3 handles such an ID differently, and that change is not part of 2.x.
 */
final class DeletedItemTest extends TestCase
{
    private const NEWS = 14;
    private const BLOG = 18;

    /** An ID nothing has. */
    private const GONE = 999999;

    private const THINGS = [
        self::NEWS => ['aaaaaaaa-0000-4000-8000-000000000014', 'News', 'news'],
        self::BLOG => ['bbbbbbbb-0000-4000-8000-000000000018', 'Blog', 'blog'],
    ];

    public function testWithIdsADeletedItemsIdIsHandedBackAndSavedAgain(): void
    {
        $field = $this->field(['multiple' => true]);
        $value = $field->normalizeValue('[999999,14]');

        self::assertSame([self::GONE, self::NEWS], $value);
        self::assertSame('[999999,14]', $field->serializeValue($value));
    }

    public function testWithObjectsADeletedItemsIdIsLeftOutAndNotSavedAgain(): void
    {
        $field = $this->field(['multiple' => true, 'valueType' => SectionAndProductType::VALUE_TYPE_OBJECTS]);
        $value = $field->normalizeValue('[999999,14]');

        self::assertInstanceOf(SelectedItems::class, $value);
        self::assertSame([self::NEWS], $value->ids());
        self::assertSame(['news'], $value->handles());
        self::assertSame('[14]', $field->serializeValue($value));
    }

    /**
     * A single select reads a list left over from when Multiple Select was on as its first ID.
     *
     * @param int[] $ids What templates get, as IDs
     */
    #[DataProvider('oldLists')]
    public function testASingleSelectHoldingAnOldListGoesByItsFirstId(string $stored, string $valueType, array $ids, string $savedAgain): void
    {
        $field = $this->field(['valueType' => $valueType]);
        $value = $field->normalizeValue($stored);

        self::assertSame($ids, $value instanceof SelectedItems ? $value->ids() : [$value]);
        self::assertSame($savedAgain, $field->serializeValue($value));
    }

    /**
     * @return array<string, array{string, string, int[], string}>
     */
    public static function oldLists(): array
    {
        return [
            'IDs, deleted last' => ['[14,999999]', SectionAndProductType::VALUE_TYPE_IDS, [self::NEWS], '14'],
            'IDs, deleted first' => ['[999999,14]', SectionAndProductType::VALUE_TYPE_IDS, [self::GONE], '999999'],
            'Objects, deleted last' => ['[14,999999]', SectionAndProductType::VALUE_TYPE_OBJECTS, [self::NEWS], '14'],
            'Objects, deleted first' => ['[999999,14]', SectionAndProductType::VALUE_TYPE_OBJECTS, [], '""'],
        ];
    }

    #[DataProvider('templateValues')]
    public function testTheInputHasNoOptionForADeletedItemsId(string $valueType): void
    {
        $field = $this->field(['multiple' => true, 'valueType' => $valueType]);
        $input = $field->input($field->normalizeValue('[999999,14]'));

        self::assertSame([self::NEWS => 'News', self::BLOG => 'Blog'], $input['options']);
        self::assertNull($input['notice']);
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

    /**
     * In the field settings a reference that names nothing is left out of the form, so saving
     * the form drops it, and a save that still carries it is rejected.
     */
    public function testADeletedItemIsLeftOutOfTheSettingsFormAndRejectedWhenSaved(): void
    {
        $deleted = 'dddddddd-0000-4000-8000-000000000099';
        $field = ThingField::over(self::THINGS, [
            'allowedThings' => [self::THINGS[self::NEWS][0], $deleted, '999999'],
            'excludedThings' => [$deleted],
        ]);

        self::assertSame(['allowed' => [self::THINGS[self::NEWS][0]], 'excluded' => []], $field->postedLists());
        self::assertFalse($field->validate(['allowedThings']));
        self::assertSame(['Invalid thing selected.'], $field->getErrors('allowedThings'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function field(array $config = []): ThingField
    {
        return ThingField::over(self::THINGS, $config + ['selectAll' => true]);
    }
}
