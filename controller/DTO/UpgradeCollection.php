<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\DTO;

use Countable;
use IteratorAggregate;
use ArrayIterator;

final class UpgradeCollection implements IteratorAggregate, Countable
{
    /** @var array<string, Upgrade> */
    private array $items = [];

    public function add(Upgrade $upgrade): void
    {
        $this->items[$upgrade->getId()] = $upgrade;
    }

    public function get(string $id): ?Upgrade
    {
        return $this->items[$id] ?? null;
    }

    /**
     * @return Upgrade[]
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
        return array_map(
            static fn (Upgrade $upgrade): array => $upgrade->toArray(),
            $this->all()
        );
    }
}

