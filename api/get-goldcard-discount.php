<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\Services\GoldCardDiscountService;
use PonoRez\SGCForms\Support\ErrorHandler;
use PonoRez\SGCForms\Support\RequestValidator;
use PonoRez\SGCForms\Support\ResponseFormatter;

try {
    $input = file_get_contents('php://input') ?: '';
    $payload = $input !== '' ? json_decode($input, true) : $_POST;
    if (!is_array($payload)) {
        $payload = [];
    }

    $query = [];
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if ($requestUri !== '' && str_contains($requestUri, '?')) {
        $queryString = parse_url($requestUri, PHP_URL_QUERY);
        if (is_string($queryString)) {
            parse_str($queryString, $query);
        }
    }
    $carrier = array_merge($query, $payload);
    $params = RequestValidator::requireParams($carrier, ['supplier', 'activity']);

    $numbers = [];
    if (isset($payload['numbers']) && is_array($payload['numbers'])) {
        $numbers = $payload['numbers'];
    } elseif (isset($payload['goldCardNumbers']) && is_array($payload['goldCardNumbers'])) {
        $numbers = $payload['goldCardNumbers'];
    } elseif (isset($payload['goldCardNumber'])) {
        $numbers = [$payload['goldCardNumber']];
    } elseif (isset($payload['number'])) {
        $numbers = [$payload['number']];
    }

    $context = [
        'date' => $payload['date'] ?? $payload['travelDate'] ?? null,
        'timeslotId' => $payload['timeslotId'] ?? $payload['departureId'] ?? null,
        'guestCounts' => isset($payload['guestCounts']) && is_array($payload['guestCounts'])
            ? $payload['guestCounts']
            : [],
        'transportationRouteId' => $payload['transportationRouteId'] ?? null,
        'upgrades' => isset($payload['upgrades']) && is_array($payload['upgrades'])
            ? $payload['upgrades']
            : [],
        'buyGoldCard' => !empty($payload['buyGoldCard']),
        'policyAccepted' => !empty($payload['policyAccepted']),
        'payLater' => array_key_exists('payLater', $payload) ? (bool) $payload['payLater'] : null,
        'referer' => isset($payload['referer']) && is_string($payload['referer']) ? $payload['referer'] : null,
    ];

    $service = new GoldCardDiscountService();
    $result = $service->fetch(
        (string) $params['supplier'],
        (string) $params['activity'],
        $numbers,
        $context
    );

    ResponseFormatter::success([
        'discount' => [
            'code' => $result['code'],
            'numbers' => $result['numbers'],
            'source' => $result['source'] ?? 'ponorez',
            'message' => $result['message'] ?? null,
        ],
        'raw' => $result['raw'] ?? null,
    ]);
} catch (Throwable $exception) {
    ErrorHandler::handle($exception);
}
