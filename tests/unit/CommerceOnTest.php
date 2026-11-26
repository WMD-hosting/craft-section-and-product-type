<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\unit;

use PHPUnit\Framework\TestCase;
use wmd\sectionandproducttype\models\SelectedItems;
use wmd\sectionandproducttype\SectionAndProductType;
use wmd\sectionandproducttype\tests\support\CommerceStandIn;
use wmd\sectionandproducttype\tests\support\ProductTypeFieldProbe;

/**
 * The Product Type field itself on an install where Commerce is on: product types can be listed,
 * so the field resolves its lists and values as every other field type does, and as 2.3.0 did.
 * A reference that names nothing is then a deleted product type, not one to keep.
 */
final class CommerceOnTest extends TestCase
{
    private const SHIRTS = 3;
    private const MUGS = 4;

    /** An ID no product type has. */
    private const GONE = 999999;

    private const PRODUCT_TYPES = [
        self::SHIRTS => ['aaaaaaaa-0000-4000-8000-000000000003', 'Shirts', 'shirts'],
        self::MUGS => ['bbbbbbbb-0000-4000-8000-000000000004', 'Mugs', 'mugs'],
    ];

    private const DELETED_UID = 'dddddddd-0000-4000-8000-000000000099';

    protected function setUp(): void
    {
        if (!CommerceStandIn::available()) {
            self::markTestSkipped('craftcms/commerce is not installed next to this PHPUnit.');
        }

        CommerceStandIn::on(self::PRODUCT_TYPES);
    }

    protected function tearDown(): void
    {
        CommerceStandIn::off();
    }

    public function testTheSettingsFormListsTheProductTypesByNameAndLeavesOutADeletedOne(): void
    {
        $field = new ProductTypeFieldProbe([
            'handle' => 'productTypes',
            'allowProductTypes' => [self::PRODUCT_TYPES[self::MUGS][0], '3', self::DELETED_UID],
            'excludedProductTypes' => [self::DELETED_UID, '999999'],
        ]);
        $form = $field->settingsForm();

        self::assertSame(['Shirts', 'Mugs'], array_column($form['options'], 'label'));
        self::assertSame(array_column(self::PRODUCT_TYPES, 0), array_column($form['options'], 'value'));
        self::assertSame(['allowed' => array_column(self::PRODUCT_TYPES, 0), 'excluded' => []], $field->postedLists());
        self::assertNull($form['allowedWarning']);
        self::assertNull($form['excludedWarning']);
    }

    public function testAnAllowListEntryThatNamesNoProductTypeIsRejected(): void
    {
        $field = new ProductTypeFieldProbe(['handle' => 'productTypes', 'allowProductTypes' => [self::PRODUCT_TYPES[self::MUGS][0], self::DELETED_UID]]);

        self::assertFalse($field->validate(['allowProductTypes']));
        self::assertSame(['Invalid product type selected.'], $field->getErrors('allowProductTypes'));
    }

    public function testWithObjectsADeletedProductTypesIdIsLeftOutAndNotSavedAgain(): void
    {
        $field = new ProductTypeFieldProbe(['handle' => 'productTypes', 'selectAll' => true, 'multiple' => true, 'valueType' => SectionAndProductType::VALUE_TYPE_OBJECTS]);
        $value = $field->normalizeValue('[999999,4]');

        self::assertInstanceOf(SelectedItems::class, $value);
        self::assertSame([self::MUGS], $value->ids());
        self::assertSame(['mugs'], $value->handles());
        self::assertSame([self::MUGS], $value->storedIds());
        self::assertSame('[4]', $field->serializeValue($value));
    }

    public function testTheInputOffersTheProductTypesByNameWithoutAWarning(): void
    {
        $field = new ProductTypeFieldProbe(['handle' => 'productTypes', 'selectAll' => true, 'multiple' => true]);
        $input = $field->input($field->normalizeValue('[999999,4]'));

        self::assertSame([self::SHIRTS => 'Shirts', self::MUGS => 'Mugs'], $input['options']);
        self::assertSame([self::GONE, self::MUGS], $input['value']);
        self::assertNull($input['warning']);
        self::assertNull($input['notice']);
    }

    public function testAFieldWithNothingAllowedSaysSo(): void
    {
        $input = (new ProductTypeFieldProbe(['handle' => 'productTypes']))->input(null);

        self::assertSame('No product types are allowed for this field yet. Allow some in the field settings.', $input['notice']);
        self::assertNull($input['warning']);
        self::assertNull($input['options']);
    }
}
