<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activity = $bootstrap['activity'] ?? [];
$currencyCode = strtoupper((string) ($activity['currency']['code'] ?? 'usd'));
$currencySymbol = $activity['currency']['symbol'] ?? '$';
$title = $bootstrap['activity']['uiLabels']['pricing'] ?? 'Trip Summary';
?>
<section class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-4" data-component="pricing">
    <header class="flex items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-slate-900"><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-sm text-slate-500">Totals update automatically as you adjust the form.</p>
        </div>
        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-600">
            <span aria-hidden="true"><?= htmlspecialchars($currencySymbol, ENT_QUOTES, 'UTF-8') ?></span>
            <span><?= htmlspecialchars($currencyCode, ENT_QUOTES, 'UTF-8') ?></span>
        </span>
    </header>

    <dl class="space-y-3 text-sm" data-pricing-breakdown>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-600">Guests</dt>
            <dd class="font-medium text-slate-900" data-pricing-guests>--</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-600">Transportation</dt>
            <dd class="font-medium text-slate-900" data-pricing-transportation>--</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-600">Upgrades</dt>
            <dd class="font-medium text-slate-900" data-pricing-upgrades>--</dd>
        </div>
        <div class="flex items-center justify-between gap-3">
            <dt class="text-slate-600">Taxes &amp; fees</dt>
            <dd class="font-medium text-slate-900" data-pricing-fees>--</dd>
        </div>
    </dl>

    <div class="flex items-center justify-between border-t border-slate-200 pt-4">
        <span class="text-sm font-semibold text-slate-900">Total due today</span>
        <span class="text-2xl font-semibold text-slate-900" data-pricing-total>--</span>
    </div>

    <p class="text-xs text-slate-500" data-pricing-note>Rates are displayed in USD and include all required fees.</p>
</section>
