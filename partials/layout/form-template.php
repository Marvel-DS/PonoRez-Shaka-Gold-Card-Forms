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

$disableUpgrades = (
    ($activity['disableUpgrades'] ?? false)
    || ($page['activity']['disableUpgrades'] ?? false)
);

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

// Pre-render activity info blocks so we can collapse the column when nothing is configured.
$activityInfoBlocksHtml = '';
if ($showInfoColumn) {
    ob_start();
    include dirname(__DIR__) . '/form/activity-info-blocks.php';
    $activityInfoBlocksHtml = trim((string) ob_get_clean());

    if ($activityInfoBlocksHtml === '') {
        $showInfoColumn = false;
    }
}

// Ensure the top-level title still reflects the activity after any includes.
$title = $activity['displayName'] ?? 'SGC Booking Forms';

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
<body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased py-3 md:py-6 px-2 md:px-6">
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

    <main class="px-4 md:px-6 pb-16 space-y-8">

        <?php include __DIR__ . '/section-hero.php'; ?>

        <div data-component="alerts" class="space-y-3" role="region" aria-live="polite"></div>

        <form id="sgc-booking-form" class="space-y-8" novalidate>

            <?php if ($showInfoColumn): ?>

                <div class="grid gap-8 lg:grid-cols-2 lg:items-start">

                    <div class="space-y-8 order-2 md:order-1" aria-label="Activity information">
                        <?= $activityInfoBlocksHtml ?>
                    </div>

                    <div class="space-y-8 order-1 md:order-2" aria-label="Booking form">

                        <?php include dirname(__DIR__) . '/form/component-guest-types.php'; ?>
                        <?php include dirname(__DIR__) . '/form/component-calendar.php'; ?>
                        <?php include dirname(__DIR__) . '/form/component-timeslot.php'; ?>
                        <?php include dirname(__DIR__) . '/form/component-transportation.php'; ?>
                        <?php if (!$disableUpgrades) { ?>
                            <?php include dirname(__DIR__) . '/form/component-upgrades.php'; ?>
                        <?php } ?>
                        <section class="space-y-6" data-component="shaka-gold-card">
                            <?php $shakaGoldCardComposite = true; ?>
                            <?php include dirname(__DIR__) . '/form/component-goldcard.php'; ?>
                            <?php include dirname(__DIR__) . '/form/component-goldcard-upsell.php'; ?>
                            <?php unset($shakaGoldCardComposite); ?>
                        </section>
                        <?php include dirname(__DIR__) . '/form/component-cancellation-policy.php'; ?>
                        <?php include dirname(__DIR__) . '/form/component-button.php'; ?>

                    </div>

                </div>

            <?php else: ?>

                <?php include dirname(__DIR__) . '/form/component-guest-types.php'; ?>
                <?php include dirname(__DIR__) . '/form/component-calendar.php'; ?>
                <?php include dirname(__DIR__) . '/form/component-timeslot.php'; ?>
                <?php include dirname(__DIR__) . '/form/component-transportation.php'; ?>
                <?php if (!$disableUpgrades) { ?>
                    <?php include dirname(__DIR__) . '/form/component-upgrades.php'; ?>
                <?php } ?>
                <section class="space-y-6" data-component="shaka-gold-card">
                    <?php $shakaGoldCardComposite = true; ?>
                    <?php include dirname(__DIR__) . '/form/component-goldcard.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-goldcard-upsell.php'; ?>
                    <?php unset($shakaGoldCardComposite); ?>
                </section>
                <?php include dirname(__DIR__) . '/form/component-cancellation-policy.php'; ?>
                <?php include dirname(__DIR__) . '/form/component-button.php'; ?>
                
            <?php endif; ?>

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
