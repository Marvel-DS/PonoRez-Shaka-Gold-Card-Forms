<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activity = $bootstrap['activity'] ?? [];
$labels = is_array($activity['uiLabels'] ?? null) ? $activity['uiLabels'] : [];

$numberLabel = $labels['shakaGoldCardNumber'] ?? 'Shaka Gold Card Number';
$upsellLabel = $labels['shakaGoldCardUpsell'] ?? 'Buying for someone else?';

$shakaGoldCardNumber = $activity['shakaGoldCardNumber'] ?? '';
if (!is_string($shakaGoldCardNumber)) {
    $shakaGoldCardNumber = '';
}

$numberDescription = 'Enter your Shaka Gold Card number so we can apply your discount.';
$upsellDescription = 'Add a Shaka Gold Card to gift savings on future adventures.';
$coverageHint = 'Covers up to 4 guests. Additional guests are $7.50 each.';
$inputId = 'shaka-gold-card-number';
$checkboxId = 'shaka-gold-card-upsell';
?>
<section class="space-y-6" data-component="shaka-gold-card">
    <div class="space-y-2">
        <label for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" class="block text-lg font-semibold text-slate-900">
            <?= htmlspecialchars($numberLabel, ENT_QUOTES, 'UTF-8') ?>
        </label>
        <p class="text-sm text-slate-600">
            <?= htmlspecialchars($numberDescription, ENT_QUOTES, 'UTF-8') ?>
        </p>
        <input
            id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
            name="shakaGoldCardNumber"
            type="text"
            value="<?= htmlspecialchars(trim($shakaGoldCardNumber), ENT_QUOTES, 'UTF-8') ?>"
            placeholder="Enter your Shaka Gold Card number"
            inputmode="text"
            autocomplete="off"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base shadow-xs transition focus:border-[var(--sgc-brand-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--sgc-brand-primary)]/30"
            data-goldcard-number
        >
        <p class="text-xs text-slate-500">
            Separate multiple numbers with commas if you are covering more than one party.
        </p>
    </div>

    <div class="flex items-start gap-3 rounded-xl border border-slate-200 bg-white px-4 py-4 shadow-xs">
        <div class="flex h-6 items-center">
            <input
                id="<?= htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') ?>"
                name="buyGoldCard"
                type="checkbox"
                class="h-5 w-5 rounded border-slate-300 text-[var(--sgc-brand-primary)] focus:ring-[var(--sgc-brand-primary)]"
                data-goldcard-upsell
            >
        </div>
        <div class="flex flex-col gap-1">
            <label for="<?= htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') ?>" class="text-base font-semibold text-slate-900">
                <?= htmlspecialchars($upsellLabel, ENT_QUOTES, 'UTF-8') ?>
            </label>
            <p class="text-sm text-slate-600" data-goldcard-upsell-description>
                <?= htmlspecialchars($upsellDescription, ENT_QUOTES, 'UTF-8') ?>
                <span class="font-semibold text-slate-900" data-goldcard-price></span>
            </p>
            <p class="text-xs text-slate-500">
                <?= htmlspecialchars($coverageHint, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
    </div>
</section>
