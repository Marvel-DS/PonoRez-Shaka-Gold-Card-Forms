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
    private const BASELINE_CACHE_TTL = 3600;

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
        $baselineCacheKey = CacheKeyGenerator::fromParts('guest-types', $supplierSlug, $activitySlug, 'baseline');

        $cached = $this->cache->get($cacheKey);
        if (!is_array($cached)) {
            $cached = $this->cache->get($baselineCacheKey);
        }

        if (is_array($cached)) {
            if ($cached !== []) {
                UtilityService::saveSupplierGuestTypeCache($supplierSlug, $activitySlug, $cached);
            }

            return $this->applySupplierCacheFallback(
                $this->mergeCollection($collection, $cached),
                $supplierSlug,
                $activitySlug
            );
        }

        try {
            $data = $this->fetchFromSoap($supplierConfig, $activityConfig, $date, $guestCounts);
            $this->cache->set($cacheKey, $data, self::CACHE_TTL);
            $this->cache->set($baselineCacheKey, $data, self::BASELINE_CACHE_TTL);

            if ($data !== []) {
                UtilityService::saveSupplierGuestTypeCache($supplierSlug, $activitySlug, $data);
            }

            return $this->applySupplierCacheFallback(
                $this->mergeCollection($collection, $data),
                $supplierSlug,
                $activitySlug
            );
        } catch (Throwable) {
            $cached = $this->cache->get($baselineCacheKey);
            if (is_array($cached)) {
                if ($cached !== []) {
                    UtilityService::saveSupplierGuestTypeCache($supplierSlug, $activitySlug, $cached);
                }

                return $this->applySupplierCacheFallback(
                    $this->mergeCollection($collection, $cached),
                    $supplierSlug,
                    $activitySlug
                );
            }

            $supplierCache = UtilityService::loadSupplierGuestTypeCache($supplierSlug, $activitySlug);
            if (is_array($supplierCache)) {
                return $this->applySupplierCacheFallback(
                    $this->mergeCollection($collection, $supplierCache),
                    $supplierSlug,
                    $activitySlug
                );
            }

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

    private function applySupplierCacheFallback(
        GuestTypeCollection $collection,
        string $supplierSlug,
        string $activitySlug
    ): GuestTypeCollection {
        $fallback = UtilityService::loadSupplierGuestTypeCache($supplierSlug, $activitySlug);
        if (!is_array($fallback) || $fallback === []) {
            return $collection;
        }

        foreach ($fallback as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = (string) ($row['id'] ?? $row['guestTypeId'] ?? '');
            if ($id === '') {
                continue;
            }

            $guestType = $collection->get($id) ?? new GuestType($id, $id);

            $currentLabel = trim($guestType->getLabel());
            if ($currentLabel === '' || strcasecmp($currentLabel, $id) === 0) {
                $fallbackLabel = self::firstNonEmptyString([
                    $row['label'] ?? null,
                    $row['name'] ?? null,
                    $row['guestTypeName'] ?? null,
                    $row['guestType'] ?? null,
                ]);

                if ($fallbackLabel !== null) {
                    $guestType->setLabel($fallbackLabel);
                }
            }

            $currentDescription = $guestType->getDescription();
            if ($currentDescription === null || trim($currentDescription) === '') {
                $fallbackDescription = self::firstNonEmptyString([
                    $row['description'] ?? null,
                    $row['guestTypeDescription'] ?? null,
                ]);

                if ($fallbackDescription !== null) {
                    $guestType->setDescription($fallbackDescription);
                }
            }

            if ($guestType->getPrice() === null && isset($row['price']) && is_numeric($row['price'])) {
                $guestType->setPrice((float) $row['price']);
            }

            $collection->add($guestType);
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
