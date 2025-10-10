<?php

declare(strict_types=1);

use PonoRez\SGCForms\UtilityService;

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activityConfig = $page['activity'] ?? [];

$bootstrapUpgrades = is_array($bootstrap['activity']['upgrades'] ?? null)
    ? $bootstrap['activity']['upgrades']
    : [];

if ($bootstrapUpgrades === []) {
    return;
}

$configUpgrades = is_array($activityConfig['upgradesConfig'] ?? null)
    ? $activityConfig['upgradesConfig']
    : [];

$configUpgradesById = [];
foreach ($configUpgrades as $configUpgrade) {
    if (!is_array($configUpgrade) || !isset($configUpgrade['id'])) {
        continue;
    }

    $configId = (string) $configUpgrade['id'];
    if ($configId === '') {
        continue;
    }

    $configUpgradesById[$configId] = $configUpgrade;
}

$firstNonEmptyString = static function (array $candidates): ?string {
    foreach ($candidates as $candidate) {
        if ($candidate === null) {
            continue;
        }

        if (is_scalar($candidate)) {
            $string = trim((string) $candidate);
            if ($string !== '') {
                return $string;
            }
        }
    }

    return null;
};

$firstNumericValue = static function (array $candidates): ?float {
    foreach ($candidates as $candidate) {
        if ($candidate === null || $candidate === '') {
            continue;
        }

        if (is_string($candidate)) {
            $candidate = trim($candidate);
            if ($candidate === '') {
                continue;
            }
        }

        if (is_numeric($candidate)) {
            return (float) $candidate;
        }
    }

    return null;
};

$sanitizeQuantity = static function (mixed $value): ?int {
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_numeric($value)) {
        return null;
    }

    $intValue = (int) $value;
    return $intValue < 0 ? 0 : $intValue;
};

$currencySymbol = $bootstrap['activity']['currency']['symbol'] ?? '$';
$chevronIcon = UtilityService::renderSvgIcon('outline/chevron-up-down.svg', 'h-5 w-5', '2');

$normalizedUpgrades = [];

foreach ($bootstrapUpgrades as $upgrade) {
    if (!is_array($upgrade) || !isset($upgrade['id'])) {
        continue;
    }

    if (($upgrade['enabled'] ?? true) === false) {
        continue;
    }

    $id = (string) $upgrade['id'];
    if ($id === '') {
        continue;
    }

    $configEntry = $configUpgradesById[$id] ?? null;
    if (is_array($configEntry) && ($configEntry['enabled'] ?? true) === false) {
        continue;
    }

    $label = $firstNonEmptyString([
        $configEntry['label'] ?? null,
        $configEntry['name'] ?? null,
        $configEntry['title'] ?? null,
        $upgrade['label'] ?? null,
        $upgrade['name'] ?? null,
        $upgrade['title'] ?? null,
    ]) ?? $id;

    $description = $firstNonEmptyString([
        $configEntry['description'] ?? null,
        $configEntry['details'] ?? null,
        $configEntry['summary'] ?? null,
        $upgrade['description'] ?? null,
        $upgrade['details'] ?? null,
        $upgrade['summary'] ?? null,
    ]);

    $price = $firstNumericValue([
        $configEntry['price'] ?? null,
        $configEntry['amount'] ?? null,
        $configEntry['rate'] ?? null,
        $upgrade['price'] ?? null,
        $upgrade['amount'] ?? null,
        $upgrade['rate'] ?? null,
    ]);

    $minQuantity = null;
    if (is_array($configEntry) && array_key_exists('minQuantity', $configEntry)) {
        $minQuantity = $sanitizeQuantity($configEntry['minQuantity']);
    }

    if ($minQuantity === null) {
        $minQuantity = $sanitizeQuantity($upgrade['minQuantity'] ?? null);
    }

    if ($minQuantity === null) {
        $minQuantity = 0;
    }

    $maxQuantity = null;
    if (is_array($configEntry) && array_key_exists('maxQuantity', $configEntry)) {
        $maxQuantity = $sanitizeQuantity($configEntry['maxQuantity']);
    }

    if ($maxQuantity === null && array_key_exists('maxQuantity', $upgrade)) {
        $maxQuantity = $sanitizeQuantity($upgrade['maxQuantity']);
    }

    if ($maxQuantity !== null) {
        $maxQuantity = max($maxQuantity, $minQuantity);
    }

    $priceDisplay = null;
    if ($price !== null) {
        if ($price > 0.0) {
            $priceDisplay = sprintf('+ %s%s', $currencySymbol, number_format($price, 2));
        } elseif ($price === 0.0) {
            $priceDisplay = 'Included';
        }
    }

    $sanitizedId = preg_replace('/[^a-zA-Z0-9_-]/', '', $id);
    $selectId = sprintf('upgrade-count-%s', $sanitizedId);

    $normalizedUpgrades[] = [
        'id' => $id,
        'label' => $label,
        'description' => $description,
        'price' => $price,
        'priceDisplay' => $priceDisplay,
        'min' => $minQuantity,
        'max' => $maxQuantity,
        'selectId' => $selectId,
    ];
}

if ($normalizedUpgrades === []) {
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
        <?php foreach ($normalizedUpgrades as $upgrade): ?>
            <?php
                $min = $upgrade['min'];
                $max = $upgrade['max'];
                $maxOption = $max ?? $min;
            ?>
            <div
                class="flex flex-wrap items-center justify-between gap-6 pe-3 rounded-xl border border-slate-200 bg-white shadow-xs"
                data-upgrade-id="<?= htmlspecialchars($upgrade['id'], ENT_QUOTES, 'UTF-8') ?>"
                data-min="<?= $min ?>"
                <?= $upgrade['price'] !== null ? 'data-price="' . htmlspecialchars((string) $upgrade['price'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                <?= $max !== null ? 'data-max="' . $max . '"' : '' ?>
            >
                <div class="flex items-center gap-4 min-w-0">
                    <div class="relative">
                        <label class="sr-only" for="<?= htmlspecialchars($upgrade['selectId'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(sprintf('Quantity for %s', $upgrade['label']), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select
                            id="<?= htmlspecialchars($upgrade['selectId'], ENT_QUOTES, 'UTF-8') ?>"
                            name="upgrades[<?= htmlspecialchars($upgrade['id'], ENT_QUOTES, 'UTF-8') ?>]"
                            class="h-14 w-20 appearance-none rounded-l-xl bg-[var(--sgc-brand-primary)] px-3 pr-8 pl-5 text-center text-base font-normal text-white shadow-sm focus:outline-none"
                            data-upgrade-select
                        >
                            <?php for ($value = $min; $value <= $maxOption; $value++): ?>
                                <option value="<?= $value ?>"<?= $value === $min ? ' selected' : '' ?>><?= $value ?></option>
                            <?php endfor; ?>
                        </select>
                        <span aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-sm text-blue-50">
                            <?= $chevronIcon ?>
                        </span>
                    </div>
                    <div class="min-w-0 space-y-0">
                        <p class="font-medium -mb-0.5 text-slate-900">
                            <?= htmlspecialchars($upgrade['label'], ENT_QUOTES, 'UTF-8') ?>
                            <?php if ($upgrade['priceDisplay'] !== null): ?>
                                <?php $priceClass = ($upgrade['price'] ?? null) === 0.0 ? 'text-green-600' : 'text-slate-900'; ?>
                                <span class="ml-2 text-sm font-semibold <?= $priceClass ?>">
                                    <?= htmlspecialchars($upgrade['priceDisplay'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                            <?php endif; ?>
                        </p>
                        <p class="text-xs text-slate-500 mb-0<?= $upgrade['description'] === null ? ' hidden' : '' ?>">
                            <?= htmlspecialchars((string) $upgrade['description'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
