<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\Services\UpgradeService;
use PonoRez\SGCForms\Support\ErrorHandler;
use PonoRez\SGCForms\Support\RequestValidator;
use PonoRez\SGCForms\Support\ResponseFormatter;
use PonoRez\SGCForms\UtilityService;

try {
    $params = RequestValidator::requireParams($_GET, ['supplier', 'activity']);

    $activityConfig = UtilityService::loadActivityConfig($params['supplier'], $params['activity']);
    if (!empty($activityConfig['disableUpgrades'])) {
        ResponseFormatter::success([
            'upgrades' => [],
        ]);
        return;
    }

    $cache = UtilityService::createCache('cache/upgrades');
    $service = new UpgradeService($cache, new SoapClientBuilder());
    $collection = $service->fetch($params['supplier'], $params['activity']);

    ResponseFormatter::success([
        'upgrades' => $collection->toArray(),
    ]);
} catch (Throwable $exception) {
    ErrorHandler::handle($exception);
}
