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
        'min' => isset($min[$stringId]) ? (int) $min[$stringId] : 0,
        'max' => isset($max[$stringId]) ? (int) $max[$stringId] : 0,
    ];
}

$label = $bootstrap['activity']['uiLabels']['guestTypes'] ?? 'How many people are in your group?';
?>
<section class="space-y-4" data-component="guest-types">
    <header class="space-y-1">
        <h2 class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600">Adjust the number of guests for each category.</p>
    </header>

    <div class="space-y-3" data-guest-types>
        <?php foreach ($guestTypes as $guestType): ?>
            <div class="flex flex-wrap items-center justify-between gap-4 bg-white border border-slate-200 rounded-xl p-4 shadow-sm"
                 data-guest-type="<?= htmlspecialchars($guestType['id'], ENT_QUOTES, 'UTF-8') ?>"
                 data-min="<?= $guestType['min'] ?>"
                 data-max="<?= $guestType['max'] ?>">
                <div class="flex-1 min-w-[220px] space-y-1">
                    <p class="font-medium text-slate-900" data-guest-label><?= htmlspecialchars($guestType['label'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php if ($guestType['description'] !== null): ?>
                        <p class="text-sm text-slate-500" data-guest-description><?= htmlspecialchars((string) $guestType['description'], ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <p class="text-sm text-slate-500" data-guest-price>--</p>
                </div>
                <div class="flex items-center gap-2" data-guest-counter>
                    <button type="button"
                            class="h-9 w-9 rounded-full border border-slate-300 text-slate-600 hover:bg-slate-100"
                            data-action="decrement"
                            aria-label="Decrease <?= htmlspecialchars($guestType['label'], ENT_QUOTES, 'UTF-8') ?>">
                        -
                    </button>
                    <input type="number"
                           name="guestCounts[<?= htmlspecialchars($guestType['id'], ENT_QUOTES, 'UTF-8') ?>]"
                           min="<?= $guestType['min'] ?>"
                           max="<?= $guestType['max'] ?>"
                           value="<?= $guestType['min'] ?>"
                           class="w-20 rounded border border-slate-300 px-2 py-1 text-right"
                           inputmode="numeric"
                           aria-label="Guest count for <?= htmlspecialchars($guestType['label'], ENT_QUOTES, 'UTF-8') ?>">
                    <button type="button"
                            class="h-9 w-9 rounded-full border border-slate-300 text-slate-600 hover:bg-slate-100"
                            data-action="increment"
                            aria-label="Increase <?= htmlspecialchars($guestType['label'], ENT_QUOTES, 'UTF-8') ?>">
                        +
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
