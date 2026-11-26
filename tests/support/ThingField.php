<?php

declare(strict_types=1);

namespace wmd\sectionandproducttype\tests\support;

use wmd\sectionandproducttype\fields\BaseTypeField;

/**
 * A field over items held in memory, standing in for the Section, Entry Type and Tag Group
 * fields: an install can always tell which of those items exist.
 */
final class ThingField extends BaseTypeField
{
    use ExposesTemplateData;

    /** @var array Thing UIDs that may be chosen. */
    public array $allowedThings = [];

    /** @var array Thing UIDs never offered. */
    public array $excludedThings = [];

    /** @var array<int, object> The things this install has, keyed by ID. Private, so it is not a setting. */
    private array $things = [];

    /**
     * @param array<int, array{0: string, 1: string, 2: string}> $things `id => [uid, name, handle]`
     * @param array<string, mixed> $config
     */
    public static function over(array $things, array $config = []): self
    {
        $field = new self($config + ['name' => 'Things', 'handle' => 'things']);

        foreach ($things as $id => [$uid, $name, $handle]) {
            $field->things[$id] = (object)['id' => $id, 'uid' => $uid, 'name' => $name, 'handle' => $handle];
        }

        return $field;
    }

    protected static function allowedAttribute(): string
    {
        return 'allowedThings';
    }

    protected static function excludedAttribute(): string
    {
        return 'excludedThings';
    }

    protected static function itemWords(): array
    {
        return ['singular' => 'thing', 'plural' => 'things'];
    }

    protected function loadItems(): array
    {
        return $this->things;
    }

    protected function loadItem(int $id): ?object
    {
        return $this->things[$id] ?? null;
    }
}
