<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\DTO;

final class CheckoutInitResponse
{
    /** @var array<string, mixed> */
    private array $metadata;

    /**
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly float $totalPrice,
        private readonly float $supplierPaymentAmount,
        private readonly ?string $reservationId = null,
        array $metadata = []
    ) {
        $this->metadata = $metadata;
    }

    public function getTotalPrice(): float
    {
        return $this->totalPrice;
    }

    public function getSupplierPaymentAmount(): float
    {
        return $this->supplierPaymentAmount;
    }

    public function getReservationId(): ?string
    {
        return $this->reservationId;
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
        return array_merge([
            'totalPrice' => $this->totalPrice,
            'supplierPaymentAmount' => $this->supplierPaymentAmount,
            'reservationId' => $this->reservationId,
        ], $this->metadata);
    }
}
