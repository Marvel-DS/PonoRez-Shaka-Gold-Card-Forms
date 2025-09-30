<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\DTO;

final class Timeslot
{
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly ?int $available = null
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getAvailable(): ?int
    {
        return $this->available;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'available' => $this->available,
        ];
    }
}
