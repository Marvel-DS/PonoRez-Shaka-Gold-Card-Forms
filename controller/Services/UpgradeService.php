<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use DateTimeImmutable;
use DateTimeZone;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\Cache\CacheKeyGenerator;
use PonoRez\SGCForms\DTO\Upgrade;
use PonoRez\SGCForms\DTO\UpgradeCollection;
use PonoRez\SGCForms\UtilityService;
use RuntimeException;
use SoapFault;
use Throwable;

final class UpgradeService
{
    private const CACHE_TTL = 600;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SoapClientFactory $soapClientBuilder
    ) {
    }

    public function fetch(string $supplierSlug, string $activitySlug): UpgradeCollection
    {
        $cacheKey = CacheKeyGenerator::fromParts('upgrades', $supplierSlug, $activitySlug);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $this->hydrateCollection($cached);
        }

        $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
        $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);

        if (!empty($activityConfig['disableUpgrades'])) {
            return new UpgradeCollection();
        }

        if (!array_key_exists('upgrades', $activityConfig)) {
            return new UpgradeCollection();
        }

        [$collection, $disabledIds] = $this->buildCollectionFromConfig($activityConfig['upgrades'] ?? []);

        try {
            $soapData = $this->fetchFromSoap($supplierConfig, $activityConfig);
            if ($soapData !== []) {
                $this->mergeCollection($collection, $soapData, $disabledIds);
            }
            $this->cache->set($cacheKey, $collection->toArray(), self::CACHE_TTL);
        } catch (Throwable) {
            return $collection;
        }

        return $collection;
    }

    /**
     * @param array<int, array<string, mixed>> $config
     * @return array{0:UpgradeCollection,1:array<int,string>}
     */
    private function buildCollectionFromConfig(array $config): array
    {
        $collection = new UpgradeCollection();
        $disabledIds = [];

        foreach ($config as $row) {
            if (!is_array($row)) {
                continue;
            }

            $id = isset($row['id']) ? (string) $row['id'] : null;
            if ($id === null) {
                continue;
            }

            if (($row['enabled'] ?? true) === false) {
                $disabledIds[] = $id;
                continue;
            }

            $webBookableFlag = $this->resolveWebBookableFlag($row);
            if ($webBookableFlag === false) {
                $disabledIds[] = $id;
                continue;
            }

            if ($webBookableFlag === true) {
                $row['webBookable'] = true;
            }

            $label = isset($row['label']) ? (string) $row['label'] : $id;
            $collection->add(new Upgrade($id, $label, $row));
        }

        return [$collection, $disabledIds];
    }

    private function hydrateCollection(array $data): UpgradeCollection
    {
        $collection = new UpgradeCollection();

        foreach ($data as $row) {
            if (!is_array($row) || !isset($row['id'])) {
                continue;
            }

            $webFlag = $this->resolveWebBookableFlag($row);
            if ($webFlag === false) {
                continue;
            }

            $label = isset($row['label']) ? (string) $row['label'] : (string) $row['id'];
            $collection->add(new Upgrade((string) $row['id'], $label, $row));
        }

        return $collection;
    }

    private function fetchFromSoap(array $supplierConfig, array $activityConfig): array
    {
        $client = $this->soapClientBuilder->build();

        $primaryActivityId = UtilityService::getPrimaryActivityId($activityConfig);
        if ($primaryActivityId === null) {
            throw new RuntimeException('Unable to determine primary activity ID for upgrade lookup.');
        }

        $date = $this->resolveLookupDate($activityConfig);

        $payload = [
            'serviceLogin' => [
                'username' => $supplierConfig['soapCredentials']['username'],
                'password' => $supplierConfig['soapCredentials']['password'],
            ],
            'supplierId' => $supplierConfig['supplierId'],
            'activityId' => $primaryActivityId,
            'date' => $date,
        ];

        try {
            $response = $client->__soapCall('getActivityUpgrades', [$payload]);
        } catch (SoapFault $exception) {
            throw $exception;
        }

        $rows = $response->return ?? $response ?? [];
        if (is_object($rows)) {
            $rows = [$rows];
        }

        $items = [];
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

    /**
     * @param array<int, array<string, mixed>> $soapData
     * @param array<int, string> $disabledIds
     */
    private function mergeCollection(UpgradeCollection $collection, array $soapData, array $disabledIds): void
    {
        $disabledLookup = array_fill_keys($disabledIds, true);

        foreach ($soapData as $row) {
            $id = (string) ($row['id'] ?? $row['upgradeId'] ?? '');
            if ($id === '' || isset($disabledLookup[$id])) {
                continue;
            }

            $label = (string) ($row['label'] ?? $row['name'] ?? $id);
            $upgrade = $collection->get($id);

            if ($upgrade === null) {
                $upgrade = new Upgrade($id, $label);
            } elseif ($upgrade->getLabel() === $upgrade->getId()) {
                $upgrade->setLabel($label);
            }

            $configFlag = $this->resolveWebBookableFlag($upgrade->getMetadata());
            $soapFlag = $this->resolveWebBookableFlag($row);
            $webBookable = $soapFlag ?? $configFlag;

            if ($webBookable === false) {
                $collection->remove($id);
                continue;
            }

            if ($upgrade->getDescription() === null && isset($row['description'])) {
                $upgrade->setDescription((string) $row['description']);
            }

            if ($upgrade->getPrice() === null && isset($row['price']) && $row['price'] !== '') {
                $upgrade->setPrice(is_numeric($row['price']) ? (float) $row['price'] : null);
            }

            if ($upgrade->getMaxQuantity() === null && array_key_exists('maxQuantity', $row)) {
                $upgrade->setMaxQuantity(is_numeric($row['maxQuantity']) ? (int) $row['maxQuantity'] : null);
            }

            if ($upgrade->getMinQuantity() === null && array_key_exists('minQuantity', $row)) {
                $upgrade->setMinQuantity(is_numeric($row['minQuantity']) ? (int) $row['minQuantity'] : null);
            }

            $metadata = array_diff_key($row, [
                'id' => true,
                'upgradeId' => true,
                'label' => true,
                'name' => true,
                'description' => true,
                'price' => true,
                'maxQuantity' => true,
                'minQuantity' => true,
                'webBookable' => true,
                'webbookable' => true,
                'isWebBookable' => true,
                'isWebbookable' => true,
                'webBooking' => true,
                'webbooking' => true,
                'allowWebBooking' => true,
                'allowWeb' => true,
            ]);

            if ($webBookable !== null) {
                $metadata['webBookable'] = $webBookable;
            }

            $upgrade->setMetadata(array_merge($upgrade->getMetadata(), $metadata));

            $collection->add($upgrade);
        }
    }

    private function resolveWebBookableFlag(array $source): ?bool
    {
        $normalized = array_change_key_case($source, CASE_LOWER);

        foreach ([
            'webbookable',
            'iswebbookable',
            'webbooking',
            'allowwebbooking',
            'allowweb',
        ] as $key) {
            if (!array_key_exists($key, $normalized)) {
                continue;
            }

            $flag = $this->normalizeBoolean($normalized[$key]);
            if ($flag !== null) {
                return $flag;
            }
        }

        return null;
    }

    private function normalizeBoolean(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return ((int) $value) !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                return null;
            }

            if (in_array($normalized, ['1', 'true', 'yes', 'on', 'y'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', 'n'], true)) {
                return false;
            }
        }

        return null;
    }

    private function resolveLookupDate(array $activityConfig): string
    {
        $override = UtilityService::getEnvironmentSetting('currentDate');
        if (is_string($override) && $override !== '') {
            return $override;
        }

        try {
            $timezone = UtilityService::getActivityTimezone($activityConfig);
            $zone = new DateTimeZone($timezone);
        } catch (Throwable) {
            $zone = null;
        }

        $now = new DateTimeImmutable('now', $zone);
        return $now->format('Y-m-d');
    }
}
