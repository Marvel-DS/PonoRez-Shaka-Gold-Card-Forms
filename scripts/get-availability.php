<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\UtilityService;

if ($argc < 4) {
    fwrite(STDERR, "Usage: php scripts/get-availability.php <supplier-slug> <activity-id> <start-date>\n");
    exit(1);
}

[, $supplierSlug, $activityId, $startDate] = $argv;

$supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);

$params = [
    'serviceLogin' => [
        'username' => $supplierConfig['soapCredentials']['username'],
        'password' => $supplierConfig['soapCredentials']['password'],
    ],
    'supplierId' => $supplierConfig['supplierId'],
    'activityId' => (int) $activityId,
    'startDate' => $startDate,
];

try {
    $client = (new SoapClientBuilder())->build();
    $response = $client->__soapCall('getActivityAvailableDates', [$params]);
    var_dump($response);
} catch (Throwable $exception) {
    echo 'SOAP fault: ' . $exception->getMessage() . "\n";
}
