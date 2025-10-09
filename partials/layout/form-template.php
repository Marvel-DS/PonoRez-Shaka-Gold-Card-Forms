<?php

declare(strict_types=1);

use PonoRez\SGCForms\UtilityService;

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$branding = $page['branding'] ?? [];
$activity = $bootstrap['activity'] ?? [];
$supplier = $bootstrap['supplier'] ?? [];
$apiEndpoints = $page['apiEndpoints'] ?? [];

$title = $activity['displayName'] ?? 'SGC Booking Forms';

try {
    $bootstrapJson = json_encode($bootstrap, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} catch (\JsonException) {
    $bootstrapJson = json_encode([
        'error' => 'Unable to encode bootstrap payload.',
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

$primaryColor = htmlspecialchars($branding['primaryColor'] ?? '#1C55DB', ENT_QUOTES, 'UTF-8');
$secondaryColor = htmlspecialchars($branding['secondaryColor'] ?? '#0B2E8F', ENT_QUOTES, 'UTF-8');

$supplierSlug = $page['supplierSlug'] ?? ($supplier['slug'] ?? null);
$activityConfig = $activity ?? [];

$galleryItems = [];
$supplierLogoUrl = is_string($branding['logo'] ?? null) ? $branding['logo'] : null;

if (is_string($supplierSlug) && $supplierSlug !== '') {
    $galleryItems = UtilityService::getActivityGalleryImages($supplierSlug, $activityConfig);

    if ($supplierLogoUrl !== null) {
        $supplierLogoUrl = UtilityService::resolveSupplierAssetUrl($supplierSlug, $supplierLogoUrl);
    }
}

$supplierName = $supplier['name']
    ?? $supplier['supplierName']
    ?? ucfirst(str_replace('-', ' ', (string) ($supplier['slug'] ?? 'Supplier')));

$showInfoColumn = $bootstrap['activity']['showInfoColumn']
    ?? UtilityService::shouldShowInfoColumn($activityConfig);

$layoutColumns = $showInfoColumn
    ? 'lg:grid-cols-[minmax(0,0.95fr),minmax(0,1.2fr)]'
    : 'lg:grid-cols-1';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="<?= $primaryColor ?>">
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="/assets/css/main.css">
    <?php include __DIR__ . '/branding.php'; ?>
</head>
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased py-2 md:py-6 px-2 md:px-6">
<script type="application/json" id="sgc-bootstrap"><?= $bootstrapJson ?></script>
<script>
    window.__SGC_BOOTSTRAP__ = JSON.parse(document.getElementById('sgc-bootstrap').textContent || '{}');
    window.__SGC_API_ENDPOINTS__ = <?= json_encode($apiEndpoints, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php
    $activityTitle = $title;
    $supplierDisplayName = $supplierName;
    $homeLink = $supplier['homeLink'] ?? [];
?>

<div class="mx-auto max-w-6xl bg-white rounded-xl border border-slate-200 shadow-xs">

    <?php include __DIR__ . '/section-header.php'; ?>

    <main class="px-6 pb-16">

        <?php include __DIR__ . '/section-hero.php'; ?>

        <form id="sgc-booking-form" class="space-y-6" novalidate>

            <div data-component="alerts" class="space-y-3" role="region" aria-live="polite"></div>

            <?php include dirname(__DIR__) . '/form/component-guest-types.php'; ?>
            <?php include dirname(__DIR__) . '/form/component-calendar.php'; ?>
            <?php include dirname(__DIR__) . '/form/component-timeslot.php'; ?>
            <?php include dirname(__DIR__) . '/form/component-transportation.php'; ?>
            <?php include dirname(__DIR__) . '/form/component-upgrades.php'; ?>
            <?php include dirname(__DIR__) . '/form/component-pricing.php'; ?>

            <?php include dirname(__DIR__) . '/form/component-button.php'; ?>

        </form>

    </main>

    <footer class="px-6 pb-10 text-center text-xs text-slate-500">
        Powered by Ponorez &middot; <?= htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8') ?>
    </footer>

</div>

<?php include dirname(__DIR__) . '/shared/component-overlay.php'; ?>

<script type="module" src="/assets/js/main.js"></script>
</body>
</html>
