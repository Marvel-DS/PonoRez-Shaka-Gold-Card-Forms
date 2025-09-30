<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$guestConfig = $bootstrap['activity']['guestTypes'] ?? [];
$labels = $guestConfig['labels'] ?? [];
$descriptions = $guestConfig['descriptions'] ?? [];
$min = $guestConfig['min'] ?? [];
$max = $guestConfig['max'] ?? [];
$ids = $guestConfig['ids'] ?? [];

$defaultGuestRange = 9;

$guestTypes = [];
foreach ($ids as $id) {
    $stringId = (string) $id;
    $minValue = isset($min[$stringId]) ? max(0, (int) $min[$stringId]) : 0;
    $maxCandidate = isset($max[$stringId]) ? max(0, (int) $max[$stringId]) : 0;
    $fallbackMax = $minValue + $defaultGuestRange;
    $maxValue = $maxCandidate > $minValue ? $maxCandidate : $fallbackMax;

    $guestTypes[] = [
        'id' => $stringId,
        'label' => $labels[$stringId] ?? $stringId,
        'description' => $descriptions[$stringId] ?? null,
        'min' => $minValue,
        'max' => max($maxValue, $minValue),
        'fallbackMax' => $fallbackMax,
    ];
}

$label = $bootstrap['activity']['uiLabels']['guestTypes'] ?? 'How many people are in your group?';
?>
<section class="space-y-3" data-component="guest-types">
    <header>
        <h2 class="text-lg font-semibold mb-0"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600 mb-0">Adjust the number of guests for each category.</p>
    </header>

    <div class="space-y-3" data-guest-types>
        <?php foreach ($guestTypes as $guestType): ?>
            <?php
                $minValue = $guestType['min'];
                $maxValue = max($guestType['max'], $minValue);
                $fallbackMax = max($guestType['fallbackMax'], $minValue);
                $selectId = sprintf('guest-count-%s', preg_replace('/[^a-zA-Z0-9_-]/', '', $guestType['id']));
                $description = $guestType['description'];
            ?>
            <div class="flex flex-wrap items-center justify-between gap-6 pe-3 rounded-xl border border-slate-200 shadow-xs"
                 data-guest-type="<?= htmlspecialchars($guestType['id'], ENT_QUOTES, 'UTF-8') ?>"
                 data-min="<?= $minValue ?>"
                 data-max="<?= $maxValue ?>"
                 data-fallback-max="<?= $fallbackMax ?>">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="relative">
                        <label class="sr-only" for="<?= htmlspecialchars($selectId, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(sprintf('Guest count for %s', $guestType['label']), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select id="<?= htmlspecialchars($selectId, ENT_QUOTES, 'UTF-8') ?>"
                                name="guestCounts[<?= htmlspecialchars($guestType['id'], ENT_QUOTES, 'UTF-8') ?>]"
                                class="h-12 w-20 appearance-none rounded-l-xl bg-blue-600 px-3 pr-8 text-center text-base font-semibold text-white shadow-sm focus:outline-none"
                                data-guest-select>
                            <?php for ($value = $minValue; $value <= $maxValue; $value++): ?>
                                <option value="<?= $value ?>"<?= $value === $minValue ? ' selected' : '' ?>><?= $value ?></option>
                            <?php endfor; ?>
                        </select>
                        <span aria-hidden="true" class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs text-blue-100">
                            <?php include dirname(__DIR__) . '/../assets/icons/outline/chevron-up-down.svg'; ?>
                        </span>
                    </div>
                    <div class="min-w-0 space-y-0">
                        <p class="font-medium mb-0" data-guest-label><?= htmlspecialchars($guestType['label'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-xs text-slate-500 mb-0<?= $description === null || $description === '' ? ' hidden' : '' ?>"
                           data-guest-description><?= htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
                <div class="text-right">
                    <p class="text-sm font-semibold text-slate-900" data-guest-price>--</p>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
