<?php
/**
 * Endpoint: get-guest-types.php
 *
 * Purpose:
 * Retrieve guest type information (IDs, labels, descriptions, prices)
 * for a given activity and date. Keeps it lightweight compared to
 * full availability calls.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PonoRez\SGCForms\UtilityService;

// -------------------------------
// 1. Load request parameters
// -------------------------------
$activityId = $_GET['activityId'] ?? null;
$date       = $_GET['date'] ?? null;

if (!$activityId || !$date) {
    http_response_code(400);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Missing required parameters: activityId and date'
    ]);
    exit;
}

// -------------------------------
// 2. Load configuration
// -------------------------------
try {
    // Load environment config for endpoint only
    $envConfig = UtilityService::loadConfig(__DIR__ . '/../config/env.config');

    // Determine environment
    $environment = $envConfig['environment'] ?? 'production';
    if (!isset($envConfig[$environment]['soapWsdl'])) {
        throw new RuntimeException("Environment config missing soapWsdl for environment: {$environment}");
    }

    $soapUrl = $envConfig[$environment]['soapWsdl'];

    // Require supplier query parameter
    $supplier = $_GET['supplier'] ?? null;
    if (!$supplier) {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Missing required parameter: supplier'
        ]);
        exit;
    }

    // Build supplier config path
    $supplierConfigPath = __DIR__ . "/../suppliers/{$supplier}/supplier.config";
    if (!file_exists($supplierConfigPath)) {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => "Supplier config not found for supplier: {$supplier}"
        ]);
        exit;
    }

    // Load supplier config for SOAP credentials (support both new and legacy keys)
    $supplierConfig = UtilityService::loadConfig($supplierConfigPath);
    if (
        !empty($supplierConfig['soap']) &&
        !empty($supplierConfig['soap']['username']) &&
        !empty($supplierConfig['soap']['password'])
    ) {
        $username = $supplierConfig['soap']['username'];
        $password = $supplierConfig['soap']['password'];
    } elseif (
        !empty($supplierConfig['supplierUsername']) &&
        !empty($supplierConfig['supplierPassword'])
    ) {
        $username = $supplierConfig['supplierUsername'];
        $password = $supplierConfig['supplierPassword'];
    } else {
        http_response_code(500);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Supplier config missing SOAP credentials'
        ]);
        exit;
    }

    // Require activity query parameter
    $activity = $_GET['activity'] ?? null;
    if (!$activity) {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Missing required parameter: activity'
        ]);
        exit;
    }

    // Build activity config path
    $activityConfigPath = __DIR__ . "/../suppliers/{$supplier}/{$activity}/activity.config";
    if (!file_exists($activityConfigPath)) {
        http_response_code(400);
        echo json_encode([
            'status'  => 'error',
            'message' => "Activity config not found for activity: {$activity}"
        ]);
        exit;
    }

    // Load activity config
    $activityConfig = UtilityService::loadConfig($activityConfigPath);

    // Extract guestTypeIds, guestTypeLabels, guestTypeInfo from activity config if present
    $guestTypeIds = $activityConfig['guestTypeIds'] ?? null;
    $guestTypeLabels = $activityConfig['guestTypeLabels'] ?? null;
    $guestTypeInfo = $activityConfig['guestTypeInfo'] ?? null;

} catch (RuntimeException $e) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'Configuration error: ' . $e->getMessage()
    ]);
    exit;
}

// -------------------------------
// 3a. Cache setup
// -------------------------------
$cacheDir = __DIR__ . '/../cache/guestTypes';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0775, true); // create cache dir if it doesn't exist
}

$cacheKeyRaw = sprintf(
    'guestTypes_%s_%s_%s_%s',
    $supplier,
    $activity,
    $activityId,
    $date
);
$cacheKey = preg_replace('/[^A-Za-z0-9_.-]/', '_', $cacheKeyRaw);
$cacheFile = "{$cacheDir}/{$cacheKey}.json";

// Optional: simple cache (file-based, 15 min)
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 900)) {
    // Serve from cache if not expired
    echo file_get_contents($cacheFile);
    exit;
}

// Build SOAP request XML using credentials from supplier config
$requestXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://hawaiifun.org/reservation/services/2012-05-10/SupplierService">
  <SOAP-ENV:Body>
    <ns1:getActivityGuestTypes>
      <serviceLogin username="{$username}" password="{$password}" />
      <activityId>{$activityId}</activityId>
      <date>{$date}</date>
    </ns1:getActivityGuestTypes>
  </SOAP-ENV:Body>
</SOAP-ENV:Envelope>
XML;

// -------------------------------
// 4. Execute SOAP call via cURL
// -------------------------------
$ch = curl_init($soapUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: text/xml; charset=utf-8",
    "SOAPAction: \"\""
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, $requestXml);

$response = curl_exec($ch);
if (curl_errno($ch)) {
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'message' => 'cURL Error: ' . curl_error($ch)
    ]);
    curl_close($ch);
    exit;
}
curl_close($ch);

// -------------------------------
// 5. Parse response
// -------------------------------
$guestTypes = [];
if ($response) {
    $xml = simplexml_load_string($response, null, 0, 'http://schemas.xmlsoap.org/soap/envelope/');
    if ($xml !== false) {
        $xml->registerXPathNamespace('ns2', 'http://hawaiifun.org/reservation/services/2012-05-10/SupplierService');
        $returns = $xml->xpath('//ns2:getActivityGuestTypesResponse/return');

        foreach ($returns as $item) {
            // Support both attribute and element form of id
            $idAttr = $item->attributes();
            $id     = isset($idAttr['id']) ? (string)$idAttr['id'] : (string)$item->id;

            $guestTypes[] = [
                'id'          => $id,
                'name'        => (string)$item->name,
                'description' => (string)$item->description,
                'price'       => number_format((float)$item->price, 2),
                'availabilityPerGuest' => (int)$item->availabilityPerGuest,
                'noChargesApplied'     => ((string)$item->noChargesApplied === 'true')
            ];
        }
    }
}

// Filter and enrich guestTypes based on activity config
if (is_array($guestTypeIds)) {
    // Normalize all IDs to strings for reliable matching
    $normalizedIds = array_map('strval', $guestTypeIds);

    $guestTypes = array_filter($guestTypes, function($gt) use ($normalizedIds) {
        return in_array((string)$gt['id'], $normalizedIds, true);
    });

    // Reindex array keys
    $guestTypes = array_values($guestTypes);
}

if (is_array($guestTypeLabels) || is_array($guestTypeInfo)) {
    foreach ($guestTypes as &$gt) {
        if (is_array($guestTypeLabels) && isset($guestTypeLabels[$gt['id']])) {
            $gt['name'] = $guestTypeLabels[$gt['id']];
        }
        if (is_array($guestTypeInfo) && isset($guestTypeInfo[$gt['id']])) {
            $gt['description'] = $guestTypeInfo[$gt['id']];
        }
    }
    unset($gt);
}

// -------------------------------
// 6. Output + cache
// -------------------------------
$output = [
    'status'     => 'ok',
    'activityId' => $activityId,
    'date'       => $date,
    'guestTypes' => $guestTypes
];

$json = json_encode($output, JSON_PRETTY_PRINT);
file_put_contents($cacheFile, $json);

header('Content-Type: application/json');
echo $json;
