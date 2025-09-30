<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\DTO;

use Countable;
use IteratorAggregate;
use ArrayIterator;

final class GuestTypeCollection implements IteratorAggregate, Countable
{
    /** @var array<string, GuestType> */
    private array $items = [];

    public function add(GuestType $guestType): void
    {
        $this->items[$guestType->getId()] = $guestType;
    }

    public function get(string $id): ?GuestType
    {
        return $this->items[$id] ?? null;
    }

    /**
     * @return GuestType[]
     */
    public function all(): array
    {
        return array_values($this->items);
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->all());
    }

    public function count(): int
    {
        return count($this->items);
    }

    public function toArray(): array
    {
        return array_map(static fn (GuestType $type) => $type->toArray(), $this->all());
    }
}
