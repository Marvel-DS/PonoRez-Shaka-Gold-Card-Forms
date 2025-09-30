<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\DTO;

final class AvailabilityDay
{
    public function __construct(
        private readonly string $date,
        private readonly string $status
    ) {
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function toArray(): array
    {
        return [
            'date' => $this->date,
            'status' => $this->status,
        ];
    }
}
