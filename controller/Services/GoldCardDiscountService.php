<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use PonoRez\SGCForms\UtilityService;
use RuntimeException;

final class GoldCardDiscountService
{
    private const DEFAULT_TIMEOUT = 10;

    /**
     * @param array<int, string|int|null> $numbers
     * @param array<string, mixed> $context
     *
     * @return array{
     *     code: ?string,
     *     numbers: array<int, string>,
     *     source?: string,
     *     raw?: mixed
     * }
     */
    public function fetch(
        string $supplierSlug,
        string $activitySlug,
        array $numbers,
        array $context = []
    ): array {
        $normalizedNumbers = $this->normalizeNumbers($numbers);
        if ($normalizedNumbers === []) {
            return [
                'code' => null,
                'numbers' => [],
                'source' => 'empty',
                'raw' => null,
            ];
        }

        $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
        $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);

        $baseUrl = UtilityService::resolvePonorezBaseUrl($activityConfig, $supplierConfig);

        $query = $this->buildAvailabilityQuery(
            $supplierConfig,
            $activityConfig,
            $normalizedNumbers,
            $context
        );

        $responseBody = $this->performAvailabilityLookup($baseUrl, $query);
        [$code, $message] = $this->parseAvailabilityResponse($responseBody);

        if ($code === null && $message === null) {
            throw new RuntimeException('Unable to resolve discount code from Ponorez response.');
        }

        return [
            'code' => $code,
            'numbers' => $normalizedNumbers,
            'source' => 'availability',
            'message' => $message,
        ];
    }

    /**
     * @param array<string, mixed> $supplierConfig
     * @param array<string, mixed> $activityConfig
     * @param array<int, string> $numbers
     * @param array<string, mixed> $context
     *
     * @return array<int, string>
     */
    private function buildAvailabilityQuery(
        array $supplierConfig,
        array $activityConfig,
        array $numbers,
        array $context
    ): array {
        $parts = [];

        $parts[] = 'action=AVAILABILITYCHECKPAGE';
        $parts[] = 'iframe=1';
        $parts[] = 'mode=reservation';
        $parts[] = 'externalpurchasemode=2';
        $parts[] = 'webbooking=true';

        if (isset($supplierConfig['supplierId'])) {
            $parts[] = 'supplierid=' . rawurlencode((string) $supplierConfig['supplierId']);
        }

        $timeslotId = $context['timeslotId'] ?? null;
        $primaryActivityId = UtilityService::getPrimaryActivityId($activityConfig);
        $targetActivityId = $timeslotId !== null && $timeslotId !== ''
            ? (string) $timeslotId
            : ($primaryActivityId !== null ? (string) $primaryActivityId : null);

        if ($targetActivityId !== null) {
            $parts[] = 'activityid=' . rawurlencode($targetActivityId);
        }

        $date = isset($context['date']) && is_string($context['date']) && $context['date'] !== ''
            ? $context['date']
            : null;

        if ($date !== null) {
            $parts[] = 'date=' . rawurlencode($this->formatPonorezDate($date));
        }

        $guestCounts = $this->normaliseCounts($context['guestCounts'] ?? []);
        foreach ($guestCounts as $guestId => $count) {
            $parts[] = sprintf('guests_t%s=%d', rawurlencode((string) $guestId), $count);
        }

        $upgrades = $this->normaliseCounts($context['upgrades'] ?? []);
        if ($upgrades !== []) {
            $parts[] = 'upgradesfixed=1';
            foreach ($upgrades as $upgradeId => $count) {
                $parts[] = sprintf('upgrades_u%s=%d', rawurlencode((string) $upgradeId), $count);
            }
        }

        $routeId = $context['transportationRouteId'] ?? null;
        if ($routeId !== null && $routeId !== '') {
            $parts[] = 'transportationrouteid=' . rawurlencode((string) $routeId);
            $parts[] = 'transportationpreselected=' . rawurlencode((string) $routeId);
        }

        foreach ($numbers as $number) {
            $parts[] = 'goldcardnumber=' . rawurlencode($number);
        }

        if (!empty($context['buyGoldCard'])) {
            $parts[] = 'buygoldcards=1';
        }

        if (!empty($context['policyAccepted'])) {
            $parts[] = 'policy=1';
        }

        if (array_key_exists('payLater', $context)) {
            $parts[] = 'paylater=' . (!empty($context['payLater']) ? 'true' : 'false');
        }

        if (!empty($context['referer'])) {
            $parts[] = 'referer=' . rawurlencode($context['referer']);
        }

        return $parts;
    }

    /**
     * @param array<int, string> $queryParts
     */
    private function performAvailabilityLookup(string $baseUrl, array $queryParts): string
    {
        $endpoint = rtrim($baseUrl, '/') . '/externalservlet';

        $url = $endpoint . '?' . implode('&', $queryParts);
        $handle = curl_init($url);

        if ($handle === false) {
            throw new RuntimeException('Unable to initialize gold card discount lookup.');
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => self::DEFAULT_TIMEOUT,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/plain;q=0.7, */*;q=0.1',
            ],
        ];

        if (UtilityService::shouldDisableAvailabilityCertificateVerification()) {
            $options[CURLOPT_SSL_VERIFYPEER] = false;
            $options[CURLOPT_SSL_VERIFYHOST] = false;
        } else {
            $caBundle = UtilityService::getTrustedCaBundlePath();
            if ($caBundle !== null) {
                $options[CURLOPT_CAINFO] = $caBundle;
            }
        }

        curl_setopt_array($handle, $options);

        $body = curl_exec($handle);
        if ($body === false) {
            $message = curl_error($handle) ?: 'Unknown cURL error';
            $code = curl_errno($handle);
            curl_close($handle);
            throw new RuntimeException(sprintf(
                'Gold card discount request failed (cURL %d): %s',
                $code,
                $message
            ));
        }

        $status = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($status < 200 || $status >= 300 || $status === null) {
            throw new RuntimeException(sprintf(
                'Gold card discount request failed with HTTP status %s.',
                $status ?? 'unknown'
            ));
        }

        return $body;
    }

    /**
     * @param array<int, string|int|null> $numbers
     * @return array<int, string>
     */
    private function normalizeNumbers(array $numbers): array
    {
        $normalized = [];
        foreach ($numbers as $value) {
            if ($value === null) {
                continue;
            }

            if (!is_string($value) && !is_numeric($value)) {
                continue;
            }

            $candidate = trim((string) $value);
            if ($candidate === '') {
                continue;
            }

            $normalized[] = $candidate;
        }

        return array_values(array_unique($normalized));
    }

    /**
     * @param array<string, mixed> $input
     * @return array<string, int>
     */
    private function normaliseCounts(array $input): array
    {
        $normalized = [];
        foreach ($input as $key => $value) {
            if (!is_scalar($value)) {
                continue;
            }

            $count = (int) $value;
            if ($count <= 0) {
                continue;
            }

            $normalized[(string) $key] = $count;
        }

        ksort($normalized);

        return $normalized;
    }

    /**
     * @return array{0:?string,1:?string}
     */
    private function parseAvailabilityResponse(string $html): array
    {
        $code = null;
        $message = null;

        if (preg_match('/setdiscount\\(([\'"])([a-f0-9]{8,})\\1\\)/i', $html, $matches)) {
            $code = strtolower($matches[2]);
        } elseif (preg_match('/discountcode\\s*=\\s*([a-f0-9]{8,})/i', $html, $matches)) {
            $code = strtolower($matches[1]);
        }

        if ($code === null) {
            if (preg_match(
                '/<div[^>]*class="[^"]*alert[^"]*alert-danger[^"]*"[^>]*>.*?<strong>(.*?)<\/strong>/is',
                $html,
                $alertMatches
            )) {
                $extracted = trim(strip_tags(html_entity_decode($alertMatches[1], ENT_QUOTES | ENT_HTML5)));
                if ($extracted !== '') {
                    $message = $extracted;
                }
            }
        }

        return [$code, $message];
    }

    private function formatPonorezDate(string $iso): string
    {
        $parts = explode('-', trim($iso));
        if (count($parts) !== 3) {
            return $iso;
        }

        [$year, $month, $day] = $parts;
        return sprintf('%02d/%02d/%04d', (int) $month, (int) $day, (int) $year);
    }
}
