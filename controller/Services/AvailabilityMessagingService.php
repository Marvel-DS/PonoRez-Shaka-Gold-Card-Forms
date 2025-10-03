<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use DateTimeImmutable;
use PonoRez\SGCForms\UtilityService;
use RuntimeException;
use SoapClient;
use SoapFault;
use stdClass;
use Throwable;

final class AvailabilityMessagingService
{
    private const PROBE_OFFSETS = [3, 2, 1];

    public function __construct(private readonly SoapClientFactory $soapClientBuilder)
    {
    }

    /**
     * @param array<int|string, int> $guestCounts
     * @param array<int, array{activityId:int|string,date:string}>|array<int, array<string, mixed>>|array<int, mixed> $timeslotRequests
     *
     * @return array{requestedSeats:int,messages:list<array{activityId:string,date:string,tier:string,seats:?int}>}
     */
    public function probeTimeslots(
        string $supplierSlug,
        string $activitySlug,
        array $timeslotRequests,
        array $guestCounts = []
    ): array {
        $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
        $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);

        $requestedSeats = $this->calculateRequestedSeats($guestCounts, $activityConfig);
        $activityIds = $this->extractActivityIds($activityConfig);
        $normalizedRequests = $this->normalizeTimeslotRequests($timeslotRequests, $activityIds);

        if ($normalizedRequests === []) {
            return [
                'requestedSeats' => $requestedSeats,
                'messages' => [],
            ];
        }

        try {
            $client = $this->soapClientBuilder->build();
        } catch (Throwable $exception) {
            throw new RuntimeException('Unable to build SOAP client for availability probing.', 0, $exception);
        }

        $results = [];
        $cache = [];

        foreach ($normalizedRequests as $request) {
            $ponorezActivityId = $request['ponorezActivityId'];
            $cacheKey = $this->buildCacheKey($ponorezActivityId, $request['date'], $requestedSeats);
            if (isset($cache[$cacheKey])) {
                $cachedPayload = $cache[$cacheKey];
                $clonedPayload = $cachedPayload;
                $clonedPayload['activityId'] = $request['activityId'];
                $results[] = $clonedPayload;
                continue;
            }

            $baseline = $this->callCheckActivityAvailability(
                $client,
                $supplierConfig,
                $ponorezActivityId,
                $request['date'],
                $requestedSeats
            );

            if ($baseline !== true) {
                $message = [
                    'activityId' => $request['activityId'],
                    'date' => $request['date'],
                    'tier' => 'unavailable',
                    'seats' => 0,
                ];
                $cachePayload = $message;
                $cachePayload['activityId'] = '';
                $cache[$cacheKey] = $cachePayload;
                $results[] = $message;
                continue;
            }

            $message = $this->probeAdditionalAvailability(
                $client,
                $supplierConfig,
                $ponorezActivityId,
                $request['date'],
                $requestedSeats,
                $request['activityId']
            );

            $cachePayload = $message;
            $cachePayload['activityId'] = '';
            $cache[$cacheKey] = $cachePayload;
            $results[] = $message;
        }

        return [
            'requestedSeats' => $requestedSeats,
            'messages' => $results,
        ];
    }

    private function buildCacheKey(int $activityId, string $date, int $requestedSeats): string
    {
        return sprintf('%s|%d|%d', $date, $activityId, $requestedSeats);
    }

    /**
     * @param array<string, mixed> $activityConfig
     */
    private function calculateRequestedSeats(array $guestCounts, array $activityConfig): int
    {
        $total = 0;
        foreach ($guestCounts as $count) {
            $total += max(0, (int) $count);
        }

        if ($total > 0) {
            return $total;
        }

        $minimums = 0;
        foreach ($activityConfig['minGuestCount'] ?? [] as $min) {
            $minimums += max(0, (int) $min);
        }

        return max($minimums, 1);
    }

    /**
     * @param array<int|string, int> $activityIds
     * @return list<array{activityId:string,ponorezActivityId:int,date:string}>
     */
    private function normalizeTimeslotRequests(array $timeslotRequests, array $activityIds): array
    {
        $allowed = $activityIds !== [] ? array_flip($activityIds) : null;

        $normalized = [];

        foreach ($timeslotRequests as $entry) {
            if (is_array($entry)) {
                $activityId = $entry['activityId'] ?? $entry['activityid'] ?? $entry['id'] ?? null;
                $date = $entry['date'] ?? null;
            } else {
                $activityId = null;
                $date = null;
            }

            $resolvedActivity = $this->resolveActivityIdentifier($activityId);
            if ($resolvedActivity === null) {
                continue;
            }

            [$identifier, $normalizedId] = $resolvedActivity;

            if ($allowed !== null && !isset($allowed[$normalizedId])) {
                continue;
            }

            $normalizedDate = $this->normalizeIsoDate($date);
            if ($normalizedDate === null) {
                continue;
            }

            $normalized[] = [
                'activityId' => $identifier,
                'ponorezActivityId' => $normalizedId,
                'date' => $normalizedDate,
            ];
        }

        return $normalized;
    }

    /**
     * @return array{0:string,1:int}|null
     */
    private function resolveActivityIdentifier(mixed $activityId): ?array
    {
        if (is_int($activityId)) {
            return [(string) $activityId, $activityId];
        }

        if (!is_string($activityId)) {
            return null;
        }

        $trimmed = trim($activityId);
        if ($trimmed === '') {
            return null;
        }

        if (preg_match('/^-?\d+$/', $trimmed) === 1) {
            return [$activityId, (int) $trimmed];
        }

        if (preg_match('/^timeslot-(\d+)$/i', $trimmed, $matches) === 1) {
            return [$activityId, (int) $matches[1]];
        }

        return null;
    }

    private function normalizeIsoDate(mixed $date): ?string
    {
        if (!is_string($date) || trim($date) === '') {
            return null;
        }

        try {
            $parsed = new DateTimeImmutable($date);
        } catch (\Exception) {
            return null;
        }

        return $parsed->format('Y-m-d');
    }

    /**
     * @param array<string, mixed> $supplierConfig
     */
    private function callCheckActivityAvailability(
        SoapClient $client,
        array $supplierConfig,
        int $activityId,
        string $isoDate,
        int $requestedSeats
    ): ?bool {
        $payload = [
            'serviceLogin' => [
                'username' => $supplierConfig['soapCredentials']['username'] ?? '',
                'password' => $supplierConfig['soapCredentials']['password'] ?? '',
            ],
            'activityId' => $activityId,
            'date' => $this->formatSoapDate($isoDate),
            'requestedAvailability' => max(0, $requestedSeats),
        ];

        if (isset($supplierConfig['supplierId'])) {
            $payload['supplierId'] = (int) $supplierConfig['supplierId'];
        }

        try {
            $response = $client->__soapCall('checkActivityAvailability', [$payload]);
        } catch (SoapFault) {
            return null;
        }

        return $this->normalizeBooleanResponse($response);
    }

    private function probeAdditionalAvailability(
        SoapClient $client,
        array $supplierConfig,
        int $activityId,
        string $isoDate,
        int $requestedSeats,
        string $responseActivityId
    ): array {
        $confirmedSeats = $requestedSeats;
        $tier = 'limited';

        foreach (self::PROBE_OFFSETS as $offset) {
            $probeSeats = $requestedSeats + $offset;
            $available = $this->callCheckActivityAvailability(
                $client,
                $supplierConfig,
                $activityId,
                $isoDate,
                $probeSeats
            );

            if ($available === true) {
                $confirmedSeats = $probeSeats;
                $tier = $offset >= 2 ? 'plenty' : 'limited';
                break;
            }
        }

        return [
            'activityId' => $responseActivityId,
            'date' => $isoDate,
            'tier' => $tier,
            'seats' => $confirmedSeats,
        ];
    }

    private function normalizeBooleanResponse(mixed $response): ?bool
    {
        if (is_bool($response)) {
            return $response;
        }

        if ($response instanceof stdClass) {
            if (property_exists($response, 'return')) {
                return $this->normalizeBooleanResponse($response->return);
            }

            $decoded = json_decode(json_encode($response), true);
            $response = is_array($decoded) ? $decoded : (array) $response;
        }

        if (is_array($response)) {
            if (array_key_exists('return', $response)) {
                return $this->normalizeBooleanResponse($response['return']);
            }

            $booleanKeys = [
                'available' => true,
                'availability' => true,
                'isavailable' => true,
                'success' => true,
            ];

            foreach ($response as $key => $value) {
                if (is_string($key) && isset($booleanKeys[strtolower($key)])) {
                    $normalized = $this->normalizeBooleanResponse($value);
                    if ($normalized !== null) {
                        return $normalized;
                    }
                }
            }

            foreach ($response as $value) {
                if ($value === $response) {
                    continue;
                }

                $normalized = $this->normalizeBooleanResponse($value);
                if ($normalized !== null) {
                    return $normalized;
                }
            }
        }

        if (is_string($response)) {
            $normalized = strtolower(trim($response));
            if ($normalized === '') {
                return null;
            }

            if (in_array($normalized, ['1', 'true', 't', 'y', 'yes', 'available'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'f', 'n', 'no', 'unavailable'], true)) {
                return false;
            }
        }

        if (is_numeric($response)) {
            return (float) $response > 0.0;
        }

        return null;
    }

    private function formatSoapDate(string $isoDate): string
    {
        try {
            return (new DateTimeImmutable($isoDate))->format('m/d/Y');
        } catch (\Exception) {
            return $isoDate;
        }
    }

    /**
     * @param array<string, mixed> $activityConfig
     * @return array<int, int>
     */
    private function extractActivityIds(array $activityConfig): array
    {
        $ids = [];

        if (isset($activityConfig['activityId'])) {
            $ids[] = $activityConfig['activityId'];
        }

        if (isset($activityConfig['activityIds']) && is_array($activityConfig['activityIds'])) {
            foreach ($activityConfig['activityIds'] as $id) {
                $ids[] = $id;
            }
        }

        $normalized = [];
        foreach ($ids as $id) {
            if (is_numeric($id)) {
                $normalized[] = (int) $id;
            }
        }

        $normalized = array_values(array_unique($normalized));
        sort($normalized);

        return $normalized;
    }
}
