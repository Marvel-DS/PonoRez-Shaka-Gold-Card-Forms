<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\Cache\CacheKeyGenerator;
use PonoRez\SGCForms\DTO\GuestType;
use PonoRez\SGCForms\DTO\GuestTypeCollection;
use PonoRez\SGCForms\UtilityService;
use SoapFault;
use Throwable;

final class GuestTypeService
{
    private const CACHE_TTL = 600;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SoapClientFactory $soapClientBuilder
    ) {
    }

    public function fetch(string $supplierSlug, string $activitySlug, string $date, array $guestCounts = []): GuestTypeCollection
    {
        $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
        $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);

        $collection = $this->buildCollectionFromConfig($activityConfig);

        $cacheKey = CacheKeyGenerator::fromParts('guest-types', $supplierSlug, $activitySlug, $date);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $this->mergeCollection($collection, $cached);
        }

        try {
            $data = $this->fetchFromSoap($supplierConfig, $activityConfig, $date, $guestCounts);
            $this->cache->set($cacheKey, $data, self::CACHE_TTL);
            return $this->mergeCollection($collection, $data);
        } catch (Throwable) {
            return $collection;
        }
    }

    private function buildCollectionFromConfig(array $activityConfig): GuestTypeCollection
    {
        $collection = new GuestTypeCollection();
        $labels = $activityConfig['guestTypeLabels'] ?? [];
        $descriptions = $activityConfig['ponorezGuestTypeDescriptions'] ?? [];
        $min = $activityConfig['minGuestCount'] ?? [];
        $max = $activityConfig['maxGuestCount'] ?? [];

        foreach ($activityConfig['guestTypeIds'] ?? [] as $guestTypeId) {
            $id = (string) $guestTypeId;
            $collection->add(new GuestType(
                $id,
                $labels[$id] ?? $id,
                $descriptions[$id] ?? null,
                null,
                isset($min[$id]) ? (int) $min[$id] : 0,
                isset($max[$id]) ? (int) $max[$id] : 0
            ));
        }

        return $collection;
    }

    private function mergeCollection(GuestTypeCollection $collection, array $soapData): GuestTypeCollection
    {
        foreach ($soapData as $row) {
            $id = (string) ($row['id'] ?? $row['guestTypeId'] ?? '');
            if ($id === '') {
                continue;
            }

            $guestType = $collection->get($id) ?? new GuestType($id, (string) ($row['name'] ?? $id));
            if (isset($row['name'])) {
                $guestType->setLabel((string) $row['name']);
            }
            if (isset($row['description'])) {
                $guestType->setDescription((string) $row['description']);
            }
            if (isset($row['price'])) {
                $guestType->setPrice(is_numeric($row['price']) ? (float) $row['price'] : null);
            }
            $collection->add($guestType);
        }

        return $collection;
    }

    private function fetchFromSoap(array $supplierConfig, array $activityConfig, string $date, array $guestCounts): array
    {
        $client = $this->soapClientBuilder->build();

        $payload = [
            'serviceLogin' => [
                'username' => $supplierConfig['soapCredentials']['username'],
                'password' => $supplierConfig['soapCredentials']['password'],
            ],
            'supplierId' => $supplierConfig['supplierId'],
            'activityId' => $activityConfig['activityId'],
            'date' => $date,
        ];

        if (!empty($guestCounts)) {
            $payload['guestCounts'] = [];
            foreach ($guestCounts as $guestTypeId => $count) {
                $payload['guestCounts'][] = [
                    'guestTypeId' => $guestTypeId,
                    'guestCount' => $count,
                ];
            }
        }

        try {
            $response = $client->__soapCall('getActivityGuestTypes', [$payload]);
        } catch (SoapFault $exception) {
            throw $exception;
        }

        $items = [];
        $rows = $response->return ?? $response ?? [];
        if (is_object($rows)) {
            $rows = [$rows];
        }
        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = json_decode(json_encode($row), true) ?: [];
                }
                if (is_array($row)) {
                    $items[] = $row;
                }
            }
        }

        return $items;
    }
}
