<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\support;

/**
 * Lets a test read what a field hands its two templates, and what the settings form posts back.
 *
 * Rendering the templates needs a Craft application; what they are given does not.
 */
trait ExposesTemplateData
{
    /**
     * What the settings template is given.
     *
     * @return array<string, mixed>
     */
    public function settingsForm(): array
    {
        return $this->settingsVariables();
    }

    /**
     * What the input template is given for a value.
     *
     * @return array<string, mixed>
     */
    public function input(mixed $value): array
    {
        return $this->inputVariables($value);
    }

    /**
     * What the two lists post when the settings form is saved untouched: of the options a list
     * shows, the ones its values tick. Craft's checkbox group ticks an option when its value is
     * `in` the values, compared loosely, which is how `[true]` ticks every option.
     *
     * @return array{allowed: string[], excluded: string[]}
     */
    public function postedLists(): array
    {
        $form = $this->settingsForm();
        $ticked = static fn(array $values) => array_values(array_filter(
            array_column($form['options'], 'value'),
            static fn($option) => in_array($option, $values),
        ));

        return [
            'allowed' => $ticked($form['allowedValues']),
            'excluded' => $ticked($form['excludedValues']),
        ];
    }
}
