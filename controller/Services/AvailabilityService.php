<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use DateInterval;
use DatePeriod;
use DateTimeImmutable;
use JsonException;
use PonoRez\SGCForms\DTO\AvailabilityCalendar;
use PonoRez\SGCForms\DTO\AvailabilityDay;
use PonoRez\SGCForms\DTO\Timeslot;
use PonoRez\SGCForms\UtilityService;
use RuntimeException;
use SoapClient;
use SoapFault;

final class AvailabilityService
{
    private const LIMITED_THRESHOLD = 5;

    /** @var callable */
    private $httpFetcher;
    private bool $certificateVerificationDisabled = false;

    public function __construct(
        private readonly SoapClientFactory $soapClientBuilder,
        ?callable $httpFetcher = null
    ) {
        $this->httpFetcher = $httpFetcher ?? [$this, 'defaultHttpFetch'];
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

        $monthsToLoad = [$viewMonthStart->format('Y-m') => $viewMonthStart];
        $selectedMonthKey = $selectedDay->format('Y-m');
        if (!isset($monthsToLoad[$selectedMonthKey])) {
            $monthsToLoad[$selectedMonthKey] = $selectedDay->modify('first day of this month');
        }

        $monthData = [];
        foreach ($monthsToLoad as $monthKey => $monthStart) {
            $monthData[$monthKey] = $this->fetchMonthAvailabilityData(
                $supplierConfig,
                $activityIds,
                $guestCounts,
                (int) $monthStart->format('Y'),
                (int) $monthStart->format('n')
            );
        }

        $calendar = new AvailabilityCalendar();
        $firstAvailableDate = null;
        $viewMonthKey = $viewMonthStart->format('Y-m');
        $viewMonthData = $monthData[$viewMonthKey];

        $monthEnd = $viewMonthStart->modify('last day of this month');
        $period = new DatePeriod($viewMonthStart, new DateInterval('P1D'), $monthEnd->modify('+1 day'));

        foreach ($period as $date) {
            $day = (int) $date->format('j');
            [$status] = $this->resolveDayStatus($viewMonthData, $day, $activityIds);

            $calendar->addDay(new AvailabilityDay($date->format('Y-m-d'), $status));

            if ($firstAvailableDate === null && in_array($status, ['available', 'limited'], true)) {
                $firstAvailableDate = $date->format('Y-m-d');
            }
        }

        $selectedMonthData = $monthData[$selectedMonthKey] ?? null;
        $selectedDayStatus = 'sold_out';
        if ($selectedMonthData !== null) {
            [$selectedDayStatus] = $this->resolveDayStatus(
                $selectedMonthData,
                (int) $selectedDay->format('j'),
                $activityIds
            );
        }

        if ($firstAvailableDate === null) {
            $searchStart = $viewMonthStart->modify('first day of next month');

            for ($offset = 0; $offset < 6 && $firstAvailableDate === null; $offset += 1) {
                $year = (int) $searchStart->format('Y');
                $month = (int) $searchStart->format('n');
                $monthKey = $searchStart->format('Y-m');

                $monthData[$monthKey] = $this->fetchMonthAvailabilityData(
                    $supplierConfig,
                    $activityIds,
                    $guestCounts,
                    $year,
                    $month
                );

                $monthEnd = $searchStart->modify('last day of this month');
                $period = new DatePeriod($searchStart, new DateInterval('P1D'), $monthEnd->modify('+1 day'));

                foreach ($period as $date) {
                    [$status] = $this->resolveDayStatus(
                        $monthData[$monthKey],
                        (int) $date->format('j'),
                        $activityIds
                    );

                    if (in_array($status, ['available', 'limited'], true)) {
                        $firstAvailableDate = $date->format('Y-m-d');
                        break;
                    }
                }

                $searchStart = $searchStart->modify('first day of next month');
            }
        }

        $requestedSeats = $this->calculateRequestedSeats($guestCounts, $activityConfig);
        $timeslots = [];
        $timeslotStatus = 'unavailable';

        if (in_array($selectedDayStatus, ['available', 'limited'], true)) {
            $timeslots = $this->fetchTimeslotsForDate(
                $this->soapClientBuilder->build(),
                $supplierConfig,
                $activityIds,
                $selectedDay->format('Y-m-d'),
                $guestCounts
            );

            $timeslotStatus = $timeslots === [] ? 'unavailable' : 'available';
        }

        return [
            'calendar' => $calendar,
            'timeslots' => $timeslots,
            'metadata' => array_filter([
            'source' => 'ponorez-json',
            'requestedSeats' => $requestedSeats,
            'firstAvailableDate' => $firstAvailableDate,
            'timeslotStatus' => $timeslotStatus,
            'selectedDateStatus' => $selectedDayStatus,
            'month' => $viewMonthKey,
            'certificateVerification' => $this->certificateVerificationDisabled ? 'disabled' : 'verified',
        ], static fn ($value) => $value !== null),
        ];
    }

    /**
     * @param array<string,mixed> $activityConfig
     * @param array<int|string,int> $guestCounts
     * @return array<string,mixed>
     */
    private function fetchMonthAvailabilityData(
        array $supplierConfig,
        array $activityIds,
        array $guestCounts,
        int $year,
        int $month
    ): array {
        $baseUrl = UtilityService::getReservationBaseUrl();
        $endpoint = rtrim($baseUrl, '/') . '/companyservlet';

        $yearMonth = sprintf('%d_%d', $year, $month);
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

        $seatsKey = 'yearmonth_' . $yearMonth;
        $extendedKey = $seatsKey . '_ex';

        $seats = isset($decoded[$seatsKey]) && is_array($decoded[$seatsKey])
            ? $decoded[$seatsKey]
            : [];

        $extended = isset($decoded[$extendedKey]) && is_array($decoded[$extendedKey])
            ? $decoded[$extendedKey]
            : [];

        return [
            'seats' => $seats,
            'extended' => $extended,
        ];
    }

    /**
     * @return array{0:string,1:?int}
     */
    private function resolveDayStatus(array $monthData, int $day, array $activityIds): array
    {
        $key = 'd' . $day;
        $seats = null;
        if (isset($monthData['seats'][$key])) {
            $value = $monthData['seats'][$key];
            if (is_numeric($value)) {
                $seats = (int) $value;
            }
        }

        $availableActivities = [];
        if (isset($monthData['extended'][$key]) && is_array($monthData['extended'][$key])) {
            $entry = $monthData['extended'][$key];
            if (isset($entry['aids']) && is_array($entry['aids'])) {
                $availableActivities = array_map('intval', $entry['aids']);
            }
        }

        $isAvailable = false;

        if ($seats !== null) {
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
     * @return Timeslot[]
     */
    private function fetchTimeslotsForDate(
        SoapClient $client,
        array $supplierConfig,
        array $activityIds,
        string $date,
        array $guestCounts
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

        $list = array_values($timeslots);

        usort($list, static function (Timeslot $a, Timeslot $b): int {
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

        return $list;
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

            if ($current instanceof \stdClass) {
                $stack[] = json_decode(json_encode($current), true);
                continue;
            }

            if (!is_array($current)) {
                continue;
            }

            if ($this->looksLikeTimeslotRow($current)) {
                $rows[] = $current;
                continue;
            }

            foreach ($current as $value) {
                if (is_array($value) || $value instanceof \stdClass) {
                    $stack[] = $value;
                }
            }
        }

        return $rows;
    }

    private function looksLikeTimeslotRow(array $row): bool
    {
        $hasIdentifier = isset($row['id']) || isset($row['timeslotId']) || isset($row['time']) || isset($row['departureTime']);
        $hasLabel = isset($row['label']) || isset($row['time']) || isset($row['departure']) || isset($row['departureTime']);

        return $hasIdentifier && $hasLabel;
    }

    private function createTimeslotFromRow(array $row): ?Timeslot
    {
        $id = (string) ($row['id'] ?? $row['timeslotId'] ?? $row['time'] ?? $row['departureTime'] ?? '');
        if ($id === '') {
            return null;
        }

        $label = (string) ($row['label'] ?? $row['time'] ?? $row['departure'] ?? $row['departureTime'] ?? $id);

        $available = null;
        foreach (['available', 'availability', 'availableSpots', 'availableSeats', 'remaining'] as $key) {
            if (isset($row[$key]) && is_numeric($row[$key])) {
                $available = (int) $row[$key];
                break;
            }
        }

        return new Timeslot($id, $label, $available);
    }

    private function defaultHttpFetch(string $url, array $params): string
    {
        $query = http_build_query($params, '', '&', PHP_QUERY_RFC3986);
        $target = $url . (str_contains($url, '?') ? '&' : '?') . $query;

        if (function_exists('curl_init')) {
            return $this->performCurlRequest($target, 0);
        }

        $cafile = $this->findCaBundlePath();
        $verifyPeer = $cafile !== null;
        if (!$verifyPeer) {
            $this->certificateVerificationDisabled = true;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 15,
                'follow_location' => 1,
                'max_redirects' => 5,
            ],
            'ssl' => [
                'verify_peer' => $verifyPeer,
                'verify_peer_name' => $verifyPeer,
                'allow_self_signed' => !$verifyPeer,
            ] + ($cafile !== null ? ['cafile' => $cafile] : []),
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
        $verifyPeer = $cafile !== null;

        curl_setopt_array($handle, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => $verifyPeer,
            CURLOPT_SSL_VERIFYHOST => $verifyPeer ? 2 : 0,
        ]);

        if ($cafile !== null) {
            curl_setopt($handle, CURLOPT_CAINFO, $cafile);
        } else {
            $this->certificateVerificationDisabled = true;
        }

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
            '/etc/ssl/cert.pem',
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
