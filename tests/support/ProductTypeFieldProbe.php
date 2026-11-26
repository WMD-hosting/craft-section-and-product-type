<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\support;

use wmd\sectionandproducttype\fields\ProductTypeField;

/**
 * The Product Type field itself. The tests run without a Craft application, so Commerce is
 * never loaded: this is the field on an install where Commerce is not installed or is disabled.
 */
final class ProductTypeFieldProbe extends ProductTypeField
{
    use ExposesTemplateData;
}
