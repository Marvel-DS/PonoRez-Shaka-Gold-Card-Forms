<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\Cache\FileCache;
use PonoRez\SGCForms\Cache\NullCache;
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

    $cacheDirectory = UtilityService::projectRoot() . '/cache/availability';
    $cache = is_writable(dirname($cacheDirectory)) ? new FileCache($cacheDirectory) : new NullCache();

    $service = new AvailabilityService($cache, new SoapClientBuilder());
    $result = $service->fetchCalendar($params['supplier'], $params['activity'], $date);

    ResponseFormatter::success([
        'calendar' => $result['calendar']->toArray(),
        'timeslots' => array_map(static fn ($slot) => $slot->toArray(), $result['timeslots']),
        'metadata' => $result['metadata'] ?? [],
    ]);
} catch (Throwable $exception) {
    ErrorHandler::handle($exception);
}
