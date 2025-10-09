<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\Cache\CacheKeyGenerator;
use PonoRez\SGCForms\DTO\TransportationRoute;
use PonoRez\SGCForms\DTO\TransportationSet;
use PonoRez\SGCForms\UtilityService;
use RuntimeException;
use SoapFault;
use Throwable;

final class TransportationService
{
    private const CACHE_TTL = 600;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SoapClientFactory $soapClientBuilder
    ) {
    }

    public function fetch(string $supplierSlug, string $activitySlug): TransportationSet
    {
        $cacheKey = CacheKeyGenerator::fromParts('transportation', $supplierSlug, $activitySlug);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $this->hydrateSet($cached);
        }

        $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
        $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);

        [$set, $disabledIds] = $this->buildSetFromConfig($activityConfig['transportation'] ?? []);

        try {
            $soapData = $this->fetchFromSoap($supplierConfig, $activityConfig);
            if ($soapData !== []) {
                $this->mergeSoapData($set, $soapData, $disabledIds);
            }
            $this->cache->set($cacheKey, $set->toArray(), self::CACHE_TTL);
        } catch (Throwable) {
            return $set;
        }

        return $set;
    }

    /**
     * @param array<string, mixed> $config
     * @return array{0:TransportationSet,1:array<int,string>}
     */
    private function buildSetFromConfig(array $config): array
    {
        $mandatory = isset($config['mandatory']) ? (bool) $config['mandatory'] : false;
        $defaultRouteId = isset($config['defaultRouteId']) ? (string) $config['defaultRouteId'] : null;
        $set = new TransportationSet($mandatory, $defaultRouteId);

        $disabledIds = [];

        foreach ($config['routes'] ?? [] as $route) {
            if (!is_array($route)) {
                continue;
            }

            $enabled = $route['enabled'] ?? true;
            $routeId = isset($route['id']) ? (string) $route['id'] : null;
            if ($routeId === null) {
                continue;
            }

            if ($enabled === false) {
                $disabledIds[] = $routeId;
                continue;
            }

            $label = isset($route['label']) ? (string) $route['label'] : $routeId;
            $set->addRoute(new TransportationRoute($routeId, $label, $route));
        }

        return [$set, $disabledIds];
    }

    private function hydrateSet(array $data): TransportationSet
    {
        $set = new TransportationSet(
            isset($data['mandatory']) ? (bool) $data['mandatory'] : false,
            isset($data['defaultRouteId']) ? (string) $data['defaultRouteId'] : null
        );

        foreach ($data['routes'] ?? [] as $route) {
            if (!is_array($route) || !isset($route['id'])) {
                continue;
            }

            $label = isset($route['label']) ? (string) $route['label'] : (string) $route['id'];
            $set->addRoute(new TransportationRoute((string) $route['id'], $label, $route));
        }

        return $set;
    }

    private function fetchFromSoap(array $supplierConfig, array $activityConfig): array
    {
        $client = $this->soapClientBuilder->build();

        $primaryActivityId = UtilityService::getPrimaryActivityId($activityConfig);
        if ($primaryActivityId === null) {
            throw new RuntimeException('Unable to determine primary activity ID for transportation lookup.');
        }

        $payload = [
            'serviceLogin' => [
                'username' => $supplierConfig['soapCredentials']['username'],
                'password' => $supplierConfig['soapCredentials']['password'],
            ],
            'supplierId' => $supplierConfig['supplierId'],
            'activityId' => $primaryActivityId,
        ];

        try {
            $response = $client->__soapCall('getActivityTransportationRoutes', [$payload]);
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
    private function mergeSoapData(TransportationSet $set, array $soapData, array $disabledIds): void
    {
        $disabledLookup = array_fill_keys($disabledIds, true);

        foreach ($soapData as $row) {
            $id = (string) ($row['id'] ?? $row['routeId'] ?? '');
            if ($id === '' || isset($disabledLookup[$id])) {
                continue;
            }

            $label = (string) ($row['label'] ?? $row['name'] ?? $id);
            $route = $set->getRoute($id) ?? new TransportationRoute($id, $label);

            $route->setLabel($label);

            if (isset($row['description'])) {
                $route->setDescription((string) $row['description']);
            }

            if (isset($row['price']) && $row['price'] !== '') {
                $route->setPrice(is_numeric($row['price']) ? (float) $row['price'] : null);
            }

            if (isset($row['capacity'])) {
                $route->setCapacity(is_numeric($row['capacity']) ? (int) $row['capacity'] : null);
            }

            $metadata = array_diff_key($row, [
                'id' => true,
                'routeId' => true,
                'label' => true,
                'name' => true,
                'description' => true,
                'price' => true,
                'capacity' => true,
                'isDefault' => true,
                'default' => true,
            ]);
            $route->setMetadata(array_merge($route->getMetadata(), $metadata));

            $set->addRoute($route);

            $isDefault = ($row['isDefault'] ?? $row['default'] ?? false) ? true : false;
            if ($isDefault) {
                $set->setDefaultRouteId($id);
            }
        }
    }
}
