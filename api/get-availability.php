<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\Services\ActivityInfoService;
use PonoRez\SGCForms\Services\AvailabilityService;
use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\Support\ErrorHandler;
use PonoRez\SGCForms\Support\RequestValidator;
use PonoRez\SGCForms\Support\ResponseFormatter;
use PonoRez\SGCForms\UtilityService;

try {
    $params = RequestValidator::requireParams($_GET, ['supplier', 'activity']);
    $date = trim((string) ($_GET['date'] ?? ''));
    if ($date === '') {
        $date = (new \DateTimeImmutable())->format('Y-m-d');
    }

    $visibleMonth = isset($_GET['month']) ? trim((string) $_GET['month']) : null;

    $guestCounts = [];
    if (isset($_GET['guestCounts'])) {
        $guestCounts = RequestValidator::optionalJson((string) $_GET['guestCounts'], 'guestCounts');
    }

    $activityIds = null;
    if (isset($_GET['activityIds'])) {
        $rawActivityIds = $_GET['activityIds'];
        if (is_string($rawActivityIds)) {
            $rawActivityIds = trim($rawActivityIds);
            if ($rawActivityIds !== '') {
                if ($rawActivityIds[0] === '[') {
                    $activityIds = RequestValidator::optionalJson($rawActivityIds, 'activityIds');
                } else {
                    $activityIds = array_filter(array_map('trim', explode(',', $rawActivityIds)), static fn ($value) => $value !== '');
                }
            }
        } elseif (is_array($rawActivityIds)) {
            $activityIds = $rawActivityIds;
        }

        if (is_array($activityIds)) {
            $activityIds = array_values(array_map('intval', $activityIds));
        }
    }

    $activityConfig = null;
    $departureLabels = [];

    try {
        $activityConfig = UtilityService::loadActivityConfig($params['supplier'], $params['activity']);
        $departureLabels = UtilityService::getDepartureLabels($activityConfig);
    } catch (Throwable) {
        $activityConfig = null;
        $departureLabels = [];
    }

    $availabilityCache = UtilityService::createCache('cache/availability');
    $service = new AvailabilityService(new SoapClientBuilder(), null, $availabilityCache);
    $result = $service->fetchCalendar(
        $params['supplier'],
        $params['activity'],
        $date,
        $guestCounts,
        $activityIds,
        $visibleMonth
    );

    $activityInfoCache = UtilityService::createCache('cache/activity-info');
    $activityInfoService = new ActivityInfoService($activityInfoCache, new SoapClientBuilder());
    $activityInfoResult = $activityInfoService->getActivityInfo($params['supplier'], $params['activity']);

    $metadata = $result['metadata'] ?? [];
    $activityInfoById = is_array($activityInfoResult['activities'] ?? null)
        ? $activityInfoResult['activities']
        : [];

    if ($activityInfoById !== []) {
        $metadata['activityInfo'] = $activityInfoById;

        if (isset($activityInfoResult['checkedAt']) && $activityInfoResult['checkedAt'] !== null) {
            $metadata['activityInfoCheckedAt'] = $activityInfoResult['checkedAt'];
        }

        if (isset($activityInfoResult['hash']) && is_string($activityInfoResult['hash'])) {
            $metadata['activityInfoHash'] = $activityInfoResult['hash'];
        }

        foreach ($activityInfoById as $activityId => $info) {
            $idString = (string) $activityId;
            if ($idString === '') {
                continue;
            }

            $existing = isset($departureLabels[$idString]) ? trim((string) $departureLabels[$idString]) : '';
            if ($existing !== '') {
                continue;
            }

            $label = null;
            if (!empty($info['label'])) {
                $candidate = trim((string) $info['label']);
                if ($candidate !== '') {
                    $label = $candidate;
                }
            }

            if ($label === null && !empty($info['times'])) {
                $candidate = trim((string) $info['times']);
                if ($candidate !== '') {
                    $label = $candidate;
                }
            }

            if ($label !== null) {
                $departureLabels[$idString] = $label;
            }
        }
    }

    $timeslotArrays = array_map(static fn ($slot) => $slot->toArray(), $result['timeslots']);

    foreach ($timeslotArrays as &$slot) {
        if (!isset($slot['id'])) {
            continue;
        }

        $idString = (string) $slot['id'];
        if ($idString === '') {
            continue;
        }

        $label = isset($slot['label']) ? trim((string) $slot['label']) : '';
        $fallbackMatch = strcasecmp($label, 'Departure ' . $idString) === 0;

        if (($label === '' || $fallbackMatch) && isset($departureLabels[$idString])) {
            $slot['label'] = $departureLabels[$idString];
            if (isset($slot['details']) && is_array($slot['details'])) {
                $detailLabel = isset($slot['details']['times']) ? trim((string) $slot['details']['times']) : '';
                if ($detailLabel === '' || strcasecmp($detailLabel, 'Departure ' . $idString) === 0) {
                    $slot['details']['times'] = $departureLabels[$idString];
                }
            }
        }
    }
    unset($slot);

    ResponseFormatter::success([
        'calendar' => $result['calendar']->toArray(),
        'timeslots' => $timeslotArrays,
        'metadata' => $metadata,
    ]);
} catch (Throwable $exception) {
    ErrorHandler::handle($exception);
}
