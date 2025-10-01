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

final class AvailabilityService
{
    private const LIMITED_THRESHOLD = 5;

    /** @var callable */
    private $httpFetcher;
    private bool $certificateVerificationDisabled = false;

    public function __construct(
        ?SoapClientFactory $soapClientBuilder = null,
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
        $monthStarts = $monthsToLoad;
        $selectedMonthKey = $selectedDay->format('Y-m');
        if (!isset($monthsToLoad[$selectedMonthKey])) {
            $monthsToLoad[$selectedMonthKey] = $selectedDay->modify('first day of this month');
            $monthStarts[$selectedMonthKey] = $monthsToLoad[$selectedMonthKey];
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
            $monthStarts[$monthKey] = $monthStart;
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
        $selectedDayActivities = [];
        if ($selectedMonthData !== null) {
            $selectedDayNumber = (int) $selectedDay->format('j');
            [$selectedDayStatus] = $this->resolveDayStatus(
                $selectedMonthData,
                $selectedDayNumber,
                $activityIds
            );
            $selectedDayActivities = $this->resolveAvailableActivities(
                $selectedMonthData,
                $selectedDayNumber,
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
                $monthStarts[$monthKey] = $searchStart;

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

        $timeslots = $this->buildTimeslotsForDate(
            $activityIds,
            $selectedDayActivities,
            $activityConfig
        );

        $timeslotStatus = $timeslots === [] ? 'unavailable' : 'available';

        $extendedAvailability = $this->buildExtendedAvailabilityIndex(
            $monthData,
            $monthStarts,
            $activityIds
        );

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
            'extended' => $extendedAvailability,
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

        $availableActivities = $this->resolveAvailableActivities($monthData, $day, $activityIds);

        if ($availableActivities !== []) {
            if ($seats !== null && $seats <= 0) {
                $seats = null;
            }

            if ($seats !== null && $seats <= self::LIMITED_THRESHOLD) {
                return ['limited', $seats];
            }

            return ['available', $seats];
        }

        if ($seats === null) {
            return ['sold_out', 0];
        }

        if ($seats <= 0) {
            return ['sold_out', 0];
        }

        if ($seats <= self::LIMITED_THRESHOLD) {
            return ['limited', $seats];
        }

        return ['available', $seats];
    }

    private function resolveAvailableActivities(array $monthData, int $day, array $activityIds): array
    {
        $key = 'd' . $day;
        if (!isset($monthData['extended'][$key]) || !is_array($monthData['extended'][$key])) {
            return [];
        }

        $entry = $monthData['extended'][$key];
        if (!isset($entry['aids']) || !is_array($entry['aids'])) {
            return [];
        }

        $available = [];
        $activityLookup = array_map('intval', $activityIds);

        foreach ($entry['aids'] as $candidate) {
            $id = (int) $candidate;
            if (($activityLookup === [] || in_array($id, $activityLookup, true))
                && !in_array($id, $available, true)
            ) {
                $available[] = $id;
            }
        }

        return $available;
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
    private function buildTimeslotsForDate(array $activityIds, array $availableIds, array $activityConfig): array
    {
        if ($availableIds === []) {
            return [];
        }

        $labels = [];
        foreach (['departureLabels', 'timeslotLabels'] as $labelKey) {
            if (!isset($activityConfig[$labelKey]) || !is_array($activityConfig[$labelKey])) {
                continue;
            }

            foreach ($activityConfig[$labelKey] as $id => $label) {
                $labels[(string) $id] = (string) $label;
            }
        }

        $availableLookup = array_map('intval', $availableIds);
        $timeslots = [];

        foreach ($activityIds as $activityId) {
            $id = (int) $activityId;
            if (!in_array($id, $availableLookup, true)) {
                continue;
            }

            $stringId = (string) $id;
            $label = $labels[$stringId] ?? $labels[(string) $activityId] ?? null;
            if ($label === null || $label === '') {
                $label = 'Departure ' . $stringId;
            }

            $timeslots[] = new Timeslot($stringId, $label);
        }

        return $timeslots;
    }

    private function buildExtendedAvailabilityIndex(array $monthData, array $monthStarts, array $activityIds): array
    {
        $index = [];
        $activityLookup = array_map('intval', $activityIds);

        foreach ($monthData as $monthKey => $data) {
            if (!isset($data['extended']) || !is_array($data['extended'])) {
                continue;
            }

            $monthStart = $monthStarts[$monthKey] ?? null;
            if (!$monthStart instanceof DateTimeImmutable) {
                try {
                    $monthStart = new DateTimeImmutable($monthKey . '-01');
                } catch (\Exception) {
                    continue;
                }
            }

            $year = (int) $monthStart->format('Y');
            $month = (int) $monthStart->format('n');

            foreach ($data['extended'] as $dayKey => $entry) {
                if (!is_array($entry) || !isset($entry['aids']) || !is_array($entry['aids'])) {
                    continue;
                }

                if (preg_match('/^d(\d{1,2})$/', (string) $dayKey, $matches) !== 1) {
                    continue;
                }

                $day = (int) $matches[1];
                if ($day < 1 || $day > 31) {
                    continue;
                }

                $date = $monthStart->setDate($year, $month, $day);
                $iso = $date->format('Y-m-d');

                $available = [];
                foreach ($entry['aids'] as $candidate) {
                    $id = (int) $candidate;
                    if (($activityLookup === [] || in_array($id, $activityLookup, true))
                        && !in_array($id, $available, true)
                    ) {
                        $available[] = $id;
                    }
                }

                $index[$iso] = $available;
            }
        }

        ksort($index);

        return $index;
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
