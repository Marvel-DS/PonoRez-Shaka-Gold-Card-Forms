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
<section class="space-y-4" data-component="upgrades">
    <header>
        <h2 class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600">Enhance your experience with optional upgrades.</p>
    </header>

    <div class="space-y-3" data-upgrade-items>
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
            ?>
            <div class="bg-white border border-slate-200 rounded-lg p-4 shadow-sm flex flex-wrap items-center justify-between gap-4"
                 data-upgrade-id="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                 <?= $price !== null ? 'data-price="' . htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                 <?= $max !== null ? 'data-max="' . $max . '"' : '' ?>
                 data-min="<?= $min ?>">
                <div class="space-y-1">
                    <div>
                        <p class="font-medium text-slate-900">
                            <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($price !== null): ?>
                                <span class="ml-2 text-sm text-blue-600">$<?= number_format($price, 2) ?></span>
                            <?php endif; ?>
                        </p>
                        <?php if ($description): ?>
                            <p class="text-sm text-slate-500 mt-1"><?= htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') ?></p>
                        <?php endif; ?>
                    </div>
                <div class="flex items-center gap-2">
                    <label class="text-xs text-slate-500" for="upgrade-<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>">Quantity</label>
                    <div class="flex items-center gap-2" data-upgrade-counter>
                        <button type="button"
                                class="h-8 w-8 rounded-full border border-slate-300 text-slate-600 hover:bg-slate-100"
                                data-action="decrement"
                                aria-label="Decrease <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>">
                            -
                        </button>
                        <input type="number"
                               id="upgrade-<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                               name="upgrades[<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>]"
                               value="<?= $min ?>"
                               min="<?= $min ?>"
                               <?= $max !== null ? 'max="' . $max . '"' : '' ?>
                               class="w-20 rounded border border-slate-300 px-2 py-1 text-right"
                               inputmode="numeric">
                        <button type="button"
                                class="h-8 w-8 rounded-full border border-slate-300 text-slate-600 hover:bg-slate-100"
                                data-action="increment"
                                aria-label="Increase <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>">
                            +
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
