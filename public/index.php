<?php

declare(strict_types=1);

use PonoRez\SGCForms\Cache\FileCache;
use PonoRez\SGCForms\Cache\NullCache;
use PonoRez\SGCForms\Services\ActivityInfoService;
use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\UtilityService;

$requestUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

/**
 * Handle API routing while the document root remains \`public/\`.
 */
function route_api_request(string $uri): void
{
    $apiRoot = realpath(__DIR__ . '/../api');
    if ($apiRoot === false) {
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'API root not found.']);
        exit;
    }

    $relative = ltrim(substr($uri, strlen('/api/')), '/');
    if ($relative === '' || strpos($relative, '..') !== false) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found.']);
        exit;
    }

    $scriptPath = realpath($apiRoot . '/' . $relative);
    if ($scriptPath === false || !str_starts_with($scriptPath, $apiRoot) || !is_file($scriptPath)) {
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found.']);
        exit;
    }

    require $scriptPath;
    exit;
}

function format_activity_info_content(?string $value): ?string
{
    if ($value === null) {
        return null;
    }

    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }

    if ($trimmed !== strip_tags($trimmed)) {
        return $trimmed;
    }

    return nl2br(htmlspecialchars($trimmed, ENT_QUOTES, 'UTF-8'));
}

function merge_activity_info_blocks(array $infoBlocks, array $activityInfo): array
{
    $mapping = [
        'description' => 'description',
        'notes' => 'notes',
        'directions' => 'directions',
    ];

    foreach ($mapping as $blockKey => $infoKey) {
        $content = format_activity_info_content($activityInfo[$infoKey] ?? null);
        if ($content === null) {
            continue;
        }

        $infoBlocks[$blockKey] = is_array($infoBlocks[$blockKey] ?? null)
            ? $infoBlocks[$blockKey]
            : [];

        if (($infoBlocks[$blockKey]['enabled'] ?? true) === false) {
            continue;
        }

        if (empty($infoBlocks[$blockKey]['content'])) {
            $infoBlocks[$blockKey]['content'] = $content;
        }

        if (!array_key_exists('enabled', $infoBlocks[$blockKey])) {
            $infoBlocks[$blockKey]['enabled'] = true;
        }
    }

    return $infoBlocks;
}

if (preg_match('#^/api/(.+)$#', $requestUri)) {
    route_api_request($requestUri);
}

require dirname(__DIR__) . '/controller/Setup.php';

const DEFAULT_SUPPLIER_SLUG = 'supplier-slug';
const DEFAULT_ACTIVITY_SLUG = 'activity-slug';

/**
 * Sanitize incoming slug parameters to prevent directory traversal and
 * keep the generated API URLs stable.
 */
function sanitize_slug(?string $value, string $fallback): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return $fallback;
    }

    $sanitized = preg_replace('/[^a-z0-9\-]+/i', '-', $value);
    $sanitized = trim((string) $sanitized, '-');

    return $sanitized !== '' ? strtolower($sanitized) : $fallback;
}

$supplierSlug = sanitize_slug($_GET['supplier'] ?? null, DEFAULT_SUPPLIER_SLUG);
$activitySlug = sanitize_slug($_GET['activity'] ?? null, DEFAULT_ACTIVITY_SLUG);

try {
    $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
    $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);
} catch (Throwable $exception) {
    http_response_code(404);
    $errorMessage = $exception->getMessage();
    include dirname(__DIR__) . '/partials/layout/error.php';
    exit;
}

$activityInfoResult = [
    'activities' => [],
    'checkedAt' => null,
    'hash' => null,
];

try {
    $cacheDirectory = UtilityService::projectRoot() . '/cache/activity-info';
    $cache = is_writable(dirname($cacheDirectory))
        ? new FileCache($cacheDirectory)
        : new NullCache();
    $activityInfoService = new ActivityInfoService($cache, new SoapClientBuilder());
    $activityInfoResult = $activityInfoService->getActivityInfo($supplierSlug, $activitySlug);
} catch (Throwable) {
    $activityInfoResult = [
        'activities' => [],
        'checkedAt' => null,
        'hash' => null,
    ];
}

$activityInfoById = is_array($activityInfoResult['activities'] ?? null)
    ? $activityInfoResult['activities']
    : [];

$primaryActivityId = isset($activityConfig['activityId']) && is_numeric($activityConfig['activityId'])
    ? (int) $activityConfig['activityId']
    : null;

$primaryActivityInfo = null;
if ($primaryActivityId !== null && isset($activityInfoById[(string) $primaryActivityId])) {
    $primaryActivityInfo = $activityInfoById[(string) $primaryActivityId];
} elseif ($activityInfoById !== []) {
    $firstActivity = reset($activityInfoById);
    if (is_array($firstActivity)) {
        $primaryActivityInfo = $firstActivity;
    }
}

$infoBlocksFromConfig = $activityConfig['infoBlocks'] ?? [];
if (is_array($infoBlocksFromConfig) && $primaryActivityInfo !== null) {
    $infoBlocksFromConfig = merge_activity_info_blocks($infoBlocksFromConfig, $primaryActivityInfo);
}

$activityDetails = [];
if (is_array($primaryActivityInfo)) {
    foreach ([
        'id' => $primaryActivityId,
        'name' => $primaryActivityInfo['name'] ?? null,
        'island' => $primaryActivityInfo['island'] ?? null,
        'times' => $primaryActivityInfo['times'] ?? null,
        'startTimeMinutes' => $primaryActivityInfo['startTimeMinutes'] ?? null,
        'transportationMandatory' => $primaryActivityInfo['transportationMandatory'] ?? null,
        'description' => $primaryActivityInfo['description'] ?? null,
        'notes' => $primaryActivityInfo['notes'] ?? null,
        'directions' => $primaryActivityInfo['directions'] ?? null,
    ] as $key => $value) {
        if ($value === null) {
            continue;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                continue;
            }
            $activityDetails[$key] = $trimmed;
            continue;
        }

        $activityDetails[$key] = $value;
    }
}

$brandingConfig = $supplierConfig['branding'] ?? [];
$branding = [
    'primaryColor' => $brandingConfig['primaryColor'] ?? '#1C55DB',
    'secondaryColor' => $brandingConfig['secondaryColor'] ?? '#0B2E8F',
    'logo' => null,
];

if (!empty($brandingConfig['logo'])) {
    $branding['logo'] = sprintf(
        '/suppliers/%s/%s',
        $supplierSlug,
        ltrim((string) $brandingConfig['logo'], '/')
    );
}

$apiEndpoints = [
    'guestTypes' => sprintf(
        '/api/get-guest-types.php?supplier=%s&activity=%s',
        rawurlencode($supplierSlug),
        rawurlencode($activitySlug)
    ),
    'availability' => sprintf(
        '/api/get-availability.php?supplier=%s&activity=%s',
        rawurlencode($supplierSlug),
        rawurlencode($activitySlug)
    ),
    'availabilityMessages' => sprintf(
        '/api/get-availability-messages.php?supplier=%s&activity=%s',
        rawurlencode($supplierSlug),
        rawurlencode($activitySlug)
    ),
    'transportation' => sprintf(
        '/api/get-transportation.php?supplier=%s&activity=%s',
        rawurlencode($supplierSlug),
        rawurlencode($activitySlug)
    ),
    'upgrades' => sprintf(
        '/api/get-upgrades.php?supplier=%s&activity=%s',
        rawurlencode($supplierSlug),
        rawurlencode($activitySlug)
    ),
    'initCheckout' => '/api/init-checkout.php',
];

$bootstrapData = [
    'supplier' => [
        'slug' => $supplierSlug,
        'name' => $supplierConfig['supplierName'] ?? ucfirst(str_replace('-', ' ', $supplierSlug)),
        'contact' => $supplierConfig['contact'] ?? [],
        'branding' => $branding,
        'homeLink' => $supplierConfig['homeLink'] ?? null,
    ],
    'activity' => [
        'slug' => $activitySlug,
        'id' => $activityConfig['activityId'] ?? null,
        'ids' => array_values(array_map(
            static fn ($value) => is_numeric($value) ? (int) $value : $value,
            array_filter(
                is_array($activityConfig['activityIds'] ?? null)
                    ? $activityConfig['activityIds']
                    : [$activityConfig['activityId'] ?? null],
                static fn ($value) => $value !== null && $value !== ''
            )
        )),
        'displayName' => $activityConfig['displayName']
            ?? $activityConfig['activityTitle']
            ?? ucfirst(str_replace('-', ' ', $activitySlug)),
        'summary' => $activityConfig['summary'] ?? null,
        'uiLabels' => $activityConfig['uiLabels'] ?? [],
        'infoBlocks' => $infoBlocksFromConfig,
        'transportation' => $activityConfig['transportation'] ?? [],
        'upgrades' => $activityConfig['upgrades'] ?? [],
        'privateActivity' => filter_var($activityConfig['privateActivity'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'departureLabels' => array_reduce(
            array_keys($activityConfig['departureLabels'] ?? []),
            static function (array $carry, $key) use ($activityConfig): array {
                $stringKey = (string) $key;
                $label = $activityConfig['departureLabels'][$key];

                if ($stringKey === '' || $label === null) {
                    return $carry;
                }

                $carry[$stringKey] = (string) $label;
                return $carry;
            },
            []
        ),
        'currency' => [
            'code' => strtoupper((string) ($activityConfig['currency']['code'] ?? 'USD')),
            'symbol' => $activityConfig['currency']['symbol'] ?? '$',
            'locale' => $activityConfig['currency']['locale'] ?? 'en-US',
        ],
        'guestTypes' => [
            'ids' => array_map('strval', $activityConfig['guestTypeIds'] ?? []),
            'labels' => $activityConfig['guestTypeLabels'] ?? [],
            'descriptions' => $activityConfig['ponorezGuestTypeDescriptions'] ?? [],
            'min' => $activityConfig['minGuestCount'] ?? [],
            'max' => $activityConfig['maxGuestCount'] ?? [],
        ],
        'details' => $activityDetails,
    ],
    'api' => $apiEndpoints,
    'environment' => [
        'currentDate' => date('Y-m-d'),
    ],
];

foreach ($activityInfoById as $id => $info) {
    if (!is_array($info) || empty($info['times'])) {
        continue;
    }

    $label = trim((string) $info['times']);
    if ($label === '') {
        continue;
    }

    $bootstrapData['activity']['departureLabels'][(string) $id] = $label;
}

$bootstrapData['activity']['activityInfo'] = [
    'byId' => $activityInfoById,
    'checkedAt' => $activityInfoResult['checkedAt'] ?? null,
    'hash' => $activityInfoResult['hash'] ?? null,
];

$pageContext = [
    'supplierSlug' => $supplierSlug,
    'activitySlug' => $activitySlug,
    'supplier' => $supplierConfig,
    'activity' => $activityConfig,
    'branding' => $branding,
    'apiEndpoints' => $apiEndpoints,
    'bootstrap' => $bootstrapData,
    'activityInfo' => $activityInfoById,
];

include dirname(__DIR__) . '/partials/layout/form-basic.php';
