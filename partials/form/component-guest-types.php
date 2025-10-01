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

$defaultGuestRange = 10;

$guestTypes = [];
foreach ($ids as $id) {
    $stringId = (string) $id;
    $minValue = isset($min[$stringId]) ? max(0, (int) $min[$stringId]) : 0;
    $maxCandidate = isset($max[$stringId]) ? max(0, (int) $max[$stringId]) : 0;
    $fallbackMax = $minValue + $defaultGuestRange;
    $maxValue = $maxCandidate >= $minValue ? $maxCandidate : $fallbackMax;
    $controlType = $minValue === 1 && $maxValue === 1 ? 'checkbox' : 'select';

    $guestTypes[] = [
        'id' => $stringId,
        'label' => $labels[$stringId] ?? $stringId,
        'description' => $descriptions[$stringId] ?? null,
        'min' => $minValue,
        'max' => max($maxValue, $minValue),
        'fallbackMax' => $fallbackMax,
        'control' => $controlType,
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
                $sanitisedId = preg_replace('/[^a-zA-Z0-9_-]/', '', $guestType['id']);
                $selectId = sprintf('guest-count-%s', $sanitisedId);
                $checkboxId = sprintf('guest-toggle-%s', $sanitisedId);
                $labelId = sprintf('guest-label-%s', $sanitisedId);
                $description = $guestType['description'];
            ?>
            <?php $isCheckboxControl = $guestType['control'] === 'checkbox'; ?>
            <?php if ($isCheckboxControl && $minValue === 1 && $maxValue === 1): ?>
                <div class="flex flex-wrap items-center justify-between"
                     data-guest-type="<?= htmlspecialchars($guestType['id'], ENT_QUOTES, 'UTF-8') ?>"
                     data-min="<?= $minValue ?>"
                     data-max="<?= $maxValue ?>"
                     data-fallback-max="<?= $fallbackMax ?>">
                    <div class="flex items-center gap-2 min-w-0">
                        <div class="relative">
                            <input type="hidden"
                                   name="guestCounts[<?= htmlspecialchars($guestType['id'], ENT_QUOTES, 'UTF-8') ?>]"
                                   value="<?= $minValue ?>"
                                   data-guest-checkbox-input>
                            <input id="<?= htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') ?>"
                                   type="checkbox"
                                   class="h-5 w-5 rounded border-slate-300 text-blue-600 focus:ring-blue-500"
                                   aria-labelledby="<?= htmlspecialchars($labelId, ENT_QUOTES, 'UTF-8') ?>"
                                   data-guest-checkbox
                                   data-checked-value="<?= $minValue ?>"
                                   data-unchecked-value="0"
                                   value="<?= $minValue ?>"
                                   checked>
                        </div>
                        <p class="font-medium mb-0" id="<?= htmlspecialchars($labelId, ENT_QUOTES, 'UTF-8') ?>" data-guest-label><?= htmlspecialchars($guestType['label'], ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                </div>
            <?php else: ?>
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
                                    class="h-12 w-20 appearance-none rounded-l-xl bg-blue-600 px-3 pr-8 text-center text-base font-normal text-white shadow-sm focus:outline-none"
                                    data-guest-select>
                                <?php for ($value = $minValue; $value <= $maxValue; $value++): ?>
                                    <option value="<?= $value ?>"<?= $value === $minValue ? ' selected' : '' ?>><?= $value ?></option>
                                <?php endfor; ?>
                            </select>
                            <span aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-sm text-blue-50">
                                <?php
                                    $chevronIcon = file_get_contents(dirname(__DIR__, 2) . '/assets/icons/outline/chevron-up-down.svg');
                                    if ($chevronIcon !== false) {
                                        echo str_replace('<svg', '<svg class="h-5 w-5" stroke-width="2"', $chevronIcon);
                                    }
                                ?>
                            </span>
                        </div>
                        <div class="min-w-0 space-y-0">
                            <p class="font-medium -mb-0.5" id="<?= htmlspecialchars($labelId, ENT_QUOTES, 'UTF-8') ?>" data-guest-label><?= htmlspecialchars($guestType['label'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs text-slate-500 mb-0<?= $description === null || $description === '' ? ' hidden' : '' ?>"
                               data-guest-description><?= htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-normal text-slate-900 mb-0" data-guest-price>--</p>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
