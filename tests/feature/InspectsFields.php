<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\feature;

use CraftCms\Cms\Database\Factories\EntryFactory;
use CraftCms\Cms\Database\Factories\FieldLayoutFactory;
use CraftCms\Cms\Database\Table;
use CraftCms\Cms\Entry\Elements\Entry;
use CraftCms\Cms\Field\Contracts\FieldInterface;
use CraftCms\Cms\Http\Controllers\FieldsController;
use CraftCms\Cms\Support\Facades\Elements;
use CraftCms\Cms\Support\Facades\Fields;
use CraftCms\Cms\Ui\Contracts\Control;
use CraftCms\Cms\Ui\Nodes\Field as UiField;
use CraftCms\Cms\User\Models\User;
use Illuminate\Support\Facades\DB;
use wmd\sectionandproducttype\fields\BaseTypeField;

/**
 * What the feature tests share: reading a field's settings form, saving it the way the control panel does,
 * and entries that hold a value as an earlier save left it.
 */
trait InspectsFields
{
    /**
     * The fields of a field's settings form, keyed by the setting they post.
     *
     * @return array<string, UiField>
     */
    private function settingsFormFields(BaseTypeField $field): array
    {
        $fields = [];

        foreach ($field->settingsUi()->nodes() as $node) {
            if ($node instanceof UiField && $node->getControl()) {
                $fields[(string) $node->getControl()->path()] = $node;
            }
        }

        return $fields;
    }

    /**
     * @return array<string, Control>
     */
    private function settingsFormControls(BaseTypeField $field): array
    {
        return array_map(static fn (UiField $node) => $node->getControl(), $this->settingsFormFields($field));
    }

    /**
     * @return array<string, string|null>
     */
    private function settingsFormWarnings(BaseTypeField $field): array
    {
        return array_map(static fn (UiField $node) => $node->props()['warning'] ?? null, $this->settingsFormFields($field));
    }

    /**
     * What a field's settings form holds when it opens, and so posts back when saved untouched.
     *
     * @return array<string, mixed>
     */
    private function settingsFormValues(BaseTypeField $field): array
    {
        return array_map(static fn ($control) => $control->getValue(), $this->settingsFormControls($field));
    }

    /**
     * Store a field as an earlier version or another environment left it (no validation), open its settings
     * in the control panel and save them untouched: the form's values, posted to Craft's own controller.
     */
    private function saveFromTheSettingsForm(BaseTypeField $field): FieldInterface
    {
        self::assertTrue(Fields::saveField($field, false));
        $stored = $this->reload($field);

        $this->actingAs(User::query()->firstOrFail())
            ->postJson(action([FieldsController::class, 'store']), [
                'fieldId' => $stored->id,
                'type' => $stored::class,
                'name' => $stored->name,
                'handle' => $stored->handle,
                'settings' => $this->settingsFormValues($stored),
            ])
            ->assertOk();

        return $this->reload($stored);
    }

    /**
     * The field as the database has it.
     */
    private function reload(FieldInterface $field): FieldInterface
    {
        Fields::refreshFields();

        $reloaded = Fields::getFieldById($field->id);
        self::assertInstanceOf($field::class, $reloaded);

        return $reloaded;
    }

    /**
     * A saved entry whose field layout has the field and whose content holds `$stored` for it, written
     * straight to the database the way an earlier save left it. Loaded fresh, so the field reads it as it
     * reads any stored value.
     */
    private function entryHolding(BaseTypeField $field, mixed $stored): Entry
    {
        self::assertTrue(Fields::saveField($field), implode(' ', $field->errors()->all()));
        $field = $this->reload($field);

        $layout = FieldLayoutFactory::new()->forField($field)->create();
        $entry = EntryFactory::new()->withFieldLayout($layout)->createElement(['title' => 'Entry']);
        self::assertTrue(Elements::saveElement($entry, false));

        DB::table(Table::ELEMENTS_SITES)
            ->where('elementId', $entry->id)
            ->update(['content' => json_encode([$this->contentKey($entry) => $stored])]);

        return $this->fresh($entry);
    }

    private function fresh(Entry $entry): Entry
    {
        $fresh = Entry::find()->id($entry->id)->status(null)->one();
        self::assertInstanceOf(Entry::class, $fresh);

        return $fresh;
    }

    /**
     * What the database holds for the entry's one custom field.
     */
    private function storedValue(Entry $entry): mixed
    {
        $content = DB::table(Table::ELEMENTS_SITES)->where('elementId', $entry->id)->value('content');

        return json_decode((string) $content, true)[$this->contentKey($entry)] ?? null;
    }

    /**
     * Content is keyed by the UID of the field's place in the layout.
     */
    private function contentKey(Entry $entry): string
    {
        return $entry->getFieldLayout()->getCustomFields()[0]->layoutElement->uid;
    }
}
