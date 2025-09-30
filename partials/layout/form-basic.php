<?php

declare(strict_types=1);

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
$logoUrl = $branding['logo'] ?? null;
$homeLink = $supplier['homeLink'] ?? null;
$contact = $supplier['contact'] ?? [];
$supplierName = $supplier['name']
    ?? $supplier['supplierName']
    ?? ucfirst(str_replace('-', ' ', (string) ($supplier['slug'] ?? 'Supplier')));
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
<body class="min-h-screen bg-slate-50 font-sans text-slate-900">
<script type="application/json" id="sgc-bootstrap"><?= $bootstrapJson ?></script>
<script>
    window.__SGC_BOOTSTRAP__ = JSON.parse(document.getElementById('sgc-bootstrap').textContent || '{}');
    window.__SGC_API_ENDPOINTS__ = <?= json_encode($apiEndpoints, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>

<header class="bg-white shadow-sm border-b border-slate-200">
    <div class="max-w-6xl mx-auto px-6 py-6 flex flex-wrap items-center justify-between gap-6">
        <div class="flex items-center gap-4">
            <?php if ($logoUrl): ?>
                <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
                     alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> logo"
                     class="h-12 w-auto">
            <?php else: ?>
                <span class="inline-flex items-center justify-center h-12 w-12 rounded-full bg-slate-200 text-slate-600 font-semibold">
                    <?= strtoupper(substr($title, 0, 1)) ?>
                </span>
            <?php endif; ?>
            <div>
                <p class="text-xs uppercase tracking-widest text-slate-500">Booking Form</p>
                <h1 class="text-2xl font-semibold text-slate-900 leading-tight"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h1>
                <?php if (!empty($activity['summary'])): ?>
                    <p class="text-slate-600 text-sm mt-1 max-w-3xl"><?= htmlspecialchars($activity['summary'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if (!empty($homeLink['enabled']) && !empty($homeLink['label']) && !empty($homeLink['url'])): ?>
                <a class="inline-flex items-center gap-2 text-sm font-medium text-slate-600 hover:text-slate-900"
                   href="<?= htmlspecialchars($homeLink['url'], ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($homeLink['label'], ENT_QUOTES, 'UTF-8') ?>
                    <span aria-hidden="true" class="mt-px">&rarr;</span>
                </a>
        <?php endif; ?>
    </div>
</header>

<main class="py-12">
    <div class="max-w-6xl mx-auto px-6">
        <div class="grid gap-8 lg:grid-cols-[2fr,1fr]">
            <div class="space-y-6">
                <div data-component="alerts" class="space-y-3" role="region" aria-live="polite"></div>

                <form id="sgc-booking-form" class="space-y-6" novalidate>
                    <?php include dirname(__DIR__) . '/form/component-guest-types.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-calendar.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-timeslot.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-transportation.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-upgrades.php'; ?>
                    <?php include dirname(__DIR__) . '/form/activity-info-blocks.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-button.php'; ?>
                </form>

                <section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4">
                    <h3 class="font-semibold text-slate-900">API Endpoints</h3>
                    <p class="text-sm text-slate-600">Quick links for manual testing while the front-end modules are in progress.</p>
                    <ul class="mt-2 space-y-1 text-sm text-slate-600">
                        <?php foreach ($apiEndpoints as $key => $endpoint): ?>
                            <li>
                                <code class="bg-slate-100 px-2 py-1 rounded text-xs text-slate-700">
                                    <?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>
                                </code>
                                <span class="ml-2 text-slate-500">
                                    <a class="underline decoration-dotted" href="<?= htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            </div>

            <aside class="space-y-6">
                <?php include dirname(__DIR__) . '/form/component-pricing.php'; ?>

                <?php if (!empty($contact)): ?>
                    <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-2">
                        <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Need help?</h3>
                        <?php if (!empty($contact['phone'])): ?>
                            <p class="text-sm text-slate-600">
                                Phone:
                                <a class="text-blue-600 hover:underline" href="tel:<?= htmlspecialchars($contact['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($contact['phone'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </p>
                        <?php endif; ?>
                        <?php if (!empty($contact['email'])): ?>
                            <p class="text-sm text-slate-600">
                                Email:
                                <a class="text-blue-600 hover:underline" href="mailto:<?= htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8') ?>">
                                    <?= htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8') ?>
                                </a>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="bg-white rounded-xl shadow-sm border border-slate-200 p-6">
                    <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-wide">Current Color Palette</h3>
                    <div class="mt-4 flex items-center gap-4">
                        <div class="flex flex-col items-center text-xs text-slate-500">
                            <span class="h-10 w-10 rounded-full border" style="background-color: <?= $primaryColor ?>"></span>
                            <span class="mt-2">Primary</span>
                        </div>
                        <div class="flex flex-col items-center text-xs text-slate-500">
                            <span class="h-10 w-10 rounded-full border" style="background-color: <?= $secondaryColor ?>"></span>
                            <span class="mt-2">Secondary</span>
                        </div>
                    </div>
                </div>
            </aside>
        </div>
    </div>
</main>

<footer class="py-10 text-center text-xs text-slate-500">
    Powered by Ponorez &middot; <?= htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8') ?>
</footer>

<?php include dirname(__DIR__) . '/shared/component-overlay.php'; ?>

<script type="module" src="/assets/js/main.js"></script>
</body>
</html>
