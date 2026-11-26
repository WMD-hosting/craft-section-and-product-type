<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\SectionAndProductType;
use wmd\sectionandproducttype\tests\support\ThingField;

/**
 * What templates get from a field with nothing chosen.
 *
 * A single select saved empty is stored as `""`. From 2.2.0 to 2.3.0 it read back as `''`
 * instead of `null`, so a template's `is not null` was true for an empty field.
 */
final class EmptyValueTest extends TestCase
{
    private const THINGS = [14 => ['aaaaaaaa-0000-4000-8000-000000000014', 'News', 'news']];

    public function testASingleSelectSavedWithNothingChosenReadsBackAsNull(): void
    {
        $field = ThingField::over(self::THINGS, ['selectAll' => true]);
        $stored = $field->serializeValue(null);

        // What 2.x has always written for an empty single select, and still does.
        self::assertSame('""', $stored);
        self::assertNull($field->normalizeValue($stored));
    }

    #[DataProvider('emptyValues')]
    public function testEveryEmptyShapeOfASingleSelectIsNull(mixed $empty): void
    {
        $field = ThingField::over(self::THINGS, ['selectAll' => true]);

        self::assertNull($field->normalizeValue($empty));
    }

    #[DataProvider('emptyValues')]
    public function testEveryEmptyShapeOfAMultiSelectIsAnEmptyList(mixed $empty): void
    {
        $field = ThingField::over(self::THINGS, ['selectAll' => true, 'multiple' => true]);

        self::assertSame([], $field->normalizeValue($empty));
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function emptyValues(): array
    {
        return [
            'never saved' => [null],
            'saved empty by a single select' => ['""'],
            'saved empty by a multi select' => ['[]'],
            'posted by the form' => [''],
            'an empty list' => [[]],
            'a list of blanks' => [['', null]],
            'an empty Objects value' => [new SelectedItems()],
        ];
    }

    public function testWithObjectsAnEmptySingleSelectIsStillAnEmptyObject(): void
    {
        $field = ThingField::over(self::THINGS, ['selectAll' => true, 'valueType' => SectionAndProductType::VALUE_TYPE_OBJECTS]);
        $value = $field->normalizeValue('""');

        self::assertInstanceOf(SelectedItems::class, $value);
        self::assertTrue($value->isEmpty());
        self::assertNull($value->one());
    }

    public function testAChosenItemIsStillItsId(): void
    {
        $single = ThingField::over(self::THINGS, ['selectAll' => true]);
        $multiple = ThingField::over(self::THINGS, ['selectAll' => true, 'multiple' => true]);

        self::assertSame(14, $single->normalizeValue('14'));
        self::assertSame(14, $single->normalizeValue(14));
        self::assertSame(14, $single->normalizeValue(['14']));
        self::assertSame([14], $multiple->normalizeValue('[14]'));
        self::assertSame([14], $multiple->normalizeValue('14'));
    }
}
