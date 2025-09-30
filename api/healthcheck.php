<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';
use PonoRez\SGCForms\Cache\NullCache;
use PonoRez\SGCForms\Services\GuestTypeService;
use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\Support\ErrorHandler;
use PonoRez\SGCForms\Support\ResponseFormatter;
use PonoRez\SGCForms\UtilityService;

try {
    // Load environment configuration to ensure config files are readable.
    UtilityService::getEnvConfig();

    $payload = [
        'status' => 'ok',
        'timestamp' => (new \DateTimeImmutable())->format(\DateTimeImmutable::ATOM),
        'environment' => UtilityService::getCurrentEnvironment(),
    ];

    $supplier = trim($_GET['supplier'] ?? '');
    $activity = trim($_GET['activity'] ?? '');

    if ($supplier !== '' && $activity !== '') {
        $service = new GuestTypeService(new NullCache(), new SoapClientBuilder());
        $date = $_GET['date'] ?? (new \DateTimeImmutable())->format('Y-m-d');
        $guestTypes = $service->fetch($supplier, $activity, $date);
        $payload['guestTypeCount'] = $guestTypes->count();
    }

    ResponseFormatter::success($payload);
} catch (Throwable $exception) {
    ErrorHandler::handle($exception);
}
