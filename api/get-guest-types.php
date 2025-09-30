<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\Cache\FileCache;
use PonoRez\SGCForms\Cache\NullCache;
use PonoRez\SGCForms\Services\GuestTypeService;
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
    $guestCounts = [];
    if (isset($_GET['guestCounts'])) {
        $guestCounts = RequestValidator::optionalJson((string) $_GET['guestCounts'], 'guestCounts');
    }

    $cacheDirectory = UtilityService::projectRoot() . '/cache/guest-types';
    $cache = is_writable(dirname($cacheDirectory)) ? new FileCache($cacheDirectory) : new NullCache();

    $service = new GuestTypeService($cache, new SoapClientBuilder());
    $collection = $service->fetch($params['supplier'], $params['activity'], $date, $guestCounts);

    ResponseFormatter::success([
        'guestTypes' => $collection->toArray(),
    ]);
} catch (Throwable $exception) {
    ErrorHandler::handle($exception);
}
