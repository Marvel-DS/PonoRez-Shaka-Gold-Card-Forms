<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\Cache\FileCache;
use PonoRez\SGCForms\Cache\NullCache;
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

    $service = new AvailabilityService(new SoapClientBuilder());
    $result = $service->fetchCalendar(
        $params['supplier'],
        $params['activity'],
        $date,
        $guestCounts,
        $activityIds,
        $visibleMonth
    );

    $cacheDirectory = UtilityService::projectRoot() . '/cache/activity-info';
    $cache = is_writable(dirname($cacheDirectory))
        ? new FileCache($cacheDirectory)
        : new NullCache();
    $activityInfoService = new ActivityInfoService($cache, new SoapClientBuilder());
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
    }

    ResponseFormatter::success([
        'calendar' => $result['calendar']->toArray(),
        'timeslots' => array_map(static fn ($slot) => $slot->toArray(), $result['timeslots']),
        'metadata' => $metadata,
    ]);
} catch (Throwable $exception) {
    ErrorHandler::handle($exception);
}
