<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\DTO;

use Countable;
use IteratorAggregate;
use ArrayIterator;

final class AvailabilityCalendar implements IteratorAggregate, Countable
{
    /** @var AvailabilityDay[] */
    private array $days = [];

    public function addDay(AvailabilityDay $day): void
    {
        $this->days[] = $day;
    }

    /**
     * @return AvailabilityDay[]
     */
    public function all(): array
    {
        return $this->days;
    }

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->days);
    }

    public function count(): int
    {
        return count($this->days);
    }

    public function toArray(): array
    {
        return array_map(static fn (AvailabilityDay $day) => $day->toArray(), $this->days);
    }
}
