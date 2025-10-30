<?php

declare(strict_types=1);

use PonoRez\SGCForms\Cache\FileCache;
use PonoRez\SGCForms\Cache\NullCache;
use PonoRez\SGCForms\Services\ActivityInfoService;
use PonoRez\SGCForms\DTO\TransportationSet;
use PonoRez\SGCForms\Services\GuestTypeService;
use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\Services\TransportationService;
use PonoRez\SGCForms\Services\UpgradeService;
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

function serve_supplier_asset(string $uri): bool
{
    $relative = ltrim(substr($uri, strlen('/suppliers/')), '/');

    if ($relative === '' || str_contains($relative, '..') || str_contains($relative, "\0")) {
        http_response_code(404);
        return true;
    }

    if (!str_contains($relative, '.')) {
        return false;
    }

    $baseDirectory = realpath(__DIR__ . '/../suppliers');
    if ($baseDirectory === false) {
        return false;
    }

    $fullPath = $baseDirectory . '/' . $relative;
    $realPath = realpath($fullPath);

    if ($realPath === false || !str_starts_with($realPath, $baseDirectory) || !is_file($realPath)) {
        return false;
    }

    $extension = strtolower(pathinfo($realPath, PATHINFO_EXTENSION));
    $mimeTypes = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'svg' => 'image/svg+xml',
        'json' => 'application/json',
        'txt' => 'text/plain; charset=UTF-8',
    ];

    $mime = $mimeTypes[$extension] ?? 'application/octet-stream';

    header('Content-Type: ' . $mime);
    header('Cache-Control: public, max-age=86400');

    $stream = fopen($realPath, 'rb');
    if ($stream === false) {
        http_response_code(500);
        return true;
    }

    fpassthru($stream);
    fclose($stream);
    return true;
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

if (preg_match('#^/suppliers/#', $requestUri) && serve_supplier_asset($requestUri)) {
    exit;
}

if (preg_match('#^/api/(.+)$#', $requestUri)) {
    route_api_request($requestUri);
}

if (!isset($_GET['supplier'])) {
    if (preg_match('#^/suppliers/([a-z0-9\-]+)/([a-z0-9\-]+)/?$#i', $requestUri, $matches)) {
        $_GET['supplier'] = strtolower($matches[1]);
        $_GET['activity'] = strtolower($matches[2]);
    } elseif (preg_match('#^/suppliers/([a-z0-9\-]+)/?$#i', $requestUri, $matches)) {
        $_GET['supplier'] = strtolower($matches[1]);
    }
}

require dirname(__DIR__) . '/controller/Setup.php';

const DEFAULT_SUPPLIER_SLUG = 'supplier-slug';
const DEFAULT_ACTIVITY_SLUG = 'activity-slug';

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

$requestedSupplier = $_GET['supplier'] ?? null;
$requestedActivity = $_GET['activity'] ?? null;

$supplierSlug = $requestedSupplier !== null
    ? sanitize_slug($requestedSupplier, DEFAULT_SUPPLIER_SLUG)
    : DEFAULT_SUPPLIER_SLUG;

$isSupplierOverview = $requestedSupplier !== null
    && ($requestedActivity === null || trim((string) $requestedActivity) === '');

$activitySlug = null;
if ($requestedActivity !== null && trim((string) $requestedActivity) !== '') {
    $activitySlug = sanitize_slug($requestedActivity, DEFAULT_ACTIVITY_SLUG);
} elseif (!$isSupplierOverview) {
    $activitySlug = DEFAULT_ACTIVITY_SLUG;
}

try {
    $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
} catch (Throwable $exception) {
    http_response_code(404);
    $errorMessage = $exception->getMessage();
    include dirname(__DIR__) . '/partials/layout/error.php';
    exit;
}

$publicBasePath = UtilityService::getPublicBasePath();

if ($isSupplierOverview) {
    $supplierDirectory = UtilityService::supplierDirectory($supplierSlug);
    $activityFiles = glob($supplierDirectory . '/*.config') ?: [];

    $activityEntries = [];
    foreach ($activityFiles as $file) {
        $basename = basename($file);
        if (strcasecmp($basename, 'supplier.config') === 0) {
            continue;
        }

        $slug = strtolower((string) preg_replace('/\.config$/i', '', $basename));
        if ($slug === '') {
            continue;
        }

        try {
            $config = UtilityService::loadActivityConfig($supplierSlug, $slug);
        } catch (Throwable) {
            continue;
        }

        $displayName = $config['displayName'] ?? ucfirst(str_replace('-', ' ', $slug));
        $summary = $config['summary'] ?? null;
        $gallery = UtilityService::getActivityGalleryImages($supplierSlug, $config);
        $image = $gallery[0]['src'] ?? ($publicBasePath . 'assets/images/activity-cover-placeholder.jpg');

        $currencyCode = $config['currency']['code'] ?? null;

        $activityEntries[] = [
            'slug' => $slug,
            'displayName' => $displayName,
            'summary' => $summary,
            'url' => $publicBasePath . 'suppliers/' . rawurlencode($supplierSlug) . '/' . rawurlencode($slug),
            'image' => $image,
            'currencyCode' => $currencyCode,
        ];
    }

    usort($activityEntries, static function (array $a, array $b): int {
        return strcasecmp($a['displayName'], $b['displayName']);
    });

    $overviewContext = [
        'supplier' => $supplierConfig,
        'supplierSlug' => $supplierSlug,
        'activities' => $activityEntries,
        'publicBasePath' => $publicBasePath,
    ];

    include dirname(__DIR__) . '/partials/layout/supplier-overview.php';
    exit;
}

if ($activitySlug === null) {
    $activitySlug = DEFAULT_ACTIVITY_SLUG;
}

try {
    $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);
} catch (Throwable $exception) {
    http_response_code(404);
    $errorMessage = $exception->getMessage();
    include dirname(__DIR__) . '/partials/layout/error.php';
    exit;
}

$disableUpgrades = (bool) ($activityConfig['disableUpgrades'] ?? false);
$hasUpgradesKey = array_key_exists('upgrades', $activityConfig);
if (!$hasUpgradesKey) {
    $disableUpgrades = true;
}

if (!$disableUpgrades && !array_key_exists('upgrades', $activityConfig)) {
    $disableUpgrades = true;
    $activityConfig['disableUpgrades'] = true;
    $activityConfig['upgrades'] = [];
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

$currentDate = date('Y-m-d');

$primaryActivityId = UtilityService::getPrimaryActivityId($activityConfig);
$activityIds = UtilityService::getActivityIds($activityConfig);
$activityList = UtilityService::getActivities($activityConfig);
$departureLabels = UtilityService::getDepartureLabels($activityConfig);
$guestTypeCollection = UtilityService::getGuestTypes($activityConfig);
$guestTypesById = UtilityService::getGuestTypesById($activityConfig);

$guestTypeDetailMap = [];

try {
    $guestTypeCacheDirectory = UtilityService::projectRoot() . '/cache/guest-types';
    $guestTypeCache = is_writable(dirname($guestTypeCacheDirectory))
        ? new FileCache($guestTypeCacheDirectory)
        : new NullCache();

    $guestTypeService = new GuestTypeService($guestTypeCache, new SoapClientBuilder());
    $guestTypeCollectionFromPonorez = $guestTypeService->fetch($supplierSlug, $activitySlug, $currentDate);
    $guestTypeArray = $guestTypeCollectionFromPonorez->toArray();

    foreach ($guestTypeArray as $detail) {
        if (!is_array($detail)) {
            continue;
        }

        $id = isset($detail['id']) ? (string) $detail['id'] : '';
        if ($id === '') {
            continue;
        }

        $guestTypeDetailMap[$id] = $detail;
    }

} catch (Throwable) {
    $guestTypeDetailMap = [];
}

$upgradesConfigOriginal = [];
$upgradesFromConfig = [];
if (!$disableUpgrades && $hasUpgradesKey) {
    $configuredUpgrades = is_array($activityConfig['upgrades']) ? $activityConfig['upgrades'] : [];
    if ($configuredUpgrades !== []) {
        $upgradesConfigOriginal = $configuredUpgrades;
        $upgradesFromConfig = $configuredUpgrades;
    } else {
        $upgradesConfigOriginal = $configuredUpgrades;
        try {
            $upgradeCacheDirectory = UtilityService::projectRoot() . '/cache/upgrades';
            $upgradeCache = is_writable(dirname($upgradeCacheDirectory))
                ? new FileCache($upgradeCacheDirectory)
                : new NullCache();

            $upgradeService = new UpgradeService($upgradeCache, new SoapClientBuilder());
            $upgradesCollection = $upgradeService->fetch($supplierSlug, $activitySlug);
            $upgradesFromConfig = $upgradesCollection->toArray();
        } catch (Throwable) {
            // Fall back to any upgrade definitions from the activity config (empty array).
        }
    }
}

$activityConfig['upgradesConfig'] = $disableUpgrades ? [] : $upgradesConfigOriginal;
$activityConfig['upgrades'] = $disableUpgrades ? [] : $upgradesFromConfig;


$normalizeGuestTypeEntry = static function (array $guestType, array $detailMap): ?array {
    if (!isset($guestType['id'])) {
        return null;
    }

    $id = (string) $guestType['id'];
    if ($id === '') {
        return null;
    }

    $detail = $detailMap[$id] ?? null;

    $configLabel = isset($guestType['label']) ? trim((string) $guestType['label']) : '';
    if ($configLabel !== '' && strcasecmp($configLabel, $id) === 0) {
        $configLabel = '';
    }
    $detailLabel = null;
    if (is_array($detail)) {
        foreach ([
            $detail['label'] ?? null,
            $detail['name'] ?? null,
            $detail['guestTypeName'] ?? null,
            $detail['guestType'] ?? null,
        ] as $candidate) {
            if ($candidate === null) {
                continue;
            }

            $trimmed = trim((string) $candidate);
            if ($trimmed !== '') {
                $detailLabel = $trimmed;
                break;
            }
        }
    }

    if ($configLabel !== '') {
        $label = $configLabel;
        $labelSource = 'config';
    } elseif ($detailLabel !== null) {
        $label = $detailLabel;
        $labelSource = 'ponorez';
    } else {
        $label = $id;
        $labelSource = 'fallback';
    }

    $configDescription = isset($guestType['description']) ? trim((string) $guestType['description']) : '';
    $detailDescription = null;
    if (is_array($detail) && array_key_exists('description', $detail)) {
        $detailDescriptionCandidate = trim((string) $detail['description']);
        if ($detailDescriptionCandidate !== '') {
            $detailDescription = $detailDescriptionCandidate;
        }
    }

    if ($configDescription !== '') {
        $description = $configDescription;
        $descriptionSource = 'config';
    } elseif ($detailDescription !== null) {
        $description = $detailDescription;
        $descriptionSource = 'ponorez';
    } else {
        $description = null;
        $descriptionSource = 'fallback';
    }

    $price = null;
    if (is_array($detail) && isset($detail['price']) && is_numeric($detail['price'])) {
        $price = (float) $detail['price'];
    } elseif (isset($guestType['price']) && $guestType['price'] !== '' && is_numeric($guestType['price'])) {
        $price = (float) $guestType['price'];
    }

    $minQuantity = isset($guestType['minQuantity']) ? max(0, (int) $guestType['minQuantity']) : 0;
    $maxQuantity = null;
    if (array_key_exists('maxQuantity', $guestType) && $guestType['maxQuantity'] !== null && $guestType['maxQuantity'] !== '') {
        $maxQuantity = max($minQuantity, (int) $guestType['maxQuantity']);
    }

    return [
        'id' => $id,
        'label' => $label,
        'labelSource' => $labelSource,
        'description' => $description,
        'descriptionSource' => $descriptionSource,
        'price' => $price,
        'minQuantity' => $minQuantity,
        'maxQuantity' => $maxQuantity,
    ];
};

$guestTypeCollectionNormalized = [];
foreach ($guestTypeCollection as $guestType) {
    if (!is_array($guestType)) {
        continue;
    }

    $normalized = $normalizeGuestTypeEntry($guestType, $guestTypeDetailMap);
    if ($normalized === null) {
        continue;
    }

    $guestTypeCollectionNormalized[] = $normalized;
}

$guestTypesByIdNormalized = [];
foreach ($guestTypesById as $guestType) {
    if (!is_array($guestType)) {
        continue;
    }

    $normalized = $normalizeGuestTypeEntry($guestType, $guestTypeDetailMap);
    if ($normalized === null) {
        continue;
    }

    $guestTypesByIdNormalized[$normalized['id']] = $normalized;
}

foreach ($guestTypeCollectionNormalized as $entry) {
    $guestTypesByIdNormalized[$entry['id']] = $entry;
}

$activityConfig['guestTypes']['collection'] = $guestTypeCollectionNormalized;
$activityConfig['guestTypes']['byId'] = $guestTypesByIdNormalized;

$transportationSet = null;
$transportationData = is_array($activityConfig['transportation'] ?? null)
    ? $activityConfig['transportation']
    : [];

try {
    $transportationCacheDirectory = UtilityService::projectRoot() . '/cache/transportation';
    $transportationCache = is_writable(dirname($transportationCacheDirectory))
        ? new FileCache($transportationCacheDirectory)
        : new NullCache();

    $transportationService = new TransportationService($transportationCache, new SoapClientBuilder());
    $transportationSet = $transportationService->fetch($supplierSlug, $activitySlug);
    $transportationData = $transportationSet->toArray();
} catch (Throwable) {
    $transportationSet = null;
}

$normalizeActivityIdentifier = static function (mixed $value): int|string|null {
    if (is_int($value)) {
        return $value;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return null;
        }

        if (ctype_digit($trimmed)) {
            return (int) $trimmed;
        }

        return $trimmed;
    }

    if (is_float($value)) {
        $intValue = (int) $value;
        return $intValue >= 0 ? $intValue : null;
    }

    return null;
};

$timezone = UtilityService::getActivityTimezone($activityConfig);
$showInfoColumn = UtilityService::shouldShowInfoColumn($activityConfig);
$shakaGoldCardNumber = UtilityService::getShakaGoldCardNumber($activityConfig);
$primaryActivityIdString = $primaryActivityId === null ? null : (string) $primaryActivityId;

$rawDiscount = $activityConfig['discount'] ?? null;
$discount = null;

if (is_numeric($rawDiscount)) {
    $discount = (float) $rawDiscount;
} elseif (is_string($rawDiscount)) {
    $trimmedDiscount = trim($rawDiscount);

    if ($trimmedDiscount !== '') {
        $normalizedDiscount = str_replace('%', '', $trimmedDiscount);

        if (is_numeric($normalizedDiscount)) {
            $discount = (float) $normalizedDiscount;
        }
    }
}

$activityListNormalized = [];
foreach ($activityList as $activity) {
    if (!is_array($activity) || !isset($activity['id'])) {
        continue;
    }

    $normalizedId = $normalizeActivityIdentifier($activity['id']);
    if ($normalizedId === null) {
        continue;
    }

    $idString = (string) $normalizedId;
    $isPrimary = $primaryActivityIdString !== null && $primaryActivityIdString === $idString;
    $activityListNormalized[] = [
        'id' => $idString,
        'rawId' => $normalizedId,
        'label' => isset($activity['label']) && $activity['label'] !== '' ? (string) $activity['label'] : null,
        'primary' => $isPrimary || (bool) ($activity['primary'] ?? false),
        'source' => isset($activity['source']) && is_string($activity['source']) ? $activity['source'] : 'config',
    ];
}

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

if ($transportationSet instanceof TransportationSet && is_array($primaryActivityInfo)) {
    $mandatoryRaw = $primaryActivityInfo['transportationMandatory'] ?? null;
    if ($mandatoryRaw !== null) {
        $mandatory = filter_var(
            $mandatoryRaw,
            FILTER_VALIDATE_BOOLEAN,
            FILTER_NULL_ON_FAILURE
        );

        if ($mandatory !== null) {
            $transportationSet->setMandatory($mandatory);
            $transportationData = $transportationSet->toArray();
        }
    }
}

$activityConfig['transportation'] = $transportationData;

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
    'transportation' => sprintf(
        '/api/get-transportation.php?supplier=%s&activity=%s',
        rawurlencode($supplierSlug),
        rawurlencode($activitySlug)
    ),
    'goldCardDiscount' => sprintf(
        '/api/get-goldcard-discount.php?supplier=%s&activity=%s',
        rawurlencode($supplierSlug),
        rawurlencode($activitySlug)
    ),
    'upgrades' => $disableUpgrades
        ? null
        : sprintf(
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
        'links' => $supplierConfig['links'] ?? [],
        'analytics' => $supplierConfig['analytics'] ?? [],
    ],
    'activity' => [
        'slug' => $activitySlug,
        'displayName' => $activityConfig['displayName']
            ?? $activityConfig['activityTitle']
            ?? ucfirst(str_replace('-', ' ', $activitySlug)),
        'summary' => $activityConfig['summary'] ?? null,
        'discount' => $discount,
        'uiLabels' => $activityConfig['uiLabels'] ?? [],
        'infoBlocks' => $infoBlocksFromConfig,
        'transportation' => $transportationData,
        'upgrades' => $activityConfig['upgrades'] ?? [],
        'disableUpgrades' => $disableUpgrades,
        'privateActivity' => filter_var($activityConfig['privateActivity'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'timezone' => $timezone,
        'showInfoColumn' => $showInfoColumn,
        'shakaGoldCardNumber' => $shakaGoldCardNumber,
        'primaryActivityId' => $primaryActivityId,
        'primaryActivityIdString' => $primaryActivityIdString,
        'activityIds' => $activityIds,
        'activityIdStrings' => array_map('strval', $activityIds),
        'activities' => $activityListNormalized,
        'departureLabels' => $departureLabels,
        'currency' => [
            'code' => strtoupper((string) ($activityConfig['currency']['code'] ?? 'USD')),
            'symbol' => $activityConfig['currency']['symbol'] ?? '$',
            'locale' => $activityConfig['currency']['locale'] ?? 'en-US',
        ],
        'guestTypes' => [
            'collection' => $guestTypeCollectionNormalized,
            'byId' => $guestTypesByIdNormalized,
        ],
        'details' => $activityDetails,
    ],
    'api' => $apiEndpoints,
    'environment' => [
        'currentDate' => $currentDate,
    ],
];

$activityIndexById = [];
foreach ($bootstrapData['activity']['activities'] as $index => $activityEntry) {
    if (!isset($activityEntry['id'])) {
        continue;
    }

    $activityIndexById[$activityEntry['id']] = $index;
}

foreach ($activityInfoById as $id => $info) {
    if (!is_array($info)) {
        continue;
    }

    $idString = (string) $id;
    if ($idString === '') {
        continue;
    }

    $label = null;
    if (!empty($info['times'])) {
        $candidate = trim((string) $info['times']);
        if ($candidate !== '') {
            $label = $candidate;
        }
    }

    if ($label !== null && !isset($bootstrapData['activity']['departureLabels'][$idString])) {
        $bootstrapData['activity']['departureLabels'][$idString] = $label;
    }

    if (isset($activityIndexById[$idString])) {
        $activityIndex = $activityIndexById[$idString];
        if ($label !== null && ($bootstrapData['activity']['activities'][$activityIndex]['label'] ?? null) === null) {
            $bootstrapData['activity']['activities'][$activityIndex]['label'] = $label;
            if (!isset($bootstrapData['activity']['activities'][$activityIndex]['source'])) {
                $bootstrapData['activity']['activities'][$activityIndex]['source'] = 'ponorez';
            }
        }
    } else {
        $rawId = ctype_digit($idString) ? (int) $idString : $idString;
        $bootstrapData['activity']['activities'][] = [
            'id' => $idString,
            'rawId' => $rawId,
            'label' => $label,
            'primary' => $primaryActivityIdString === $idString,
            'source' => 'ponorez',
        ];

        $newIndex = array_key_last($bootstrapData['activity']['activities']);
        if ($newIndex !== null) {
            $activityIndexById[$idString] = $newIndex;
        }

        if (!in_array($rawId, $bootstrapData['activity']['activityIds'], true)) {
            $bootstrapData['activity']['activityIds'][] = $rawId;
        }

        if (!in_array($idString, $bootstrapData['activity']['activityIdStrings'], true)) {
            $bootstrapData['activity']['activityIdStrings'][] = $idString;
        }
    }
}

$bootstrapData['activity']['activityIds'] = array_values(array_unique(
    $bootstrapData['activity']['activityIds'],
    SORT_REGULAR
));
$bootstrapData['activity']['activityIdStrings'] = array_values(array_unique(
    $bootstrapData['activity']['activityIdStrings']
));

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

include dirname(__DIR__) . '/partials/layout/form-template.php';
