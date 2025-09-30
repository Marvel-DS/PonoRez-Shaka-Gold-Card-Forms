<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\DTO;

final class CheckoutInitRequest
{
    /** @var array<string, int> */
    private array $guestCounts;

    /** @var array<string, int> */
    private array $upgrades;

    /** @var array<string, mixed> */
    private array $contact;

    /** @var array<int, array<string, mixed>> */
    private array $checklist;

    /** @var array<string, mixed> */
    private array $metadata;

    /**
     * @param array<string, int|string> $guestCounts
     * @param array<string, int|string> $upgrades
     * @param array<string, mixed> $contact
     * @param array<int, array<string, mixed>> $checklist
     * @param array<string, mixed> $metadata
     */
    public function __construct(
        private readonly string $supplierSlug,
        private readonly string $activitySlug,
        private readonly string $date,
        private readonly string $timeslotId,
        array $guestCounts = [],
        array $upgrades = [],
        array $contact = [],
        private ?string $transportationRouteId = null,
        array $checklist = [],
        array $metadata = []
    ) {
        $this->guestCounts = self::normalizeIntMap($guestCounts);
        $this->upgrades = self::normalizeIntMap($upgrades);
        $this->contact = $contact;
        $this->checklist = $checklist;
        $this->metadata = $metadata;
    }

    public function getSupplierSlug(): string
    {
        return $this->supplierSlug;
    }

    public function getActivitySlug(): string
    {
        return $this->activitySlug;
    }

    public function getDate(): string
    {
        return $this->date;
    }

    public function getTimeslotId(): string
    {
        return $this->timeslotId;
    }

    /**
     * @return array<string, int>
     */
    public function getGuestCounts(): array
    {
        return $this->guestCounts;
    }

    /**
     * @return array<string, int>
     */
    public function getUpgrades(): array
    {
        return $this->upgrades;
    }

    public function getTransportationRouteId(): ?string
    {
        return $this->transportationRouteId;
    }

    public function setTransportationRouteId(?string $routeId): void
    {
        $this->transportationRouteId = $routeId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContact(): array
    {
        return $this->contact;
    }

    public function setContact(array $contact): void
    {
        $this->contact = $contact;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getChecklist(): array
    {
        return $this->checklist;
    }

    /**
     * @param array<int, array<string, mixed>> $checklist
     */
    public function setChecklist(array $checklist): void
    {
        $this->checklist = $checklist;
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
            'supplierSlug' => $this->supplierSlug,
            'activitySlug' => $this->activitySlug,
            'date' => $this->date,
            'timeslotId' => $this->timeslotId,
            'guestCounts' => $this->guestCounts,
            'upgrades' => $this->upgrades,
            'contact' => $this->contact,
            'transportationRouteId' => $this->transportationRouteId,
            'checklist' => $this->checklist,
        ], $this->metadata);
    }

    /**
     * @param array<string, int|string> $values
     *
     * @return array<string, int>
     */
    private static function normalizeIntMap(array $values): array
    {
        $normalized = [];
        foreach ($values as $key => $value) {
            $intValue = (int) $value;
            if ($intValue < 0) {
                $intValue = 0;
            }
            $normalized[(string) $key] = $intValue;
        }

        return $normalized;
    }
}
