<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\Cache\FileCache;
use PonoRez\SGCForms\Cache\NullCache;
use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\Services\TransportationService;
use PonoRez\SGCForms\Support\ErrorHandler;
use PonoRez\SGCForms\Support\RequestValidator;
use PonoRez\SGCForms\Support\ResponseFormatter;
use PonoRez\SGCForms\UtilityService;

try {
    $params = RequestValidator::requireParams($_GET, ['supplier', 'activity']);

    $cacheDirectory = UtilityService::projectRoot() . '/cache/transportation';
    $cache = is_writable(dirname($cacheDirectory)) ? new FileCache($cacheDirectory) : new NullCache();

    $service = new TransportationService($cache, new SoapClientBuilder());
    $set = $service->fetch($params['supplier'], $params['activity']);

    ResponseFormatter::success([
        'transportation' => $set->toArray(),
    ]);
} catch (Throwable $exception) {
    ErrorHandler::handle($exception);
}
