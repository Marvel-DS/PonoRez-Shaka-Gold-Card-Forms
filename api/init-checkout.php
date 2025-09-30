<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\DTO\CheckoutInitRequest;
use PonoRez\SGCForms\Services\CheckoutInitService;
use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\Support\ErrorHandler;
use PonoRez\SGCForms\Support\RequestValidator;
use PonoRez\SGCForms\Support\ResponseFormatter;

try {
    $input = file_get_contents('php://input') ?: '';
    $payload = $input !== '' ? json_decode($input, true) : $_POST;
    if (!is_array($payload)) {
        throw new InvalidArgumentException('Invalid checkout payload.');
    }

    $params = RequestValidator::requireParams($payload, ['supplier', 'activity', 'date', 'timeslotId']);

    $guestCounts = isset($payload['guestCounts']) && is_array($payload['guestCounts']) ? $payload['guestCounts'] : [];
    $upgrades = isset($payload['upgrades']) && is_array($payload['upgrades']) ? $payload['upgrades'] : [];
    $contact = isset($payload['contact']) && is_array($payload['contact']) ? $payload['contact'] : [];
    $checklist = isset($payload['checklist']) && is_array($payload['checklist']) ? $payload['checklist'] : [];
    $transportationRouteId = isset($payload['transportationRouteId']) && $payload['transportationRouteId'] !== ''
        ? (string) $payload['transportationRouteId']
        : null;
    $metadata = isset($payload['metadata']) && is_array($payload['metadata']) ? $payload['metadata'] : [];

    $request = new CheckoutInitRequest(
        $params['supplier'],
        $params['activity'],
        $params['date'],
        $params['timeslotId'],
        $guestCounts,
        $upgrades,
        $contact,
        $transportationRouteId,
        $checklist,
        $metadata
    );

    $service = new CheckoutInitService(new SoapClientBuilder());
    $response = $service->initiate($request);

    ResponseFormatter::success([
        'checkout' => $response->toArray(),
    ]);
} catch (Throwable $exception) {
    ErrorHandler::handle($exception);
}
