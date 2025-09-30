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

$guestTypes = [];
foreach ($ids as $id) {
    $stringId = (string) $id;
    $guestTypes[] = [
        'id' => $stringId,
        'label' => $labels[$stringId] ?? $stringId,
        'description' => $descriptions[$stringId] ?? null,
        'min' => isset($min[$stringId]) ? max(0, (int) $min[$stringId]) : 0,
        'max' => isset($max[$stringId]) ? max(0, (int) $max[$stringId]) : 0,
    ];
}

$label = $bootstrap['activity']['uiLabels']['guestTypes'] ?? 'How many people are in your group?';
?>
<section class="space-y-6" data-component="guest-types">
    <header class="space-y-1">
        <h2 class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600">Adjust the number of guests for each category.</p>
    </header>

    <div class="space-y-3" data-guest-types>
        <?php foreach ($guestTypes as $guestType): ?>
            <?php
                $minValue = $guestType['min'];
                $maxValue = $guestType['max'] > $minValue ? $guestType['max'] : $minValue;
                $selectId = sprintf('guest-count-%s', preg_replace('/[^a-zA-Z0-9_-]/', '', $guestType['id']));
                $description = $guestType['description'];
            ?>
            <div class="flex flex-wrap items-center justify-between gap-6 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"
                 data-guest-type="<?= htmlspecialchars($guestType['id'], ENT_QUOTES, 'UTF-8') ?>"
                 data-min="<?= $minValue ?>"
                 data-max="<?= $maxValue ?>">
                <div class="flex items-center gap-4 min-w-0">
                    <div class="relative">
                        <label class="sr-only" for="<?= htmlspecialchars($selectId, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars(sprintf('Guest count for %s', $guestType['label']), ENT_QUOTES, 'UTF-8') ?>
                        </label>
                        <select id="<?= htmlspecialchars($selectId, ENT_QUOTES, 'UTF-8') ?>"
                                name="guestCounts[<?= htmlspecialchars($guestType['id'], ENT_QUOTES, 'UTF-8') ?>]"
                                class="h-11 w-20 appearance-none rounded-xl border border-blue-100 bg-blue-600 px-3 pr-8 text-center text-base font-semibold text-white shadow-sm focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-300"
                                data-guest-select>
                            <?php for ($value = $minValue; $value <= $maxValue; $value++): ?>
                                <option value="<?= $value ?>"<?= $value === $minValue ? ' selected' : '' ?>><?= $value ?></option>
                            <?php endfor; ?>
                        </select>
                        <span aria-hidden="true" class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-xs text-blue-100">&#9662;</span>
                    </div>
                    <div class="min-w-0 space-y-1">
                        <p class="font-medium text-slate-900" data-guest-label><?= htmlspecialchars($guestType['label'], ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="text-sm text-slate-500<?= $description === null || $description === '' ? ' hidden' : '' ?>"
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
