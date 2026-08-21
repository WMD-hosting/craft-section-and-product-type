<?php

namespace wmd\sectionandproducttype\models;

use ArrayAccess;
use ArrayIterator;
use Countable;
use IteratorAggregate;
use JsonSerializable;
use Stringable;
use Traversable;


/**
 * The value returned by this plugin's fields when their Template Value setting
 * is set to `objects`.
 *
 * Iterating this object yields the selected IDs, exactly like the plain array
 * the fields have always returned, so it can still be handed straight to an
 * element query. The named accessors give you the underlying models instead.
 */
class SelectedItems implements IteratorAggregate, Countable, ArrayAccess, Stringable, JsonSerializable
{
    /**
     * @var array The selected models, in the order they were selected.
     */
    private array $items;

    /**
     * @param array $items The selected models. Each one needs an `id`, and may have a `name` and `handle`.
     */
    public function __construct(array $items = [])
    {
        $this->items = array_values($items);
    }

    /**
     * Return the selected models.
     *
     * @return array
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Return the first selected model, or null when nothing is selected.
     *
     * @return mixed
     */
    public function one(): mixed
    {
        return $this->items[0] ?? null;
    }

    /**
     * Return the selected IDs.
     *
     * @return array
     */
    public function ids(): array
    {
        return array_map(static fn($item) => (int)$item->id, $this->items);
    }

    /**
     * Return the selected handles.
     *
     * @return array
     */
    public function handles(): array
    {
        return array_map(static fn($item) => (string)($item->handle ?? ''), $this->items);
    }

    /**
     * Return the selected names.
     *
     * @return array
     */
    public function names(): array
    {
        return array_map(static fn($item) => (string)($item->name ?? ''), $this->items);
    }

    /**
     * Return whether nothing is selected.
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return $this->items === [];
    }

    /**
     * @inheritdoc
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->ids());
    }

    /**
     * @inheritdoc
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * @inheritdoc
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->items[$offset]);
    }

    /**
     * @inheritdoc
     */
    public function offsetGet(mixed $offset): mixed
    {
        return isset($this->items[$offset]) ? (int)$this->items[$offset]->id : null;
    }

    /**
     * @inheritdoc
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
    }

    /**
     * @inheritdoc
     */
    public function offsetUnset(mixed $offset): void
    {
    }

    /**
     * @inheritdoc
     */
    public function __toString(): string
    {
        return implode(', ', $this->ids());
    }

    /**
     * @inheritdoc
     */
    public function jsonSerialize(): mixed
    {
        return $this->ids();
    }
}
