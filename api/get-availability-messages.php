<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\Services\AvailabilityMessagingService;
use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\Support\ErrorHandler;
use PonoRez\SGCForms\Support\RequestValidator;
use PonoRez\SGCForms\Support\ResponseFormatter;

try {
    $params = RequestValidator::requireParams($_GET, ['supplier', 'activity']);

    $guestCounts = [];
    if (isset($_GET['guestCounts'])) {
        $guestCounts = RequestValidator::optionalJson((string) $_GET['guestCounts'], 'guestCounts');
    }

    $timeslotRequests = [];
    if (isset($_GET['timeslots'])) {
        $rawTimeslots = $_GET['timeslots'];
        if (is_string($rawTimeslots)) {
            $decoded = RequestValidator::optionalJson($rawTimeslots, 'timeslots');
            if (is_array($decoded)) {
                $timeslotRequests = $decoded;
            }
        } elseif (is_array($rawTimeslots)) {
            $timeslotRequests = $rawTimeslots;
        }
    }

    $date = isset($_GET['date']) ? trim((string) $_GET['date']) : '';

    if ($timeslotRequests === []) {
        $activityIds = [];

        if (isset($_GET['activityIds'])) {
            $rawActivityIds = $_GET['activityIds'];
            if (is_string($rawActivityIds)) {
                $trimmed = trim($rawActivityIds);
                if ($trimmed !== '') {
                    if ($trimmed[0] === '[') {
                        $decoded = RequestValidator::optionalJson($trimmed, 'activityIds');
                        if (is_array($decoded)) {
                            $activityIds = $decoded;
                        }
                    } else {
                        $activityIds = array_filter(array_map('trim', explode(',', $trimmed)), static fn ($value) => $value !== '');
                    }
                }
            } elseif (is_array($rawActivityIds)) {
                $activityIds = $rawActivityIds;
            }
        }

        if ($activityIds === []) {
            throw new \InvalidArgumentException('At least one activityId must be provided.');
        }

        if ($date === '') {
            throw new \InvalidArgumentException('Parameter "date" is required when timeslots are not provided.');
        }

        foreach ($activityIds as $activityId) {
            $timeslotRequests[] = [
                'activityId' => $activityId,
                'date' => $date,
            ];
        }
    }

    $service = new AvailabilityMessagingService(new SoapClientBuilder());
    $result = $service->probeTimeslots(
        (string) $params['supplier'],
        (string) $params['activity'],
        $timeslotRequests,
        is_array($guestCounts) ? $guestCounts : []
    );

    ResponseFormatter::success([
        'messages' => $result['messages'],
        'metadata' => [
            'requestedSeats' => $result['requestedSeats'],
        ],
    ]);
} catch (\Throwable $exception) {
    ErrorHandler::handle($exception);
}
