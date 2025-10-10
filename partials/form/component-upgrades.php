<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$upgrades = $bootstrap['activity']['upgrades'] ?? [];

if ($upgrades === []) {
    return;
}

$label = $bootstrap['activity']['uiLabels']['upgrades'] ?? 'Optional Upgrades';
?>
<section class="space-y-5" data-component="upgrades">
    <header class="space-y-1">
        <h2 class="text-xl font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600">Enhance your experience with optional upgrades.</p>
    </header>

    <div class="space-y-4" data-upgrade-items>
        <?php foreach ($upgrades as $upgrade): ?>
            <?php
            if (($upgrade['enabled'] ?? true) === false) {
                continue;
            }

            $id = (string) ($upgrade['id'] ?? '');
            if ($id === '') {
                continue;
            }

            $labelText = $upgrade['label'] ?? $id;
            $description = $upgrade['description'] ?? null;
            $price = isset($upgrade['price']) ? (float) $upgrade['price'] : null;
            $max = isset($upgrade['maxQuantity']) ? (int) $upgrade['maxQuantity'] : null;
            $min = isset($upgrade['minQuantity']) ? (int) $upgrade['minQuantity'] : 0;
            $priceDisplay = null;

            if ($price !== null) {
                if ($price > 0.0) {
                    $priceDisplay = '+ $' . number_format($price, 2);
                } elseif ($price === 0.0) {
                    $priceDisplay = 'Included';
                }
            }
            ?>
            <div
                class="w-full"
                data-upgrade-id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                <?= $price !== null ? 'data-price="' . htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                <?= $max !== null ? 'data-max="' . $max . '"' : '' ?>
                data-min="<?= $min ?>"
            >
                <div
                    class="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-xs transition-all duration-300"
                    data-upgrade-card
                >
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="flex items-start gap-4">
                            <span
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-slate-300 bg-white text-white transition-all duration-300"
                                aria-hidden="true"
                                data-upgrade-indicator
                            >
                                <span class="flex h-5 w-5 items-center justify-center" data-upgrade-icon></span>
                            </span>

                            <div class="flex flex-col gap-1 text-left">
                                <p class="text-lg font-semibold tracking-tight text-slate-900">
                                    <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($priceDisplay && $price > 0.0): ?>
                                        <span class="ml-2 text-sm font-medium text-slate-500"><?= htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php elseif ($priceDisplay): ?>
                                        <span class="ml-2 text-sm font-semibold text-green-600"><?= htmlspecialchars($priceDisplay, ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php endif; ?>
                                </p>

                                <?php if ($description): ?>
                                    <p class="text-sm text-slate-500"><?= htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') ?></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="flex flex-col items-start gap-2 sm:flex-row sm:items-center">
                            <label class="text-xs font-medium uppercase tracking-wide text-slate-500" for="upgrade-<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">
                                Quantity
                            </label>
                            <div class="flex items-center gap-2" data-upgrade-counter>
                                <button
                                    type="button"
                                    class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 text-base font-semibold text-slate-600 transition hover:bg-slate-100"
                                    data-action="decrement"
                                    aria-label="Decrease <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    &minus;
                                </button>
                                <input
                                    type="number"
                                    id="upgrade-<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                                    name="upgrades[<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>]"
                                    value="<?= $min ?>"
                                    min="<?= $min ?>"
                                    <?= $max !== null ? 'max="' . $max . '"' : '' ?>
                                    class="w-20 rounded-lg border border-slate-300 bg-white px-2 py-2 text-right text-base font-semibold text-slate-900 focus:border-[var(--sgc-brand-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--sgc-brand-primary)]"
                                    inputmode="numeric"
                                >
                                <button
                                    type="button"
                                    class="flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 text-base font-semibold text-slate-600 transition hover:bg-slate-100"
                                    data-action="increment"
                                    aria-label="Increase <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>"
                                >
                                    +
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
