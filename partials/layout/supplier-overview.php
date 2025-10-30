<?php

declare(strict_types=1);

use PonoRez\SGCForms\UtilityService;

/** @var array<string, mixed> $overviewContext */
$overviewContext = $overviewContext ?? [];

$supplier = $overviewContext['supplier'] ?? [];
$supplierSlug = $overviewContext['supplierSlug'] ?? '';
$activities = $overviewContext['activities'] ?? [];
$publicBasePath = $overviewContext['publicBasePath'] ?? null;

$base = UtilityService::getPublicBasePath();
$assetBase = is_string($publicBasePath) && $publicBasePath !== '' ? $publicBasePath : $base;

$supplierName = $supplier['supplierName'] ?? ucfirst(str_replace('-', ' ', (string) $supplierSlug));
$pageTitle = $supplierName !== '' ? $supplierName . ' Activities' : 'Supplier Activities';
$branding = $supplier['branding'] ?? [];
$primaryColor = htmlspecialchars($branding['primaryColor'] ?? '#1C55DB', ENT_QUOTES, 'UTF-8');
$secondaryColor = htmlspecialchars($branding['secondaryColor'] ?? '#0B2E8F', ENT_QUOTES, 'UTF-8');

$logoPath = isset($branding['logo']) && is_string($branding['logo']) ? $branding['logo'] : null;
$supplierLogoUrl = null;
if ($logoPath !== null && $supplierSlug !== '') {
    $supplierLogoUrl = UtilityService::resolveSupplierAssetUrl($supplierSlug, $logoPath);
}

$homeLink = $supplier['homeLink'] ?? [];

$currencyCode = null;
foreach ($activities as $activityEntry) {
    if (!empty($activityEntry['currencyCode'])) {
        $currencyCode = strtoupper((string) $activityEntry['currencyCode']);
        break;
    }
}
$priceDisclaimer = $currencyCode !== null
    ? sprintf('All prices are in %s.', htmlspecialchars($currencyCode, ENT_QUOTES, 'UTF-8'))
    : null;

// Allow branding partial to reuse this palette.
$pageContext = [
    'branding' => [
        'primaryColor' => $branding['primaryColor'] ?? '#1C55DB',
    ],
];

?>
<!DOCTYPE html>
<html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="<?= $primaryColor ?>">
        <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
        <link rel="stylesheet" href="<?= htmlspecialchars($assetBase . 'assets/css/main.css', ENT_QUOTES, 'UTF-8') ?>">
        <?php include __DIR__ . '/branding.php'; ?>
    </head>
    <body class="min-h-screen bg-slate-50 font-sans text-slate-900 antialiased py-3 md:py-6 px-2 md:px-6">
        <div class="mx-auto max-w-6xl bg-white rounded-xl border border-slate-200 shadow-xs">

            <main class="px-4 md:px-6 py-6 space-y-8">

                <?php
                    $supplierDisplayName = $supplierName;
                    $contactPhone = isset($supplier['contact']['phone']) && is_string($supplier['contact']['phone'])
                        ? trim($supplier['contact']['phone'])
                        : '';
                    $contactEmail = isset($supplier['contact']['email']) && is_string($supplier['contact']['email'])
                        ? trim($supplier['contact']['email'])
                        : '';
                    $showHomeLink = false;
                    include __DIR__ . '/section-header.php';
                    unset($showHomeLink, $contactPhone, $contactEmail);
                ?>

<?php if ($activities === []): ?>
    <section class="rounded-3xl border border-slate-200 bg-white/70 p-8 text-center shadow-sm backdrop-blur">
        <p class="text-sm text-slate-600 mb-0">
            Activities for this supplier are currently unavailable. Please check back later or contact support.
        </p>
    </section>
                <?php else: ?>
                    <section class="grid gap-8 md:grid-cols-2">
                        <?php foreach ($activities as $activity): ?>
                            <?php
                                $activityName = $activity['displayName'] ?? $activity['slug'] ?? 'Activity';
                                $activitySummary = $activity['summary'] ?? null;
                                $activityUrl = $activity['url']
                                    ?? ($assetBase . 'suppliers/' . rawurlencode((string) $supplierSlug) . '/' . rawurlencode((string) ($activity['slug'] ?? '')));
                                $imageUrl = $activity['image'] ?? ($assetBase . 'assets/images/activity-cover-placeholder.jpg');
                            ?>
                            <article class="group relative flex flex-col overflow-hidden rounded-[28px] border border-slate-200 bg-white shadow-sm transition-transform duration-500 hover:shadow-xl focus-within:ring-4 focus-within:ring-[var(--sgc-brand-primary)] focus-within:ring-offset-4">
                                <div class="relative h-72 w-full md:h-80">
                                    <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>"
                                         alt="<?= htmlspecialchars((string) $activityName, ENT_QUOTES, 'UTF-8') ?>"
                                         class="absolute inset-0 h-full w-full scale-100 object-cover opacity-80 transition-transform duration-700 ease-out group-hover:scale-110 group-hover:opacity-95">
                                    <div class="absolute inset-0 bg-gradient-to-b from-transparent via-slate-900/40 to-slate-900/90"></div>
                                    <div class="absolute inset-0 flex flex-col justify-end gap-4 px-6 pb-6 pt-12 text-white md:px-8 md:pb-8 md:pt-16">
                                        <div class="space-y-3">
                                            <h2 class="text-2xl font-semibold leading-snug tracking-tight md:text-[26px]">
                                                <?= htmlspecialchars((string) $activityName, ENT_QUOTES, 'UTF-8') ?>
                                            </h2>
                                            <?php if ($activitySummary !== null && trim((string) $activitySummary) !== ''): ?>
                                                <p class="text-sm text-white/85">
                                                    <?= htmlspecialchars($activitySummary, ENT_QUOTES, 'UTF-8') ?>
                                                </p>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <a href="<?= htmlspecialchars($activityUrl, ENT_QUOTES, 'UTF-8') ?>"
                                               class="home-link inline-flex items-center justify-center gap-2 rounded-md px-5 py-4 text-xs font-semibold uppercase no-underline text-white bg-[var(--sgc-brand-primary)] hover:bg-slate-100 hover:text-[var(--sgc-brand-primary)] transition-all duration-500">
                                                Book Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>

                <?php if ($priceDisclaimer !== null): ?>
                    <p class="text-xs text-slate-500 text-center md:text-right">
                        <?= $priceDisclaimer ?>
                    </p>
                <?php endif; ?>

                <?php
                    $footerContext = [
                        'supplier' => $supplier,
                        'supplierSlug' => $supplierSlug,
                    ];
                    include __DIR__ . '/section-footer.php';
                    unset($footerContext);
                ?>

            </main>
        </div>

        <?php include dirname(__DIR__) . '/shared/component-overlay.php'; ?>
        <script type="module" src="<?= htmlspecialchars($base . 'assets/js/main.js', ENT_QUOTES, 'UTF-8') ?>"></script>
    </body>
</html>
