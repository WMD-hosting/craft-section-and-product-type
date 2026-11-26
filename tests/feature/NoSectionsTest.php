<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\feature;

use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Plugin\Testing\PluginTestCase;
use CraftCms\Cms\Support\Facades\Fields;
use Illuminate\Support\Facades\Validator;
use wmd\sectionandproducttype\fields\SectionField;

/**
 * An install that has no sections is not one that cannot tell its sections: every reference there is known
 * to name nothing. Nothing is kept or let through the way a Product Type field does while Commerce is off.
 */
final class NoSectionsTest extends PluginTestCase
{
    use InspectsFields;

    private const string SOME_UID = 'eaa2390a-6a37-4b51-bdf8-b3f7fe4072a0';

    public function test_an_allow_list_is_still_checked(): void
    {
        foreach ([self::SOME_UID, '42', 'anything'] as $reference) {
            $field = new SectionField(['name' => 'Source', 'handle' => 'source', 'allowedSections' => [$reference]]);

            self::assertFalse(Fields::saveField($field), $reference);
            self::assertSame(['Invalid section selected.'], $field->errors()->get('allowedSections'));
        }
    }

    public function test_the_settings_form_shows_no_reference_and_no_notice(): void
    {
        $field = new SectionField(['handle' => 'source', 'allowedSections' => [self::SOME_UID], 'excludedSections' => [self::SOME_UID]]);

        self::assertSame([], $this->settingsFormValues($field)['allowedSections']);
        self::assertSame([], $this->settingsFormValues($field)['excludedSections']);
        self::assertSame([], $this->settingsFormControls($field)['allowedSections']->props()['options']);
        self::assertNull($this->settingsFormWarnings($field)['allowedSections']);
    }

    public function test_a_value_is_still_rejected(): void
    {
        $field = new SectionField(['name' => 'Source', 'handle' => 'source', 'selectAll' => true]);
        [$rule] = $field->getElementRules(new Entry);

        self::assertTrue(Validator::make(['source' => 5], ['source' => [$rule]])->fails());
    }
}
