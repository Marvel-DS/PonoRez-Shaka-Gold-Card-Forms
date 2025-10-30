<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use JsonException;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\Cache\CacheKeyGenerator;
use PonoRez\SGCForms\UtilityService;
use SoapClient;
use SoapFault;
use Throwable;

final class ActivityInfoService
{
    private const CACHE_KEY_PREFIX = 'activity-info';
    private const DEFAULT_REFRESH_INTERVAL = 86400; // 24 hours

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SoapClientFactory $soapClientBuilder,
        private readonly int $refreshInterval = self::DEFAULT_REFRESH_INTERVAL
    ) {
    }

    /**
     * @return array{
     *     activities: array<string, array{
     *         id: int,
     *         name: ?string,
     *         island: ?string,
     *         times: ?string,
     *         startTimeMinutes: ?int,
     *         transportationMandatory: ?bool,
     *         description: ?string,
     *         notes: ?string,
     *         directions: ?string,
     *     }>,
     *     checkedAt: ?int,
     *     hash: ?string
     * }
     */
    public function getActivityInfo(string $supplierSlug, string $activitySlug): array
    {
        $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
        $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);

        $activityIds = $this->extractActivityIds($activityConfig);
        if ($activityIds === []) {
            return [
                'activities' => [],
                'checkedAt' => null,
                'hash' => null,
            ];
        }

        $cacheKey = CacheKeyGenerator::fromParts(self::CACHE_KEY_PREFIX, $supplierSlug, $activitySlug);
        $cached = $this->cache->get($cacheKey);
        $normalizedCache = $this->normalizeCachedPayload($cached, $activityIds);

        if ($normalizedCache !== null && !$this->shouldRefresh($normalizedCache['checkedAt'] ?? null)) {
            return $normalizedCache;
        }

        $fresh = $this->attemptRefresh($cacheKey, $supplierConfig, $activityIds, $normalizedCache);
        if ($fresh !== null) {
            return $fresh;
        }

        if ($normalizedCache !== null) {
            return $normalizedCache;
        }

        return [
            'activities' => [],
            'checkedAt' => null,
            'hash' => null,
        ];
    }

    /**
     * @param array<string, mixed>|null $cached
     * @param int[] $activityIds
     */
    private function normalizeCachedPayload(mixed $cached, array $activityIds): ?array
    {
        if (!is_array($cached)) {
            return null;
        }

        $activities = [];
        foreach ($cached['activities'] ?? [] as $key => $value) {
            $id = $this->resolveActivityIdFromCacheKey($key, $value, $activityIds);
            if ($id === null || !is_array($value)) {
                continue;
            }

            $activities[(string) $id] = $this->formatActivityInfo($id, $value);
        }

        if ($activities === []) {
            return null;
        }

        $checkedAt = null;
        if (isset($cached['checkedAt']) && is_numeric($cached['checkedAt'])) {
            $checkedAt = (int) $cached['checkedAt'];
        }

        $hash = null;
        if (isset($cached['hash']) && is_string($cached['hash'])) {
            $hash = $cached['hash'];
        }

        return [
            'activities' => $activities,
            'checkedAt' => $checkedAt,
            'hash' => $hash,
        ];
    }

    /**
     * @param array<string, mixed> $supplierConfig
     * @param int[] $activityIds
     * @param array<string, mixed>|null $cached
     */
    private function attemptRefresh(
        string $cacheKey,
        array $supplierConfig,
        array $activityIds,
        ?array $cached
    ): ?array {
        $freshActivities = $this->fetchActivities($supplierConfig, $activityIds);
        if ($freshActivities === null) {
            return null;
        }

        if ($freshActivities === null) {
            return $cached;
        }

        $payload = $this->buildCachePayload($freshActivities);
        if ($payload === null) {
            return $cached;
        }

        if ($cached !== null && $cached['hash'] !== null && $payload['hash'] === $cached['hash']) {
            $updated = [
                'activities' => $cached['activities'],
                'checkedAt' => $payload['checkedAt'],
                'hash' => $cached['hash'],
            ];
            $this->cache->set($cacheKey, $updated);
            return $updated;
        }

        $this->cache->set($cacheKey, $payload);
        return $payload;
    }

    /**
     * @param array<string, mixed> $activityConfig
     * @return int[]
     */
    private function extractActivityIds(array $activityConfig): array
    {
        $candidates = UtilityService::getActivityIds($activityConfig);
        $primary = UtilityService::getPrimaryActivityId($activityConfig);
        if ($primary !== null) {
            $candidates[] = $primary;
        }

        $normalized = [];
        foreach ($candidates as $id) {
            if (is_numeric($id)) {
                $normalized[] = (int) $id;
            } elseif (is_string($id) && ctype_digit($id)) {
                $normalized[] = (int) $id;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }

    /**
     * @param array<int, array<string, mixed>>|null $activities
     */
    private function buildCachePayload(?array $activities): ?array
    {
        $activities = $activities ?? [];
        $normalized = [];

        foreach ($activities as $id => $activity) {
            if (!is_array($activity)) {
                continue;
            }

            $normalized[(string) $id] = $this->formatActivityInfo((int) $id, $activity);
        }

        if ($normalized === []) {
            return null;
        }

        return [
            'activities' => $normalized,
            'checkedAt' => time(),
            'hash' => $this->hashActivities($normalized),
        ];
    }

    /**
     * @param array<string, mixed> $supplierConfig
     * @param int[] $activityIds
     * @return array<int, array<string, mixed>>|null
     */
    private function fetchActivities(array $supplierConfig, array $activityIds): ?array
    {
        try {
            $client = $this->soapClientBuilder->build();
        } catch (Throwable) {
            return null;
        }

        $activities = [];

        foreach ($activityIds as $activityId) {
            $payload = [
                'serviceLogin' => [
                    'username' => $supplierConfig['soapCredentials']['username'] ?? '',
                    'password' => $supplierConfig['soapCredentials']['password'] ?? '',
                ],
                'supplierId' => isset($supplierConfig['supplierId']) ? (int) $supplierConfig['supplierId'] : null,
                'activityId' => $activityId,
            ];

            try {
                $response = $client->__soapCall('getActivity', [$payload]);
            } catch (SoapFault) {
                continue;
            }

            $data = $this->normalizeActivityResponse($response);
            if ($data === null) {
                continue;
            }

            $activities[$activityId] = $data;
        }

        return $activities;
    }

    private function shouldRefresh(?int $checkedAt): bool
    {
        if ($checkedAt === null) {
            return true;
        }

        if ($this->refreshInterval <= 0) {
            return true;
        }

        return (time() - $checkedAt) >= $this->refreshInterval;
    }

    private function hashActivities(array $activities): string
    {
        try {
            return hash('sha256', json_encode($activities, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
        } catch (JsonException) {
            return hash('sha256', serialize($activities));
        }
    }

    private function normalizeActivityResponse(mixed $response): ?array
    {
        if (is_object($response) && property_exists($response, 'return')) {
            $response = $response->return;
        }

        if ($response instanceof SoapClient) {
            return null;
        }

        if (is_object($response)) {
            try {
                $response = json_decode(json_encode($response, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                return null;
            }
        }

        if (!is_array($response)) {
            return null;
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function formatActivityInfo(int $activityId, array $payload): array
    {
        return [
            'id' => $activityId,
            'name' => $this->extractString($payload, ['name', 'activityName', 'title']),
            'island' => $this->extractString($payload, ['island']),
            'times' => $this->extractString($payload, ['times', 'timesLabel', 'time', 'timeLabel']),
            'startTimeMinutes' => $this->extractInt($payload, ['startTimeMinutes', 'starttime']),
            'transportationMandatory' => $this->extractBool($payload, ['transportationMandatory', 'transportation_required']),
            'description' => $this->extractString($payload, ['description', 'details']),
            'notes' => $this->extractString($payload, ['notes', 'additionalNotes']),
            'directions' => $this->extractString($payload, ['directions', 'directionsInfo']),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[] $keys
     */
    private function extractString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if ($value === null) {
                continue;
            }

            if (is_string($value)) {
                $trimmed = trim($value);
                if ($trimmed === '') {
                    continue;
                }
                return $trimmed;
            }

            if (is_numeric($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[] $keys
     */
    private function extractInt(array $payload, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_int($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return (int) $value;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $payload
     * @param string[] $keys
     */
    private function extractBool(array $payload, array $keys): ?bool
    {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $payload)) {
                continue;
            }

            $value = $payload[$key];
            if (is_bool($value)) {
                return $value;
            }

            if (is_numeric($value)) {
                return ((int) $value) === 1;
            }

            if (is_string($value)) {
                $normalized = strtolower(trim($value));
                if ($normalized === '') {
                    continue;
                }

                if (in_array($normalized, ['1', 'true', 'yes', 'y', 'required'], true)) {
                    return true;
                }

                if (in_array($normalized, ['0', 'false', 'no', 'n'], true)) {
                    return false;
                }
            }
        }

        return null;
    }

    /**
     * @param mixed $value
     * @param int[] $activityIds
     */
    private function resolveActivityIdFromCacheKey(mixed $key, mixed $value, array $activityIds): ?int
    {
        if (is_numeric($key)) {
            $candidate = (int) $key;
            if (in_array($candidate, $activityIds, true)) {
                return $candidate;
            }
        }

        if (is_array($value) && isset($value['id']) && is_numeric($value['id'])) {
            return (int) $value['id'];
        }

        foreach ($activityIds as $id) {
            if ((string) $key === (string) $id) {
                return $id;
            }
        }

        return null;
    }
}
