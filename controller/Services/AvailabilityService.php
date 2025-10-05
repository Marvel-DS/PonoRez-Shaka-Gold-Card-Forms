<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use JsonException;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\Cache\CacheKeyGenerator;
use PonoRez\SGCForms\Cache\NullCache;
use PonoRez\SGCForms\DTO\AvailabilityCalendar;
use PonoRez\SGCForms\DTO\AvailabilityDay;
use PonoRez\SGCForms\DTO\Timeslot;
use PonoRez\SGCForms\UtilityService;
use RuntimeException;
use SoapClient;
use SoapFault;
use stdClass;

final class AvailabilityService
{
    private const LIMITED_THRESHOLD = 5;
    private const CACHE_TTL = 180; // 3 minutes
    private const CACHE_WINDOW_MONTHS = 6;
    private const CACHE_KEY_PREFIX = 'availability-months';
    private const CACHE_KEY_VERSION = 'v1';

    /** @var callable */
    private $httpFetcher;
    private bool $certificateVerificationDisabled = false;
    private CacheInterface $cache;

    public function __construct(
        private readonly SoapClientFactory $soapClientBuilder,
        ?callable $httpFetcher = null,
        ?CacheInterface $cache = null
    ) {
        $this->httpFetcher = $httpFetcher ?? [$this, 'defaultHttpFetch'];
        $this->cache = $cache ?? new NullCache();
    }

    /**
     * @param array<int|string,int> $guestCounts
     * @param array<int,int|string>|null $activityIds
     * @return array{calendar:AvailabilityCalendar,timeslots:Timeslot[],metadata:array<string,mixed>}
     */
    public function fetchCalendar(
        string $supplierSlug,
        string $activitySlug,
        string $selectedDate,
        array $guestCounts = [],
        ?array $activityIds = null,
        ?string $visibleMonth = null
    ): array {
        $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
        $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);

        $this->certificateVerificationDisabled = false;

        if ($activityIds === null || $activityIds === []) {
            $activityIds = $activityConfig['activityIds'] ?? [];
        }

        $activityIds = array_values(array_map('intval', array_filter(
            $activityIds,
            static fn ($value) => $value !== null && $value !== ''
        )));

        if ($activityIds === []) {
            throw new RuntimeException('Unable to determine activity IDs for availability lookup.');
        }

        $selectedDay = $this->createDateImmutable($selectedDate);
        $viewMonthStart = $this->resolveMonthStart($visibleMonth, $selectedDay);

        $requestedSeats = $this->calculateRequestedSeats($guestCounts, $activityConfig);

        $monthsToLoad = [$viewMonthStart->format('Y-m') => $viewMonthStart];
        $selectedMonthKey = $selectedDay->format('Y-m');
        if (!isset($monthsToLoad[$selectedMonthKey])) {
            $monthsToLoad[$selectedMonthKey] = $selectedDay->modify('first day of this month');
        }

        $monthData = [];
        $normalizedExtended = [];
        foreach ($monthsToLoad as $monthKey => $monthStart) {
            $monthData[$monthKey] = $this->fetchMonthAvailabilityData(
                $supplierSlug,
                $activitySlug,
                $supplierConfig,
                $activityIds,
                $guestCounts,
                (int) $monthStart->format('Y'),
                (int) $monthStart->format('n'),
                $requestedSeats
            );

            $normalizedExtended[$monthKey] = $this->normalizeMonthExtendedData(
                $monthData[$monthKey],
                $requestedSeats
            );
        }

        $calendar = new AvailabilityCalendar();
        $firstAvailableDate = null;
        $viewMonthKey = $viewMonthStart->format('Y-m');
        $viewMonthData = $monthData[$viewMonthKey];
        $viewMonthExtended = $normalizedExtended[$viewMonthKey] ?? [];

        $monthEnd = $viewMonthStart->modify('last day of this month');
        $period = new DatePeriod($viewMonthStart, new DateInterval('P1D'), $monthEnd->modify('+1 day'));

        foreach ($period as $date) {
            $day = (int) $date->format('j');
            [$status] = $this->resolveDayStatus(
                $viewMonthData,
                $viewMonthExtended,
                $day,
                $activityIds,
                $requestedSeats
            );

            $calendar->addDay(new AvailabilityDay($date->format('Y-m-d'), $status));

            if ($firstAvailableDate === null && in_array($status, ['available', 'limited'], true)) {
                $firstAvailableDate = $date->format('Y-m-d');
            }
        }

        $selectedMonthData = $monthData[$selectedMonthKey] ?? null;
        $selectedMonthExtended = $normalizedExtended[$selectedMonthKey] ?? [];
        $selectedDayStatus = 'sold_out';
        if ($selectedMonthData !== null) {
            [$selectedDayStatus] = $this->resolveDayStatus(
                $selectedMonthData,
                $selectedMonthExtended,
                (int) $selectedDay->format('j'),
                $activityIds,
                $requestedSeats
            );
        }

        if ($firstAvailableDate === null) {
            $searchStart = $viewMonthStart->modify('first day of next month');

            for ($offset = 0; $offset < 6 && $firstAvailableDate === null; $offset += 1) {
                $year = (int) $searchStart->format('Y');
                $month = (int) $searchStart->format('n');
                $monthKey = $searchStart->format('Y-m');

                $monthData[$monthKey] = $this->fetchMonthAvailabilityData(
                    $supplierSlug,
                    $activitySlug,
                    $supplierConfig,
                    $activityIds,
                    $guestCounts,
                    $year,
                    $month,
                    $requestedSeats
                );

                $normalizedExtended[$monthKey] = $this->normalizeMonthExtendedData(
                    $monthData[$monthKey],
                    $requestedSeats
                );

                $monthEnd = $searchStart->modify('last day of this month');
                $period = new DatePeriod($searchStart, new DateInterval('P1D'), $monthEnd->modify('+1 day'));

                foreach ($period as $date) {
                    [$status] = $this->resolveDayStatus(
                        $monthData[$monthKey],
                        $normalizedExtended[$monthKey] ?? [],
                        (int) $date->format('j'),
                        $activityIds,
                        $requestedSeats
                    );

                    if (in_array($status, ['available', 'limited'], true)) {
                        $firstAvailableDate = $date->format('Y-m-d');
                        break;
                    }
                }

                $searchStart = $searchStart->modify('first day of next month');
            }
        }

        $extendedAvailability = $this->buildExtendedAvailabilityIndex($normalizedExtended);
        $selectedDateKey = $selectedDay->format('Y-m-d');
        $selectedExtendedEntry = $extendedAvailability[$selectedDateKey] ?? null;
        $availableActivityIdsForSelectedDate = [];
        if (is_array($selectedExtendedEntry)) {
            $availableActivityIdsForSelectedDate = $selectedExtendedEntry['availableActivityIds']
                ?? $selectedExtendedEntry['activityIds']
                ?? [];
        }

        $timeslots = [];
        $shouldFetchTimeslotsFromSoap = true;

        if (is_array($selectedExtendedEntry)) {
            if ($availableActivityIdsForSelectedDate === []) {
                $shouldFetchTimeslotsFromSoap = false;
            } else {
                $syntheticTimeslots = $this->buildTimeslotsFromExtendedEntry(
                    $selectedExtendedEntry,
                    $activityIds,
                    $requestedSeats
                );

                if ($syntheticTimeslots !== null) {
                    $timeslots = $syntheticTimeslots;
                    $shouldFetchTimeslotsFromSoap = false;
                }
            }
        }

        if ($shouldFetchTimeslotsFromSoap) {
            $timeslots = $this->fetchTimeslotsForDate(
                $this->soapClientBuilder->build(),
                $supplierConfig,
                $activityIds,
                $selectedDay->format('Y-m-d'),
                $guestCounts,
                $requestedSeats
            );
        }

        $timeslotStatus = 'available';
        if ($timeslots === []) {
            $timeslotStatus = $availableActivityIdsForSelectedDate === [] ? 'unavailable' : 'available';
        } elseif ($timeslots !== []) {
            $hasSelectableTimeslot = false;
            foreach ($timeslots as $timeslot) {
                $available = $timeslot->getAvailable();
                if ($available === null || $available > 0) {
                    $hasSelectableTimeslot = true;
                    break;
                }
            }

            if (!$hasSelectableTimeslot) {
                $timeslotStatus = 'unavailable';
            }
        }

        return [
            'calendar' => $calendar,
            'timeslots' => $timeslots,
            'metadata' => array_filter([
                'requestedSeats' => $requestedSeats,
                'firstAvailableDate' => $firstAvailableDate,
                'timeslotStatus' => $timeslotStatus,
                'selectedDateStatus' => $selectedDayStatus,
                'month' => $viewMonthKey,
                'certificateVerification' => $this->certificateVerificationDisabled ? 'disabled' : 'verified',
                'extended' => $extendedAvailability,
            ], static fn ($value) => $value !== null),
        ];
    }

    private function buildExtendedAvailabilityIndex(array $normalizedExtendedByMonth): array
    {
        $index = [];

        foreach ($normalizedExtendedByMonth as $monthKey => $entries) {
            if (!is_array($entries) || $entries === []) {
                continue;
            }

            try {
                $monthDate = new DateTimeImmutable($monthKey . '-01');
            } catch (\Exception) {
                continue;
            }

            $year = (int) $monthDate->format('Y');
            $month = (int) $monthDate->format('m');

            foreach ($entries as $dayKey => $entry) {
                if (preg_match('/^d(\d{1,2})$/', (string) $dayKey, $matches) !== 1) {
                    continue;
                }

                $day = (int) $matches[1];

                $indexKey = sprintf('%04d-%02d-%02d', $year, $month, $day);

                $index[$indexKey] = $entry;
            }
        }

        return $index;
    }

    private function normalizeMonthExtendedData(array $monthData, int $requestedSeats): array
    {
        if (!isset($monthData['extended']) || !is_array($monthData['extended'])) {
            return [];
        }

        $normalized = [];

        foreach ($monthData['extended'] as $dayKey => $entry) {
            if (preg_match('/^d(\d{1,2})$/', (string) $dayKey) !== 1) {
                continue;
            }

            $normalized[$dayKey] = $this->normalizeExtendedAvailabilityEntry($entry, $requestedSeats);
        }

        return $normalized;
    }

    /**
     * @param array<string,mixed> $entry
     * @param int[]               $requestedActivityIds
     * @param int                 $requestedSeats
     *
     * @return Timeslot[]|null
     */
    private function buildTimeslotsFromExtendedEntry(array $entry, array $requestedActivityIds, int $requestedSeats): ?array
    {
        if (!isset($entry['times']) || !is_array($entry['times']) || $entry['times'] === []) {
            return null;
        }

        $times = [];
        foreach ($entry['times'] as $activityId => $label) {
            $normalizedId = null;
            if (is_int($activityId)) {
                $normalizedId = $activityId;
            } elseif (is_string($activityId) && preg_match('/^\d+$/', $activityId) === 1) {
                $normalizedId = (int) $activityId;
            }

            if ($normalizedId === null) {
                continue;
            }

            $stringLabel = $this->stringifyTimeslotDetailValue($label);
            if ($stringLabel === null) {
                continue;
            }

            $times[$normalizedId] = $stringLabel;
        }

        if ($times === []) {
            return null;
        }

        $activitiesById = [];
        if (isset($entry['activities']) && is_array($entry['activities'])) {
            foreach ($entry['activities'] as $activity) {
                if (!is_array($activity)) {
                    continue;
                }

                $activityId = $activity['activityId'] ?? null;
                if (!is_int($activityId) || isset($activitiesById[$activityId])) {
                    continue;
                }

                $activitiesById[$activityId] = $activity;
            }
        }

        $orderedIds = [];
        if ($requestedActivityIds !== []) {
            foreach ($requestedActivityIds as $requestedId) {
                if (isset($times[$requestedId])) {
                    $orderedIds[] = $requestedId;
                }
            }
        }

        foreach (array_keys($times) as $activityId) {
            if (!in_array($activityId, $orderedIds, true)) {
                $orderedIds[] = $activityId;
            }
        }

        $availableActivityIds = [];
        if (isset($entry['availableActivityIds']) && is_array($entry['availableActivityIds'])) {
            $availableActivityIds = array_values(array_map('intval', $entry['availableActivityIds']));
        }

        $timeslotList = [];
        foreach ($orderedIds as $activityId) {
            if ($availableActivityIds !== [] && !in_array($activityId, $availableActivityIds, true)) {
                continue;
            }

            $label = $times[$activityId];
            $activity = $activitiesById[$activityId] ?? null;
            $details = [];

            if (isset($activity['details']) && is_array($activity['details'])) {
                $details = $activity['details'];
            }

            if (!array_key_exists('times', $details)) {
                $details['times'] = $label;
            }

            $available = null;
            if (is_array($activity)) {
                $capacity = $this->extractActivityCapacity($activity);
                if ($capacity !== null) {
                    $available = $capacity;
                }

                if ($requestedSeats > 0 && $capacity !== null && $capacity < $requestedSeats) {
                    continue;
                }

                if (array_key_exists('available', $activity) && $activity['available'] === false) {
                    $available = 0;
                }
            }

            $timeslotList[] = new Timeslot((string) $activityId, $label, $available, $details);
        }

        if ($timeslotList === []) {
            return null;
        }

        return $this->sortTimeslots($timeslotList);
    }

    private function normalizeExtendedAvailabilityEntry(mixed $entry, int $requestedSeats = 0): array
    {
        if ($entry instanceof stdClass) {
            $entry = (array) $entry;
        }

        if (!is_array($entry)) {
            return [
                'activityIds' => [],
                'availableActivityIds' => [],
                'activities' => [],
            ];
        }

        $activityIds = $this->extractExtendedActivityIds($entry);
        $activities = $this->extractExtendedActivities($entry, $activityIds);
        $times = $this->extractExtendedTimes($entry, $activityIds, $activities);

        if ($times !== []) {
            $activities = $this->mergeExtendedActivitiesWithTimes($activities, $times, $activityIds);
        }

        $availableActivityIds = $this->resolveAvailableActivityIds($activityIds, $activities, $requestedSeats);

        $normalized = [
            'activityIds' => $activityIds,
            'availableActivityIds' => $availableActivityIds,
            'activities' => $activities,
        ];

        if ($times !== []) {
            $normalized['times'] = $times;
        }

        return $normalized;
    }

    /**
     * @param int[]                          $activityIds
     * @param array<int,array<string,mixed>> $activities
     *
     * @return int[]
     */
    private function resolveAvailableActivityIds(array $activityIds, array $activities, int $requestedSeats): array
    {
        if ($activities === []) {
            return $activityIds;
        }

        $availableSet = [];
        $availableOrder = [];

        foreach ($activities as $activity) {
            if (!is_array($activity)) {
                continue;
            }

            $activityId = $activity['activityId'] ?? null;
            if (!is_int($activityId)) {
                continue;
            }

            if (array_key_exists('available', $activity) && $activity['available'] === false) {
                continue;
            }

            if ($requestedSeats > 0) {
                $capacity = $this->extractActivityCapacity($activity);
                if ($capacity !== null && $capacity < $requestedSeats) {
                    continue;
                }
            }

            if (!array_key_exists($activityId, $availableSet)) {
                $availableSet[$activityId] = true;
                $availableOrder[] = $activityId;
            }
        }

        if ($availableSet === []) {
            return [];
        }

        $ordered = [];

        if ($activityIds !== []) {
            foreach ($activityIds as $id) {
                if (array_key_exists($id, $availableSet) && !in_array($id, $ordered, true)) {
                    $ordered[] = $id;
                }
            }
        }

        if ($ordered === []) {
            foreach ($availableOrder as $id) {
                if (!in_array($id, $ordered, true)) {
                    $ordered[] = $id;
                }
            }
        }

        return $ordered;
    }

    /**
     * @param array<string, mixed> $activity
     */
    private function extractActivityCapacity(array $activity): ?int
    {
        $keys = ['availableSeats', 'availableSpots', 'remaining', 'availableCount', 'capacity', 'available'];

        foreach ($keys as $key) {
            if (!array_key_exists($key, $activity)) {
                continue;
            }

            $value = $activity[$key];
            $numeric = $this->normalizeAvailabilityCount($value);
            if ($numeric !== null) {
                return $numeric;
            }
        }

        if (isset($activity['details']) && is_array($activity['details'])) {
            foreach ($keys as $key) {
                if (!array_key_exists($key, $activity['details'])) {
                    continue;
                }

                $numeric = $this->normalizeAvailabilityCount($activity['details'][$key]);
                if ($numeric !== null) {
                    return $numeric;
                }
            }
        }

        return null;
    }

    private function normalizeAvailabilityCount(mixed $value): ?int
    {
        if ($value === null) {
            return null;
        }

        if (is_int($value) || is_float($value)) {
            return max(0, (int) $value);
        }

        if (is_string($value) && is_numeric($value)) {
            return max(0, (int) $value);
        }

        return null;
    }

    private function extractExtendedActivityIds(array $entry): array
    {
        $candidates = [];

        if (array_is_list($entry)) {
            $candidates[] = $entry;
        }

        foreach (['activityIds', 'aids', 'ids'] as $key) {
            if (!array_key_exists($key, $entry)) {
                continue;
            }

            $value = $entry[$key];

            if ($value instanceof stdClass) {
                $value = (array) $value;
            }

            if (!is_array($value)) {
                continue;
            }

            $candidates[] = $value;
        }

        if ($candidates === []) {
            if (isset($entry['activities'])) {
                $activities = $entry['activities'];
                if ($activities instanceof stdClass) {
                    $activities = (array) $activities;
                }

                if (is_array($activities)) {
                    $candidates[] = $activities;
                }
            }

            if ($candidates === []) {
                return [];
            }
        }

        $ids = [];
        foreach ($candidates as $candidate) {
            foreach ($candidate as $value) {
                $id = $this->normalizeExtendedActivityIdValue($value);
                if ($id === null || in_array($id, $ids, true)) {
                    continue;
                }

                $ids[] = $id;
            }
        }

        return $ids;
    }

    private function normalizeExtendedActivityIdValue(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        if ($value instanceof stdClass) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            return null;
        }

        foreach (['activityId', 'activityid', 'id', 'aid', 'departureId', 'departureid', 'departure_id', 'departureID'] as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }

            $candidate = $this->normalizeExtendedActivityIdValue($value[$key]);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed>|list<mixed> $entry
     * @param int[]                            $activityIds
     * @param array<int,array<string,mixed>>   $activities
     *
     * @return array<int,string>
     */
    private function extractExtendedTimes(array $entry, array $activityIds, array $activities): array
    {
        $times = [];

        foreach ($activities as $activity) {
            $activityId = $activity['activityId'] ?? null;
            if (!is_int($activityId) || array_key_exists($activityId, $times)) {
                continue;
            }

            $time = $this->extractTimeValueFromArray($activity);
            if ($time !== null) {
                $times[$activityId] = $time;
            }
        }

        $candidateKeys = [
            'times',
            'time',
            'ponore',
            'ponoreTimes',
            'ponoretimes',
            'ponoreValue',
            'ponorevalue',
            'ponore_values',
            'ponoreValues',
            'departureTimes',
            'departure_times',
            'departures',
            'departureDetails',
            'departuredetails',
            'departure_info',
            'departureInfo',
        ];
        foreach ($candidateKeys as $candidateKey) {
            if (!array_key_exists($candidateKey, $entry)) {
                continue;
            }

            $this->accumulateExtendedTimes($times, $entry[$candidateKey], $activityIds);
        }

        return $times;
    }

    /**
     * @param array<int,string> $times
     * @param int[]             $activityIds
     */
    private function accumulateExtendedTimes(array &$times, mixed $source, array $activityIds): void
    {
        if ($source instanceof stdClass) {
            $source = (array) $source;
        }

        if (is_scalar($source) || $source === null) {
            $string = $this->stringifyTimeslotDetailValue($source);
            if ($string !== null && $activityIds !== []) {
                $firstId = $activityIds[0];
                if (!array_key_exists($firstId, $times)) {
                    $times[$firstId] = $string;
                }
            }

            return;
        }

        if (!is_array($source)) {
            return;
        }

        if (array_is_list($source)) {
            $index = 0;
            foreach ($source as $value) {
                if ($value instanceof stdClass) {
                    $value = (array) $value;
                }

                $id = null;
                $string = null;

                if (is_array($value)) {
                    $id = $this->normalizeExtendedActivityIdValue(
                        $value['activityId']
                        ?? $value['activityid']
                        ?? $value['id']
                        ?? $value['aid']
                        ?? $value['departureId']
                        ?? $value['departureid']
                        ?? $value['departure_id']
                        ?? null
                    );
                    $string = $this->extractTimeValueFromArray($value);
                } else {
                    $string = $this->stringifyTimeslotDetailValue($value);
                }

                if ($string !== null) {
                    $resolvedId = $id ?? ($activityIds[$index] ?? null);
                    if ($resolvedId !== null && !array_key_exists($resolvedId, $times)) {
                        $times[$resolvedId] = $string;
                    }
                }

                $index++;
            }

            return;
        }

        foreach ($source as $key => $value) {
            $id = $this->normalizeExtendedActivityIdValue($key);

            if ($value instanceof stdClass) {
                $value = (array) $value;
            }

            if (is_array($value)) {
                $candidateId = $id ?? $this->normalizeExtendedActivityIdValue(
                    $value['activityId']
                    ?? $value['activityid']
                    ?? $value['id']
                    ?? $value['aid']
                    ?? $value['departureId']
                    ?? $value['departureid']
                    ?? $value['departure_id']
                    ?? null
                );
                $string = $this->extractTimeValueFromArray($value);

                if ($candidateId !== null && $string !== null && !array_key_exists($candidateId, $times)) {
                    $times[$candidateId] = $string;
                }

                continue;
            }

            $string = $this->stringifyTimeslotDetailValue($value);
            if ($id !== null && $string !== null && !array_key_exists($id, $times)) {
                $times[$id] = $string;
            }
        }
    }

    /**
     * @param array<int,array<string,mixed>> $activities
     * @param array<int,string>              $times
     * @param int[]                          $activityIds
     *
     * @return array<int,array<string,mixed>>
     */
    private function mergeExtendedActivitiesWithTimes(array $activities, array $times, array $activityIds): array
    {
        $activitiesById = [];
        foreach ($activities as $activity) {
            $activityId = $activity['activityId'] ?? null;
            if (!is_int($activityId)) {
                continue;
            }

            if (isset($activity['details']) && !is_array($activity['details'])) {
                unset($activity['details']);
            }

            $activitiesById[$activityId] = $activity;
        }

        foreach ($times as $activityId => $label) {
            if (!is_int($activityId)) {
                continue;
            }

            $activity = $activitiesById[$activityId] ?? ['activityId' => $activityId];

            $details = [];
            if (isset($activity['details']) && is_array($activity['details'])) {
                $details = $activity['details'];
            }

            if (!array_key_exists('times', $details)
                || !is_string($details['times'])
                || trim($details['times']) === ''
            ) {
                $details['times'] = $label;
            }

            $activity['details'] = $details;

            if (!isset($activity['activityName']) && $label !== '') {
                $activity['activityName'] = $label;
            }

            $activitiesById[$activityId] = $activity;
        }

        if ($activitiesById === []) {
            return [];
        }

        $orderedIds = $activityIds !== [] ? $activityIds : array_keys($activitiesById);
        $ordered = [];

        foreach ($orderedIds as $activityId) {
            if (!isset($activitiesById[$activityId])) {
                continue;
            }

            $ordered[] = $activitiesById[$activityId];
            unset($activitiesById[$activityId]);
        }

        foreach ($activitiesById as $activity) {
            $ordered[] = $activity;
        }

        return $ordered;
    }

    private function extractTimeValueFromArray(array $value): ?string
    {
        if (isset($value['details'])) {
            $details = $this->normalizeTimeslotDetails($value['details']);
            foreach (['times', 'time', 'departure', 'departureTime', 'checkIn', 'checkin', 'checkintime', 'check_in', 'label', 'name', 'title', 'displayName', 'display'] as $detailKey) {
                if (array_key_exists($detailKey, $details)) {
                    return $details[$detailKey];
                }
            }
        }

        foreach (['times', 'time', 'ponoreValue', 'ponorevalue', 'ponore', 'departure', 'departureTime', 'checkIn', 'checkin', 'checkintime', 'check_in', 'label', 'name', 'title', 'displayName', 'display'] as $key) {
            if (!array_key_exists($key, $value)) {
                continue;
            }

            $candidate = $this->stringifyTimeslotDetailValue($value[$key]);
            if ($candidate !== null) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractExtendedActivities(array $entry, array $activityIds): array
    {
        $sources = [];

        if (array_is_list($entry)) {
            $sources[] = $entry;
        }

        foreach ([
            'activities',
            'activityDetails',
            'activitydetails',
            'activity_info',
            'activityInfo',
            'departures',
            'departureDetails',
            'departuredetails',
            'departure_info',
            'departureInfo',
        ] as $key) {
            if (!array_key_exists($key, $entry)) {
                continue;
            }

            $value = $entry[$key];

            if ($value instanceof stdClass) {
                $value = (array) $value;
            }

            if (!is_array($value)) {
                continue;
            }

            $sources[] = $value;
        }

        if ($sources === []) {
            return [];
        }

        $activities = [];
        foreach ($sources as $source) {
            $this->accumulateExtendedActivities($activities, $source);
        }

        if ($activities === []) {
            return [];
        }

        if ($activityIds !== []) {
            $ordered = [];
            foreach ($activityIds as $id) {
                if (isset($activities[$id])) {
                    $ordered[] = $activities[$id];
                }
            }

            return $ordered;
        }

        return array_values($activities);
    }

    /**
     * @param array<int,array<string,mixed>> $activities
     */
    private function accumulateExtendedActivities(array &$activities, array $source): void
    {
        if (array_is_list($source)) {
            foreach ($source as $value) {
                $this->appendExtendedActivity($activities, $value);
            }

            return;
        }

        foreach ($source as $key => $value) {
            $this->appendExtendedActivity($activities, $value, $key);
        }
    }

    /**
     * @param array<int,array<string,mixed>> $activities
     */
    private function appendExtendedActivity(array &$activities, mixed $value, mixed $key = null): void
    {
        if ($value instanceof stdClass) {
            $value = (array) $value;
        }

        if (!is_array($value)) {
            $id = $this->normalizeExtendedActivityIdValue($value ?? $key);
            if ($id === null || isset($activities[$id])) {
                return;
            }

            $activities[$id] = ['activityId' => $id];

            return;
        }

        $activityId = $this->normalizeExtendedActivityIdValue(
            $value['activityId']
            ?? $value['activityid']
            ?? $value['id']
            ?? $value['aid']
            ?? $value['departureId']
            ?? $value['departureid']
            ?? $value['departure_id']
            ?? $key
        );
        if ($activityId === null) {
            return;
        }

        $activity = $activities[$activityId] ?? ['activityId' => $activityId];

        $name = $value['activityName']
            ?? $value['activityname']
            ?? $value['name']
            ?? $value['label']
            ?? $value['title']
            ?? $value['displayName']
            ?? $value['display']
            ?? null;
        if (is_string($name) && trim($name) !== '') {
            $activity['activityName'] = trim($name);
        }

        $availabilityKeys = ['available', 'isAvailable', 'availableFlag', 'status', 'availability', 'availabilityStatus'];
        foreach ($availabilityKeys as $availabilityKey) {
            if (!array_key_exists($availabilityKey, $value)) {
                continue;
            }

            $available = $this->normalizeAvailabilityFlag($value[$availabilityKey]);
            if ($available !== null) {
                $activity['available'] = $available;
                break;
            }
        }

        $details = $this->normalizeTimeslotDetails($value['details'] ?? null);
        foreach (['times', 'time', 'departure', 'departureTime', 'checkIn', 'checkin', 'checkintime', 'check_in', 'label', 'name', 'title', 'displayName', 'display'] as $detailKey) {
            if (!array_key_exists($detailKey, $value)) {
                continue;
            }

            $stringValue = $this->stringifyTimeslotDetailValue($value[$detailKey]);
            if ($stringValue === null || array_key_exists($detailKey, $details)) {
                continue;
            }

            if (in_array($detailKey, ['label', 'name', 'title', 'displayName', 'display'], true)) {
                if (!array_key_exists('times', $details)) {
                    $details['times'] = $stringValue;
                }

                continue;
            }

            $details[$detailKey] = $stringValue;

            if (in_array($detailKey, ['time', 'departure', 'departureTime'], true) && !array_key_exists('times', $details)) {
                $details['times'] = $stringValue;
            }
        }

        if ($details !== []) {
            $activity['details'] = $details;
        }

        if (!isset($activity['activityName']) && isset($details['times'])) {
            $activity['activityName'] = $details['times'];
        }

        foreach (['availableSeats', 'availableSpots', 'remaining', 'availableCount', 'capacity'] as $capacityKey) {
            if (array_key_exists($capacityKey, $value) && !array_key_exists($capacityKey, $activity)) {
                $activity[$capacityKey] = $value[$capacityKey];
            }
        }

        $activities[$activityId] = $activity;
    }

    private function normalizeAvailabilityFlag(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value > 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            if ($normalized === '') {
                return null;
            }

            $truthy = ['1', 'y', 'yes', 'true', 't', 'available', 'open'];
            if (in_array($normalized, $truthy, true)) {
                return true;
            }

            $falsy = ['0', 'n', 'no', 'false', 'f', 'unavailable', 'closed', 'sold_out', 'soldout'];
            if (in_array($normalized, $falsy, true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * @param array<string,mixed> $supplierConfig
     * @param array<int>          $activityIds
     * @param array<int|string,int> $guestCounts
     */
    private function fetchMonthAvailabilityData(
        string $supplierSlug,
        string $activitySlug,
        array $supplierConfig,
        array $activityIds,
        array $guestCounts,
        int $year,
        int $month,
        int $requestedSeats
    ): array {
        $cacheKey = $this->buildMonthCacheKey(
            $supplierSlug,
            $activitySlug,
            $activityIds,
            $guestCounts,
            $requestedSeats
        );

        $cachePayload = $this->normalizeMonthCachePayload($this->cache->get($cacheKey));
        $monthKey = sprintf('%04d-%02d', $year, $month);

        if (!isset($cachePayload['months'][$monthKey])) {
            $monthStart = $this->createMonthStartDate($year, $month);
            $prefetchMonths = $this->buildCachePrefetchMonths($monthStart);

            $missingMonths = [];
            foreach ($prefetchMonths as $prefetchMonth) {
                $prefetchKey = $prefetchMonth->format('Y-m');
                if (!isset($cachePayload['months'][$prefetchKey])) {
                    $missingMonths[$prefetchKey] = $prefetchMonth;
                }
            }

            if ($missingMonths !== []) {
                $fetchedMonths = $this->fetchMonthAvailabilityDataFromRemote(
                    $supplierConfig,
                    $activityIds,
                    $guestCounts,
                    array_values($missingMonths)
                );

                foreach ($missingMonths as $prefetchKey => $prefetchMonth) {
                    if (isset($fetchedMonths[$prefetchKey])) {
                        $cachePayload['months'][$prefetchKey] = $fetchedMonths[$prefetchKey];
                    } else {
                        $cachePayload['months'][$prefetchKey] = ['seats' => [], 'extended' => []];
                    }
                }

                $cachePayload['months'] = $this->pruneCachedMonths($cachePayload['months']);

                $this->cache->set($cacheKey, $cachePayload, self::CACHE_TTL);
            }
        }

        return $cachePayload['months'][$monthKey] ?? ['seats' => [], 'extended' => []];
    }

    /**
     * @param array<string,mixed>|null $payload
     * @return array{months: array<string, array<string, mixed>>}
     */
    private function normalizeMonthCachePayload(mixed $payload): array
    {
        $normalized = ['months' => []];

        if (!is_array($payload)) {
            return $normalized;
        }

        foreach ($payload['months'] ?? [] as $monthKey => $data) {
            if (!is_string($monthKey) || !is_array($data)) {
                continue;
            }

            $seats = [];
            if (isset($data['seats']) && is_array($data['seats'])) {
                $seats = $data['seats'];
            }

            $extended = [];
            if (isset($data['extended']) && is_array($data['extended'])) {
                $extended = $data['extended'];
            }

            $normalized['months'][$monthKey] = [
                'seats' => $seats,
                'extended' => $extended,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<int> $activityIds
     * @param array<int|string,int> $guestCounts
     */
    private function buildMonthCacheKey(
        string $supplierSlug,
        string $activitySlug,
        array $activityIds,
        array $guestCounts,
        int $requestedSeats
    ): string {
        $activitySignature = $this->buildActivitySignature($activityIds);
        $guestSignature = $this->buildGuestCountsSignature($guestCounts);

        return CacheKeyGenerator::fromParts(
            self::CACHE_KEY_PREFIX,
            self::CACHE_KEY_VERSION,
            $supplierSlug,
            $activitySlug,
            $activitySignature,
            $guestSignature,
            (string) max(0, $requestedSeats)
        );
    }

    /**
     * @param array<int> $activityIds
     */
    private function buildActivitySignature(array $activityIds): string
    {
        if ($activityIds === []) {
            return 'none';
        }

        $normalized = array_values(array_unique(array_map('intval', $activityIds)));
        sort($normalized);

        return implode('-', $normalized);
    }

    /**
     * @param array<int|string,int> $guestCounts
     */
    private function buildGuestCountsSignature(array $guestCounts): string
    {
        if ($guestCounts === []) {
            return 'none';
        }

        ksort($guestCounts);

        $parts = [];
        foreach ($guestCounts as $guestTypeId => $count) {
            $parts[] = sprintf('%s-%d', (string) $guestTypeId, max(0, (int) $count));
        }

        return implode('_', $parts);
    }

    /**
     * @return DateTimeImmutable[]
     */
    private function buildCachePrefetchMonths(DateTimeImmutable $start): array
    {
        $months = [];

        for ($offset = 0; $offset < self::CACHE_WINDOW_MONTHS; $offset++) {
            $months[] = $start->modify(sprintf('+%d months', $offset));
        }

        return $months;
    }

    private function createMonthStartDate(int $year, int $month): DateTimeImmutable
    {
        $clampedMonth = max(1, min(12, $month));
        $formatted = sprintf('%04d-%02d-01', $year, $clampedMonth);

        try {
            return new DateTimeImmutable($formatted);
        } catch (\Exception) {
            return new DateTimeImmutable('first day of this month');
        }
    }

    /**
     * @param array<string, array<string, mixed>> $months
     * @return array<string, array<string, mixed>>
     */
    private function pruneCachedMonths(array $months): array
    {
        if ($months === []) {
            return [];
        }

        ksort($months);

        $limit = self::CACHE_WINDOW_MONTHS * 3; // keep up to 18 months
        if (count($months) <= $limit) {
            return $months;
        }

        return array_slice($months, -$limit, null, true);
    }

    /**
     * @param array<string,mixed> $supplierConfig
     * @param array<int>          $activityIds
     * @param array<int|string,int> $guestCounts
     * @param DateTimeImmutable[] $months
     * @return array<string,array<string,mixed>>
     */
    private function fetchMonthAvailabilityDataFromRemote(
        array $supplierConfig,
        array $activityIds,
        array $guestCounts,
        array $months
    ): array {
        if ($months === []) {
            return [];
        }

        $baseUrl = UtilityService::getReservationBaseUrl();
        $endpoint = rtrim($baseUrl, '/') . '/companyservlet';

        $lookup = [];
        $yearMonthParam = [];
        foreach ($months as $monthDate) {
            if (!$monthDate instanceof DateTimeImmutable) {
                continue;
            }

            $cacheKey = $monthDate->format('Y-m');
            $lookup[$cacheKey] = [
                'year' => (int) $monthDate->format('Y'),
                'month' => (int) $monthDate->format('n'),
            ];
            $yearMonthParam[] = sprintf('%d_%d', $lookup[$cacheKey]['year'], $lookup[$cacheKey]['month']);
        }

        if ($yearMonthParam === []) {
            return [];
        }

        $yearMonth = implode('|', $yearMonthParam);
        $minAvailability = $this->buildMinAvailabilityPayload($guestCounts);

        $params = [
            'action' => 'COMMON_AVAILABILITYCHECKJSON',
            'activityid' => implode('|', array_map('strval', $activityIds)),
            'agencyid' => 0,
            'blocksonly' => 'false',
            'year_months' => $yearMonth,
            'checkAutoAttached' => 'true',
            'webbooking' => 'true',
            'hawaiifunbooking' => 'false',
            'agencybooking' => 'false',
            'wantExtendedResults' => 'true',
        ];

        if ($minAvailability !== null) {
            $params['minavailability'] = $minAvailability;
        }

        $responseBody = ($this->httpFetcher)($endpoint, $params);
        $decoded = $this->decodeCompanyServletResponse($responseBody);

        $results = [];

        foreach ($lookup as $cacheKey => $parts) {
            $seatsKey = sprintf('yearmonth_%d_%d', $parts['year'], $parts['month']);
            $extendedKey = $seatsKey . '_ex';

            $seats = isset($decoded[$seatsKey]) && is_array($decoded[$seatsKey])
                ? $decoded[$seatsKey]
                : [];

            $extended = isset($decoded[$extendedKey]) && is_array($decoded[$extendedKey])
                ? $decoded[$extendedKey]
                : [];

            $results[$cacheKey] = [
                'seats' => $seats,
                'extended' => $extended,
            ];
        }

        return $results;
    }

    /**
     * @return array{0:string,1:?int}
     */
    private function resolveDayStatus(
        array $monthData,
        array $normalizedExtended,
        int $day,
        array $activityIds,
        int $requestedSeats
    ): array {
        $key = 'd' . $day;
        $seats = null;
        if (isset($monthData['seats'][$key])) {
            $value = $monthData['seats'][$key];
            if (is_numeric($value)) {
                $seats = (int) $value;
            }
        }

        $availableActivities = [];
        if (isset($normalizedExtended[$key])) {
            $normalizedEntry = $normalizedExtended[$key];
            $availableActivities = $normalizedEntry['availableActivityIds'] ?? $normalizedEntry['activityIds'];
        }

        $isAvailable = false;

        if ($seats !== null) {
            if ($requestedSeats > 0 && $seats < $requestedSeats) {
                return ['sold_out', $seats];
            }

            $isAvailable = $seats > 0;
        } elseif ($availableActivities !== []) {
            $isAvailable = count(array_intersect($availableActivities, array_map('intval', $activityIds))) > 0;
        }

        if (!$isAvailable) {
            return ['sold_out', 0];
        }

        if ($seats !== null && $seats <= self::LIMITED_THRESHOLD) {
            return ['limited', $seats];
        }

        return ['available', $seats];
    }

    private function createDateImmutable(string $value): DateTimeImmutable
    {
        try {
            return new DateTimeImmutable($value ?: 'now');
        } catch (\Exception) {
            return new DateTimeImmutable();
        }
    }

    private function resolveMonthStart(?string $visibleMonth, DateTimeImmutable $fallback): DateTimeImmutable
    {
        if (is_string($visibleMonth) && preg_match('/^\d{4}-\d{2}$/', $visibleMonth) === 1) {
            try {
                return new DateTimeImmutable($visibleMonth . '-01');
            } catch (\Exception) {
                // fall through to fallback
            }
        }

        return $fallback->modify('first day of this month');
    }

    /**
     * @param array<int|string,int> $guestCounts
     * @param array<string,mixed>    $activityConfig
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
     * @param int $requestedSeats
     *
     * @return Timeslot[]
     */
    private function fetchTimeslotsForDate(
        SoapClient $client,
        array $supplierConfig,
        array $activityIds,
        string $date,
        array $guestCounts,
        int $requestedSeats
    ): array {
        $timeslots = [];
        $guestCountPayload = $this->formatGuestCountsPayload($guestCounts);

        $soapDate = $date;
        try {
            $soapDate = (new DateTimeImmutable($date))->format('m/d/Y');
        } catch (\Exception) {
            // keep original format
        }

        foreach ($activityIds as $activityId) {
            $payload = [
                'serviceLogin' => [
                    'username' => $supplierConfig['soapCredentials']['username'],
                    'password' => $supplierConfig['soapCredentials']['password'],
                ],
                'supplierId' => $supplierConfig['supplierId'],
                'activityId' => $activityId,
                'date' => $soapDate,
            ];

            if ($guestCountPayload !== []) {
                $payload['guestCounts'] = $guestCountPayload;
            }

            $response = null;

            try {
                $response = $client->__soapCall('getActivityTimeslots', [$payload]);
            } catch (SoapFault $exception) {
                try {
                    $response = $client->__soapCall('getActivityGuestTypes', [$payload]);
                } catch (SoapFault) {
                    continue;
                }
            }

            $normalized = $this->normalizeSoapResponse($response);
            $rows = $this->extractTimeslotRows($normalized);

            foreach ($rows as $row) {
                $timeslot = $this->createTimeslotFromRow($row);
                if ($timeslot === null) {
                    continue;
                }
                $timeslots[$timeslot->getId()] = $timeslot;
            }
        }

        $sorted = $this->sortTimeslots(array_values($timeslots));

        return $this->filterTimeslotsByRequestedSeats($sorted, $requestedSeats);
    }

    /**
     * @param Timeslot[] $timeslots
     * @return Timeslot[]
     */
    private function sortTimeslots(array $timeslots): array
    {
        usort($timeslots, static function (Timeslot $a, Timeslot $b): int {
            $idA = $a->getId();
            $idB = $b->getId();

            $numericA = preg_replace('/\D+/', '', $idA);
            $numericB = preg_replace('/\D+/', '', $idB);

            if ($numericA !== '' && $numericB !== '') {
                $valueA = (int) $numericA;
                $valueB = (int) $numericB;

                if ($valueA !== $valueB) {
                    return $valueA <=> $valueB;
                }
            }

            return strcmp($a->getLabel(), $b->getLabel());
        });

        return $timeslots;
    }

    /**
     * @param Timeslot[] $timeslots
     * @return Timeslot[]
     */
    private function filterTimeslotsByRequestedSeats(array $timeslots, int $requestedSeats): array
    {
        if ($requestedSeats <= 0) {
            return $timeslots;
        }

        $filtered = [];

        foreach ($timeslots as $timeslot) {
            $available = $timeslot->getAvailable();

            if ($available !== null && $available < $requestedSeats) {
                continue;
            }

            $filtered[] = $timeslot;
        }

        return $filtered;
    }

    /**
     * @param array<int|string,int> $guestCounts
     */
    private function formatGuestCountsPayload(array $guestCounts): array
    {
        $payload = [];

        foreach ($guestCounts as $guestTypeId => $count) {
            $count = (int) $count;
            if ($count <= 0) {
                continue;
            }

            $payload[] = [
                'guestTypeId' => is_numeric($guestTypeId) ? (int) $guestTypeId : (string) $guestTypeId,
                'guestCount' => $count,
            ];
        }

        return $payload;
    }

    private function buildMinAvailabilityPayload(array $guestCounts): ?string
    {
        $guests = [];
        foreach ($guestCounts as $guestTypeId => $count) {
            $count = (int) $count;
            if ($count > 0) {
                $guests[(string) $guestTypeId] = $count;
            }
        }

        if ($guests === []) {
            return null;
        }

        $payload = [
            'guests' => (object) $guests,
            'upgrades' => (object) [],
        ];

        try {
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Unable to encode minavailability payload.', 0, $exception);
        }
    }

    private function decodeCompanyServletResponse(string $responseBody): array
    {
        $trimmed = trim($responseBody);
        if ($trimmed === '') {
            throw new RuntimeException('Empty response received from availability endpoint.');
        }

        if ($trimmed[0] !== '{' && $trimmed[0] !== '[') {
            if (preg_match('/^[^(]+\((.*)\)\s*;?$/s', $trimmed, $matches)) {
                $trimmed = $matches[1];
            }
        }

        $decoded = json_decode($trimmed, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('Unable to decode availability response.');
        }

        return $decoded;
    }

    private function normalizeSoapResponse(mixed $response): mixed
    {
        if ($response instanceof \stdClass) {
            if (property_exists($response, 'return')) {
                return $this->normalizeSoapResponse($response->return);
            }

            return json_decode(json_encode($response), true);
        }

        if (is_array($response) && array_key_exists('return', $response)) {
            return $this->normalizeSoapResponse($response['return']);
        }

        return $response;
    }

    private function extractTimeslotRows(mixed $data): array
    {
        $rows = [];
        $stack = [$data];

        while ($stack !== []) {
            $current = array_pop($stack);

            if ($current instanceof stdClass) {
                $current = get_object_vars($current);
            }

            if (!is_array($current)) {
                continue;
            }

            if ($this->looksLikeTimeslotRow($current)) {
                $rows[] = $this->convertObjectsToArrays($current);
                continue;
            }

            foreach ($current as $value) {
                if ($value instanceof stdClass || is_array($value)) {
                    $stack[] = $value;
                }
            }
        }

        return $rows;
    }

    private function convertObjectsToArrays(mixed $value): mixed
    {
        if ($value instanceof stdClass) {
            $value = get_object_vars($value);
        }

        if (!is_array($value)) {
            return $value;
        }

        $converted = [];
        foreach ($value as $key => $item) {
            $converted[$key] = $this->convertObjectsToArrays($item);
        }

        return $converted;
    }

    private function looksLikeTimeslotRow(array $row): bool
    {
        $hasIdentifier = isset($row['id']) || isset($row['timeslotId']) || isset($row['time']) || isset($row['departureTime']);
        $hasLabel = isset($row['label']) || isset($row['time']) || isset($row['departure']) || isset($row['departureTime']) || $this->extractTimeslotDetailsValue($row, 'times') !== null;

        return $hasIdentifier && $hasLabel;
    }

    private function createTimeslotFromRow(array $row): ?Timeslot
    {
        $id = (string) ($row['id'] ?? $row['timeslotId'] ?? $row['time'] ?? $row['departureTime'] ?? '');
        if ($id === '') {
            return null;
        }

        $detailsLabel = $this->extractTimeslotDetailsValue($row, 'times');
        $label = (string) (($detailsLabel ?? $row['label'] ?? $row['time'] ?? $row['departure'] ?? $row['departureTime']) ?? $id);
        $details = $this->normalizeTimeslotDetails($row['details'] ?? null);

        $available = null;
        foreach (['available', 'availability', 'availableSpots', 'availableSeats', 'remaining'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                $available = (int) $row[$key];
                break;
            }
        }

        return new Timeslot($id, $label, $available, $details);
    }

    private function extractTimeslotDetailsValue(array $row, string $key): ?string
    {
        $details = $row['details'] ?? null;
        if ($details instanceof stdClass) {
            $details = (array) $details;
        }

        if (!is_array($details) || !array_key_exists($key, $details)) {
            return null;
        }

        return $this->stringifyTimeslotDetailValue($details[$key]);
    }

    /**
     * @return array<string,string>
     */
    private function normalizeTimeslotDetails(mixed $details): array
    {
        if ($details instanceof stdClass) {
            $details = (array) $details;
        }

        if (!is_array($details) || $details === []) {
            return [];
        }

        $normalized = [];
        foreach ($details as $key => $value) {
            $stringKey = is_string($key) || is_int($key) ? (string) $key : null;
            if ($stringKey === null || $stringKey === '') {
                continue;
            }

            $stringValue = $this->stringifyTimeslotDetailValue($value);
            if ($stringValue !== null) {
                $normalized[$stringKey] = $stringValue;
            }
        }

        return $normalized;
    }

    private function stringifyTimeslotDetailValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_scalar($value)) {
            $string = (string) $value;

            return $string !== '' ? $string : null;
        }

        if ($value instanceof stdClass) {
            $value = (array) $value;
        }

        if (!is_array($value) || $value === []) {
            return null;
        }

        $preferredKeys = ['provided', 'display', 'label', 'text', 'value'];
        foreach ($preferredKeys as $preferredKey) {
            if (array_key_exists($preferredKey, $value)) {
                $candidate = $this->stringifyTimeslotDetailValue($value[$preferredKey]);
                if ($candidate !== null) {
                    return $candidate;
                }
            }
        }

        foreach ($value as $candidate) {
            $string = $this->stringifyTimeslotDetailValue($candidate);
            if ($string !== null) {
                return $string;
            }
        }

        return null;
    }

    private function defaultHttpFetch(string $url, array $params): string
    {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $target = $url . (str_contains($url, '?') ? '&' : '?') . $query;

        if (function_exists('curl_init')) {
            return $this->performCurlRequest($target, 0);
        }

        $cafile = $this->findCaBundlePath();
        if ($cafile === null) {
            throw new RuntimeException('Unable to locate a trusted CA bundle for availability request.');
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
                'cafile' => $cafile,
            ],
        ]);

        $body = @file_get_contents($target, false, $context);
        if ($body === false) {
            throw new RuntimeException('Availability request failed using fopen.');
        }

        return $body;
    }

    private function performCurlRequest(string $url, int $redirectCount): string
    {
        if ($redirectCount > 5) {
            throw new RuntimeException('Availability request exceeded maximum redirect attempts.');
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new RuntimeException('Unable to initialise cURL for availability request.');
        }

        $cafile = $this->findCaBundlePath();
        if ($cafile === null) {
            curl_close($handle);
            throw new RuntimeException('Unable to locate a trusted CA bundle for availability request.');
        }

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);

        curl_setopt($handle, CURLOPT_CAINFO, $cafile);

        $response = curl_exec($handle);
        if ($response === false) {
            $error = curl_error($handle);
            curl_close($handle);
            throw new RuntimeException('Availability request failed: ' . $error);
        }

        $headerSize = curl_getinfo($handle, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        $header = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);

        if ($status >= 300 && $status < 400 && preg_match('/^Location:\s*(.+)$/im', $header, $match)) {
            $location = trim($match[1]);
            if ($location !== '') {
                $nextUrl = $this->resolveRedirectUrl($url, $location);
                return $this->performCurlRequest($nextUrl, $redirectCount + 1);
            }
        }

        if ($status !== 200) {
            throw new RuntimeException('Availability request returned HTTP ' . $status);
        }

        return $body;
    }

    private function resolveRedirectUrl(string $currentUrl, string $location): string
    {
        if (preg_match('#^https?://#i', $location)) {
            return $location;
        }

        $parts = parse_url($currentUrl);
        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            return $location;
        }

        $scheme = $parts['scheme'];
        $host = $parts['host'];
        $port = isset($parts['port']) ? ':' . $parts['port'] : '';
        $path = $location[0] === '/' ? $location : rtrim($parts['path'] ?? '/', '/') . '/' . $location;

        return sprintf('%s://%s%s%s', $scheme, $host, $port, $path);
    }

    private function findCaBundlePath(): ?string
    {
        $candidates = [
            ini_get('curl.cainfo'),
            ini_get('openssl.cafile'),
            '/etc/ssl/certs/ca-certificates.crt',
            '/etc/ssl/certs/ca-bundle.crt',
            '/etc/ssl/cert.pem',
            '/etc/pki/tls/certs/ca-bundle.crt',
            '/etc/pki/ca-trust/extracted/pem/tls-ca-bundle.pem',
            '/usr/local/etc/openssl@3/cert.pem',
            '/usr/local/etc/openssl/cert.pem',
        ];

        foreach ($candidates as $candidate) {
            $path = is_string($candidate) ? trim($candidate) : '';
            if ($path === '') {
                continue;
            }

            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        return null;
    }
}
