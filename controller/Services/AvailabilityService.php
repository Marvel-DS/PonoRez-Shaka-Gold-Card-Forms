<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\Cache\CacheKeyGenerator;
use PonoRez\SGCForms\DTO\AvailabilityCalendar;
use PonoRez\SGCForms\DTO\AvailabilityDay;
use PonoRez\SGCForms\DTO\Timeslot;
use PonoRez\SGCForms\UtilityService;
use SoapFault;

final class AvailabilityService
{
    private const CAL_CACHE_TTL = 300;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly SoapClientFactory $soapClientBuilder
    ) {
    }

    public function fetchCalendar(string $supplierSlug, string $activitySlug, string $startDate): array
    {
        $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
        $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);

        $cacheKey = CacheKeyGenerator::fromParts('availability', $supplierSlug, $activitySlug, $startDate);
        $cached = $this->cache->get($cacheKey);
        if (is_array($cached)) {
            return $this->hydrateFromArray($cached);
        }

        $calendar = $this->buildCalendar($supplierConfig, $activityConfig, $startDate);

        $payload = [
            'calendar' => $calendar['calendar']->toArray(),
            'timeslots' => array_map(static fn (Timeslot $slot) => $slot->toArray(), $calendar['timeslots']),
            'metadata' => $calendar['metadata'],
        ];

        $this->cache->set($cacheKey, $payload, self::CAL_CACHE_TTL);

        return $calendar;
    }

    private function hydrateFromArray(array $data): array
    {
        $calendar = new AvailabilityCalendar();
        foreach ($data['calendar'] ?? [] as $row) {
            if (isset($row['date'], $row['status'])) {
                $calendar->addDay(new AvailabilityDay($row['date'], $row['status']));
            }
        }

        $timeslots = [];
        foreach ($data['timeslots'] ?? [] as $row) {
            if (isset($row['id'], $row['label'])) {
                $timeslots[] = new Timeslot($row['id'], $row['label'], $row['available'] ?? null);
            }
        }

        return [
            'calendar' => $calendar,
            'timeslots' => $timeslots,
            'metadata' => $data['metadata'] ?? [],
        ];
    }

    private function buildCalendar(array $supplierConfig, array $activityConfig, string $startDate): array
    {
        $calendar = new AvailabilityCalendar();
        $timeslots = [];
        $metadata = [
            'fallback' => false,
            'source' => 'soap',
        ];

        try {
            $client = $this->soapClientBuilder->build();
            $params = [
                'serviceLogin' => [
                    'username' => $supplierConfig['soapCredentials']['username'],
                    'password' => $supplierConfig['soapCredentials']['password'],
                ],
                'supplierId' => $supplierConfig['supplierId'],
                'activityId' => $activityConfig['activityId'],
            ];
            if ($startDate !== '') {
                $params['startDate'] = $startDate;
            }

            $response = $client->__soapCall('getActivityAvailableDates', [$params]);
            $rows = $response->return ?? [];
            if (is_object($rows)) {
                $rows = [$rows];
            }
            if (is_array($rows)) {
                foreach ($rows as $row) {
                    if (is_object($row)) {
                        $row = json_decode(json_encode($row), true) ?: [];
                    }
                    if (!isset($row['date'])) {
                        continue;
                    }
                    $status = strtolower((string) ($row['status'] ?? 'available'));
                    $calendar->addDay(new AvailabilityDay((string) $row['date'], $status));
                }
            }
        } catch (SoapFault $exception) {
            $this->seedFallbackCalendar($calendar, $startDate);
            $metadata['fallback'] = true;
            $metadata['source'] = 'fallback';
            $metadata['error'] = $exception->getMessage();
            if (isset($_GET['debug']) && $_GET['debug'] === '1') {
                throw $exception;
            }
        }

        return [
            'calendar' => $calendar,
            'timeslots' => $timeslots,
            'metadata' => $metadata,
        ];
    }

    private function seedFallbackCalendar(AvailabilityCalendar $calendar, string $startDate): void
    {
        try {
            $start = new DateTimeImmutable($startDate);
        } catch (\Exception) {
            $start = new DateTimeImmutable();
        }

        $period = new DatePeriod($start, new DateInterval('P1D'), 14);
        foreach ($period as $date) {
            $calendar->addDay(new AvailabilityDay($date->format('Y-m-d'), 'unknown'));
        }
    }
}
