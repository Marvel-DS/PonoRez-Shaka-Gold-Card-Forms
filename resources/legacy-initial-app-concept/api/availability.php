<?php
require_once __DIR__ . '/../vendor/autoload.php';

$envConfigPath = realpath(__DIR__ . '/../config/env.json');
$envConfig = $envConfigPath && file_exists($envConfigPath)
    ? json_decode(file_get_contents($envConfigPath), true)
    : [];

use PonoRez\SGCForms\ApiService;
use PonoRez\SGCForms\UtilityService;

// Return JSON
header('Content-Type: application/json');

// Get raw POST body
$raw = file_get_contents("php://input");
$data = json_decode($raw, true);

// If no JSON data, check $_GET for parameters
if (!$data) {
    $data = [];
    if (isset($_GET['date'])) {
        $data['date'] = $_GET['date'];
    }
    if (isset($_GET['activityIds'])) {
        if (is_array($_GET['activityIds'])) {
            $data['activityIds'] = $_GET['activityIds'];
        } else {
            $data['activityIds'] = explode(',', $_GET['activityIds']);
        }
    }
    if (isset($_GET['supplierId'])) {
        $data['supplierId'] = $_GET['supplierId'];
    }
    if (isset($_GET['guestCounts'])) {
        $decoded = json_decode($_GET['guestCounts'], true);
        $data['guestCounts'] = $decoded && is_array($decoded) ? $decoded : [];
    }
}

// Normalize and validate input parameters

// Normalize date to MM/DD/YYYY
if (empty($data['date'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameter: date'
    ]);
    exit;
} else {
    $dateObj = date_create($data['date']);
    if (!$dateObj) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid date format'
        ]);
        exit;
    }
    $date = $dateObj->format('m/d/Y');
}

// Normalize activityIds to array
if (empty($data['activityIds'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required parameter: activityIds'
    ]);
    exit;
} else {
    if (is_array($data['activityIds'])) {
        $activityIds = $data['activityIds'];
    } else {
        $activityIds = [$data['activityIds']];
    }
    // Remove empty values
    $activityIds = array_filter($activityIds, function($val) {
        return !empty($val);
    });
    if (empty($activityIds)) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'activityIds cannot be empty'
        ]);
        exit;
    }
}

// Normalize guestCounts to array
$guestCounts = [];
if (isset($data['guestCounts']) && is_array($data['guestCounts'])) {
    $guestCounts = $data['guestCounts'];
} else {
    $guestCounts = [];
}

if (empty($guestCounts) || !is_array($guestCounts)) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing guestCounts (must include guest type IDs and counts)'
    ]);
    exit;
}

// Normalize guestCounts values to integers and validate they are >= 0
foreach ($guestCounts as $key => $value) {
    if (!is_numeric($value) || (int)$value < 0) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Invalid guestCounts value for guest type ID ' . $key . '. Counts must be integers >= 0.'
        ]);
        exit;
    }
    $guestCounts[$key] = (int)$value;
}

// For now, assume supplier is fixed (each form can pass its supplierId later)
$supplierId = $data['supplierId'] ?? null;
if (!$supplierId) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing supplierId'
    ]);
    exit;
}

// Load supplier credentials
$supplierDir = null;
$supplierConfig = null;

// Determine if supplierId is numeric (config id) or string (folder slug)
if (is_numeric($supplierId)) {
    // Search suppliers/*/supplier.json for a matching supplierId field
    $supplierFolders = glob(__DIR__ . '/../suppliers/*', GLOB_ONLYDIR);
    foreach ($supplierFolders as $folder) {
        $configFile = $folder . '/supplier.json';
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if (isset($config['supplierId']) && (string)$config['supplierId'] === (string)$supplierId) {
                $supplierDir = realpath($folder);
                $supplierConfig = $config;
                break;
            }
        }
    }
} else {
    // Treat as slug/folder name
    $possibleDir = realpath(__DIR__ . "/../suppliers/{$supplierId}");
    if ($possibleDir && is_dir($possibleDir)) {
        $configFile = $possibleDir . '/supplier.json';
        if (file_exists($configFile)) {
            $supplierDir = $possibleDir;
            $supplierConfig = json_decode(file_get_contents($configFile), true);
        }
    }
}

if (!$supplierDir || !$supplierConfig) {
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Supplier not found or configuration missing'
    ]);
    exit;
}

// Validate supplier config
try {
    UtilityService::validateConfig($supplierConfig, [
        'supplierUsername',
        'supplierPassword'
    ]);
} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid supplier configuration: ' . $e->getMessage()
    ]);
    exit;
}

$username   = $supplierConfig['supplierUsername'];
$password   = $supplierConfig['supplierPassword'];
$production = $envConfig['production'] ?? false;

$api = new ApiService($username, $password, $production);

// Collect available timeslots and guest types for all requested activities
$timeslots     = [];
$guestTypesData = [];

foreach ($activityIds as $activityId) {
    try {
        $guestTypes = $api->getActivityGuestTypes($supplierId, $activityId, $date, $guestCounts);

        // Debug log raw guestTypes
        UtilityService::log(json_encode($guestTypes), "debug");

        // Map into guestTypesData list
        foreach ($guestTypes as $gt) {
            $guestTypesData[] = [
                'id'           => $gt['id'] ?? null,
                'name'         => $gt['name'] ?? ($gt['label'] ?? 'Guest'),
                'price'        => $gt['price']
                                    ?? $gt['perGuestPrice']
                                    ?? $gt['retailPrice']
                                    ?? $gt['amount']
                                    ?? null,
                'availability' => $gt['availabilityPerGuest'] ?? null,
            ];
        }
    } catch (\Exception $e) {
        UtilityService::log("Availability error: " . $e->getMessage(), "error");
    }
}

if (empty($guestTypesData)) {
    http_response_code(200);
    echo json_encode([
        'status'     => 'ok',
        'date'       => $date,
        'guestTypes' => [],
        'timeslots'  => [],
        'message'    => 'No availability for this date'
    ]);
} else {
    http_response_code(200);
    echo json_encode([
        'status'     => 'ok',
        'date'       => $date,
        'guestTypes' => $guestTypesData,
        'timeslots'  => $timeslots,
    ]);
}