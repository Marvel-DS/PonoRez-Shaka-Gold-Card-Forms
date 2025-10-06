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

$details = is_array($activity['details'] ?? null) ? $activity['details'] : [];
$metaBadges = [];

if (!empty($details['island'])) {
    $metaBadges[] = [
        'label' => 'Island',
        'value' => (string) $details['island'],
    ];
}

if (!empty($details['times'])) {
    $metaBadges[] = [
        'label' => 'Departures',
        'value' => (string) $details['times'],
    ];
}

if (array_key_exists('transportationMandatory', $details)) {
    $transportRaw = strtolower(trim((string) $details['transportationMandatory']));
    $isMandatory = in_array($transportRaw, ['1', 'true', 'yes', 'on', 'required', 'y'], true);
    $isOptional = in_array($transportRaw, ['0', 'false', 'no', 'off', 'optional', 'n'], true);

    if ($isMandatory || $isOptional) {
        $metaBadges[] = [
            'label' => 'Transportation',
            'value' => $isMandatory ? 'Required' : 'Optional',
        ];
    }
}

if (!empty($activity['privateActivity'])) {
    $metaBadges[] = [
        'label' => 'Experience',
        'value' => 'Private charter',
    ];
}

$steps = [
    [
        'title' => 'Guests',
        'description' => 'Tell us who is coming along so we can secure seats for everyone.',
    ],
    [
        'title' => 'Schedule',
        'description' => 'Choose the ideal travel date and departure window.',
    ],
    [
        'title' => 'Extras',
        'description' => 'Add transportation and upgrades to elevate the experience.',
    ],
    [
        'title' => 'Confirm',
        'description' => 'Review the summary before sending your request.',
    ],
];
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
<body class="min-h-screen bg-[#f1f4ff] font-sans text-slate-900 antialiased">
<script type="application/json" id="sgc-bootstrap"><?= $bootstrapJson ?></script>
<script>
    window.__SGC_BOOTSTRAP__ = JSON.parse(document.getElementById('sgc-bootstrap').textContent || '{}');
    window.__SGC_API_ENDPOINTS__ = <?= json_encode($apiEndpoints, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>

<div class="relative isolate overflow-hidden">
    <div class="pointer-events-none absolute inset-x-0 top-0 h-[360px] bg-gradient-to-r from-[#012a52] via-[#0b4f81] to-[#4fcaf3]">
        <div class="absolute -left-24 top-10 h-64 w-64 rounded-full bg-white/20 blur-3xl"></div>
        <div class="absolute left-1/2 top-[-120px] h-80 w-80 -translate-x-1/2 rounded-full bg-white/10 blur-3xl"></div>
        <div class="absolute -right-36 top-24 h-72 w-72 rounded-full bg-white/15 blur-3xl"></div>
        <div class="absolute inset-x-0 bottom-[-160px] h-60 bg-gradient-to-t from-[#f1f4ff] via-[#f1f4ff]/40 to-transparent"></div>
    </div>

    <div class="relative mx-auto flex max-w-6xl flex-col gap-10 px-6 pb-20 pt-12 lg:pt-16">
        <header class="space-y-8 text-white">
            <div class="flex flex-wrap items-start justify-between gap-6">
                <div class="flex items-center gap-4">
                    <?php if ($logoUrl): ?>
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 ring-1 ring-white/20 backdrop-blur">
                            <img src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?> logo"
                                 class="h-10 w-auto">
                        </span>
                    <?php else: ?>
                        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-white/15 text-2xl font-semibold uppercase ring-1 ring-white/30">
                            <?= strtoupper(substr($title, 0, 1)) ?>
                        </span>
                    <?php endif; ?>
                    <div class="min-w-0 space-y-2">
                        <p class="text-xs uppercase tracking-[0.3em] text-white/70">Ponorez Gold Card</p>
                        <h1 class="text-3xl font-semibold leading-tight sm:text-4xl">
                            <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                        </h1>
                        <?php if (!empty($activity['summary'])): ?>
                            <p class="max-w-2xl text-base text-white/80">
                                <?= htmlspecialchars($activity['summary'], ENT_QUOTES, 'UTF-8') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (!empty($homeLink['enabled']) && !empty($homeLink['label']) && !empty($homeLink['url'])): ?>
                    <a class="inline-flex items-center gap-2 rounded-full border border-white/30 bg-white/10 px-5 py-2 text-sm font-medium text-white transition hover:border-white/60 hover:bg-white/20 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
                       href="<?= htmlspecialchars($homeLink['url'], ENT_QUOTES, 'UTF-8') ?>">
                        <span><?= htmlspecialchars($homeLink['label'], ENT_QUOTES, 'UTF-8') ?></span>
                        <svg aria-hidden="true" class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path d="M9 5l7 7-7 7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </a>
                <?php endif; ?>
            </div>

            <?php if ($metaBadges !== []): ?>
                <dl class="flex flex-wrap items-center gap-3 text-xs font-medium text-white/80">
                    <?php foreach ($metaBadges as $badge): ?>
                        <div class="flex items-center gap-2 rounded-full bg-white/15 px-4 py-2 backdrop-blur">
                            <span class="text-white/60"><?= htmlspecialchars($badge['label'], ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-white">
                                <?= htmlspecialchars($badge['value'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </dl>
            <?php endif; ?>
        </header>

        <div class="mt-4 grid gap-8 lg:grid-cols-[280px,minmax(0,1fr),320px]">
            <aside class="order-1 space-y-6 lg:order-0">
                <section class="rounded-3xl bg-white/80 p-6 shadow-card ring-1 ring-slate-200/70 backdrop-blur">
                    <header class="space-y-1">
                        <p class="text-xs font-semibold uppercase tracking-[0.35em] text-slate-500">Booking steps</p>
                        <h2 class="text-lg font-semibold text-slate-900">Plan your adventure</h2>
                    </header>
                    <ol class="mt-6 space-y-5 text-sm text-slate-600">
                        <?php foreach ($steps as $index => $step): ?>
                            <li class="flex gap-4">
                                <span class="mt-1 flex h-9 w-9 flex-none items-center justify-center rounded-full border-2 <?= $index === 0 ? 'border-[#1c54db] bg-[#1c54db] text-white' : 'border-slate-200 text-slate-500' ?> font-semibold">
                                    <?= $index + 1 ?>
                                </span>
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-slate-900">
                                        <?= htmlspecialchars($step['title'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                    <p class="text-xs text-slate-600">
                                        <?= htmlspecialchars($step['description'], ENT_QUOTES, 'UTF-8') ?>
                                    </p>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </section>

                <?php if (!empty($contact)): ?>
                    <section class="rounded-3xl bg-gradient-to-r from-[#0b4f81] to-[#1c54db] p-6 text-white shadow-card">
                        <h2 class="text-sm font-semibold uppercase tracking-[0.35em] text-white/70">Need help?</h2>
                        <p class="mt-2 text-base font-semibold">Talk with our local experts.</p>
                        <div class="mt-4 space-y-3 text-sm text-white/80">
                            <?php if (!empty($contact['phone'])): ?>
                                <p>
                                    <span class="font-medium text-white">Phone:</span>
                                    <a class="ml-1 underline decoration-white/40 decoration-dotted underline-offset-4 transition hover:text-white" href="tel:<?= htmlspecialchars($contact['phone'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($contact['phone'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                            <?php if (!empty($contact['email'])): ?>
                                <p>
                                    <span class="font-medium text-white">Email:</span>
                                    <a class="ml-1 underline decoration-white/40 decoration-dotted underline-offset-4 transition hover:text-white" href="mailto:<?= htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($contact['email'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </p>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>

                <?php if ($apiEndpoints !== []): ?>
                    <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-card">
                        <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-[0.3em]">Developer tools</h3>
                        <p class="mt-2 text-xs text-slate-500">Quick links for validating integration responses.</p>
                        <ul class="mt-4 space-y-2 text-xs text-slate-600">
                            <?php foreach ($apiEndpoints as $key => $endpoint): ?>
                                <li class="flex items-center gap-3">
                                    <span class="inline-flex min-w-[6rem] justify-center rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-600">
                                        <?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>
                                    </span>
                                    <a class="truncate text-blue-600 hover:text-blue-700" href="<?= htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars($endpoint, ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </section>
                <?php endif; ?>
            </aside>

            <div class="order-0 space-y-6 lg:order-1">
                <div data-component="alerts" class="space-y-3" role="region" aria-live="polite"></div>

                <form id="sgc-booking-form" class="rounded-3xl bg-white p-6 shadow-card ring-1 ring-slate-200/70 sm:p-8 space-y-8" novalidate>
                    <?php include dirname(__DIR__) . '/form/component-guest-types.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-calendar.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-timeslot.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-transportation.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-upgrades.php'; ?>
                    <?php include dirname(__DIR__) . '/form/activity-info-blocks.php'; ?>
                    <?php include dirname(__DIR__) . '/form/component-button.php'; ?>
                </form>
            </div>

            <aside class="order-2 space-y-6 lg:order-2 lg:pl-4 lg:pt-2">
                <div class="lg:sticky lg:top-32 space-y-6">
                    <?php include dirname(__DIR__) . '/form/component-pricing.php'; ?>

                    <section class="rounded-3xl border border-slate-200 bg-white/90 p-6 shadow-card">
                        <h3 class="text-sm font-semibold text-slate-900 uppercase tracking-[0.3em]">Current palette</h3>
                        <p class="mt-2 text-xs text-slate-500">Brand colors pulled from supplier settings.</p>
                        <div class="mt-5 flex items-center gap-5">
                            <div class="flex flex-col items-center text-xs text-slate-500">
                                <span class="h-12 w-12 rounded-full border" style="background-color: <?= $primaryColor ?>"></span>
                                <span class="mt-2 font-medium text-slate-600">Primary</span>
                            </div>
                            <div class="flex flex-col items-center text-xs text-slate-500">
                                <span class="h-12 w-12 rounded-full border" style="background-color: <?= $secondaryColor ?>"></span>
                                <span class="mt-2 font-medium text-slate-600">Secondary</span>
                            </div>
                        </div>
                    </section>
                </div>
            </aside>
        </div>
    </div>
</div>

<footer class="px-6 pb-10 text-center text-xs text-slate-500">
    Powered by Ponorez &middot; <?= htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8') ?>
</footer>

<?php include dirname(__DIR__) . '/shared/component-overlay.php'; ?>

<script type="module" src="/assets/js/main.js"></script>
</body>
</html>
