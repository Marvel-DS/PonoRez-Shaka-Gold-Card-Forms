<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\Cache\CacheKeyGenerator;
use PonoRez\SGCForms\DTO\GuestType;
use PonoRez\SGCForms\DTO\GuestTypeCollection;
use PonoRez\SGCForms\UtilityService;
use RuntimeException;
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
        $guestTypes = UtilityService::getGuestTypes($activityConfig);

        foreach ($guestTypes as $guestType) {
            if (!isset($guestType['id'])) {
                continue;
            }

            $id = (string) $guestType['id'];
            if ($id === '') {
                continue;
            }

            $minQuantity = isset($guestType['minQuantity']) ? (int) $guestType['minQuantity'] : 0;
            $maxQuantity = isset($guestType['maxQuantity']) && $guestType['maxQuantity'] !== null
                ? (int) $guestType['maxQuantity']
                : 0;

            $collection->add(new GuestType(
                $id,
                isset($guestType['label']) ? (string) $guestType['label'] : $id,
                isset($guestType['description']) ? $guestType['description'] : null,
                isset($guestType['price']) && is_numeric($guestType['price']) ? (float) $guestType['price'] : null,
                max(0, $minQuantity),
                max(0, $maxQuantity)
            ));
        }

        return $collection;
    }

    private static function firstNonEmptyString(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $string = trim((string) $candidate);
            if ($string !== '') {
                return $string;
            }
        }

        return null;
    }

    private function mergeCollection(GuestTypeCollection $collection, array $soapData): GuestTypeCollection
    {
        foreach ($soapData as $row) {
            $id = (string) ($row['id'] ?? $row['guestTypeId'] ?? '');
            if ($id === '') {
                continue;
            }

            $guestType = $collection->get($id) ?? new GuestType($id, (string) ($row['name'] ?? $id));
            $label = self::firstNonEmptyString([
                $row['label'] ?? null,
                $row['name'] ?? null,
                $row['guestTypeName'] ?? null,
                $row['guestType'] ?? null,
            ]);

            if ($label !== null) {
                $guestType->setLabel($label);
            }

            $description = self::firstNonEmptyString([
                $row['description'] ?? null,
                $row['guestTypeDescription'] ?? null,
            ]);

            if ($description !== null) {
                $guestType->setDescription($description);
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

        $primaryActivityId = UtilityService::getPrimaryActivityId($activityConfig);
        if ($primaryActivityId === null) {
            throw new RuntimeException('Unable to determine primary activity ID for guest type lookup.');
        }

        $payload = [
            'serviceLogin' => [
                'username' => $supplierConfig['soapCredentials']['username'],
                'password' => $supplierConfig['soapCredentials']['password'],
            ],
            'supplierId' => $supplierConfig['supplierId'],
            'activityId' => $primaryActivityId,
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
