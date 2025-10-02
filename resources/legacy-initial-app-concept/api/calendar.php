

<?php
require_once __DIR__ . '/../controller/ApiService.php';

// Load env config for limitedThreshold
$envConfigPath = realpath(__DIR__ . '/../config/env.json');
$envConfig = $envConfigPath && file_exists($envConfigPath)
    ? json_decode(file_get_contents($envConfigPath), true)
    : [];
$limitedThreshold = $envConfig['limitedThreshold'] ?? 5;

use PonoRez\SGCForms\ApiService;

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$supplierId  = $data['supplierId'] ?? null;
$activityIds = $data['activityIds'] ?? [];
$year        = (int)($data['year'] ?? date('Y'));
$month       = (int)($data['month'] ?? date('m'));

if (!$supplierId || empty($activityIds)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing supplierId or activityIds']);
    exit;
}

try {
    $api = new ApiService();

    // Days in month
    $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
    $availabilityMap = [];

    for ($day = 1; $day <= $daysInMonth; $day++) {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $dayStatus = 'sold_out';
        $dayTotal = 0;

        foreach ($activityIds as $activityId) {
            try {
                $slots = $api->getActivityGuestTypes($supplierId, $activityId, $date);
            } catch (Exception $e) {
                $slots = [];
            }

            if (!empty($slots)) {
                $totalAvailable = 0;
                foreach ($slots as $slot) {
                    $totalAvailable += (int)($slot['availabilityPerGuest'] ?? 0);
                }
                $dayTotal += $totalAvailable;
            }
        }

        if ($dayTotal <= 0) {
            $dayStatus = 'sold_out';
        } elseif ($dayTotal < $limitedThreshold) {
            $dayStatus = 'limited';
        } else {
            $dayStatus = 'available';
        }

        $availabilityMap[$date] = $dayStatus;
    }

    echo json_encode([
        'status' => 'ok',
        'supplierId' => $supplierId,
        'activityIds' => $activityIds,
        'month' => sprintf('%04d-%02d', $year, $month),
        'days' => $availabilityMap
    ]);
} catch (Exception $e) {
    // Always return status ok with partial data if possible
    echo json_encode([
        'status' => 'ok',
        'supplierId' => $supplierId,
        'activityIds' => $activityIds,
        'month' => sprintf('%04d-%02d', $year, $month),
        'days' => $availabilityMap ?? [],
        'error' => $e->getMessage()
    ]);
}