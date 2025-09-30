<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\DTO;

final class TransportationRoute
{
    private ?string $description;

    private ?float $price;

    private ?int $capacity;

    /** @var array<string, mixed> */
    private array $metadata;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        private readonly string $id,
        private string $label,
        array $attributes = []
    ) {
        $this->description = isset($attributes['description'])
            ? (string) $attributes['description']
            : null;

        $this->price = isset($attributes['price']) && $attributes['price'] !== ''
            ? (float) $attributes['price']
            : null;

        $this->capacity = isset($attributes['capacity'])
            ? (int) $attributes['capacity']
            : null;

        $this->metadata = array_diff_key($attributes, [
            'description' => true,
            'price' => true,
            'capacity' => true,
            'id' => true,
            'label' => true,
        ]);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): void
    {
        $this->description = $description;
    }

    public function getPrice(): ?float
    {
        return $this->price;
    }

    public function setPrice(?float $price): void
    {
        $this->price = $price;
    }

    public function getCapacity(): ?int
    {
        return $this->capacity;
    }

    public function setCapacity(?int $capacity): void
    {
        $this->capacity = $capacity;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }

    public function toArray(): array
    {
        $data = [
            'id' => $this->id,
            'label' => $this->label,
            'description' => $this->description,
            'price' => $this->price,
            'capacity' => $this->capacity,
        ];

        return array_filter(
            array_merge($data, $this->metadata),
            static fn (mixed $value): bool => $value !== null
        );
    }
}

