<?php

declare(strict_types=1);

use PonoRez\SGCForms\UtilityService;

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activityConfig = $page['activity'] ?? [];
$isPrivateActivity = filter_var($bootstrap['activity']['privateActivity'] ?? false, FILTER_VALIDATE_BOOLEAN);

$guestTypeEntries = UtilityService::getGuestTypes($activityConfig);
$bootstrapGuestTypes = [];

if (isset($bootstrap['activity']['guestTypes']['byId'])
    && is_array($bootstrap['activity']['guestTypes']['byId'])
) {
    $bootstrapGuestTypes = $bootstrap['activity']['guestTypes']['byId'];
}

if ($guestTypeEntries === []) {
    $guestTypeEntries = $bootstrap['activity']['guestTypes']['collection'] ?? [];
}

$defaultGuestRange = 10;
$chevronIcon = UtilityService::renderSvgIcon('outline/chevron-up-down.svg', 'h-5 w-5', '2');

$guestTypes = [];
foreach ($guestTypeEntries as $guestType) {
    if (!is_array($guestType) || !isset($guestType['id'])) {
        continue;
    }

    $stringId = (string) $guestType['id'];
    if ($stringId === '') {
        continue;
    }

    $label = isset($guestType['label']) && $guestType['label'] !== ''
        ? (string) $guestType['label']
        : $stringId;

    $bootstrapLabel = $bootstrapGuestTypes[$stringId]['label'] ?? null;
    if (is_string($bootstrapLabel)) {
        $trimmedLabel = trim($bootstrapLabel);
        if ($trimmedLabel !== '') {
            $label = $trimmedLabel;
        }
    }

    $description = isset($guestType['description']) && $guestType['description'] !== ''
        ? (string) $guestType['description']
        : null;

    $bootstrapDescription = $bootstrapGuestTypes[$stringId]['description'] ?? null;
    if (($description === null || $description === '') && is_string($bootstrapDescription)) {
        $trimmedDescription = trim($bootstrapDescription);
        if ($trimmedDescription !== '') {
            $description = $trimmedDescription;
        }
    }

    $minValue = isset($guestType['minQuantity']) ? max(0, (int) $guestType['minQuantity']) : 0;
    $maxCandidate = isset($guestType['maxQuantity']) && $guestType['maxQuantity'] !== null && $guestType['maxQuantity'] !== ''
        ? max(0, (int) $guestType['maxQuantity'])
        : 0;
    $fallbackMax = $minValue + $defaultGuestRange;
    $maxValue = $maxCandidate >= $minValue && $maxCandidate > 0 ? $maxCandidate : $fallbackMax;
    $controlType = $minValue === 1 && $maxValue === 1 ? 'checkbox' : 'select';

    $guestTypes[] = [
        'id' => $stringId,
        'label' => $label,
        'description' => $description,
        'min' => $minValue,
        'max' => max($maxValue, $minValue),
        'fallbackMax' => $fallbackMax,
        'control' => $controlType,
    ];
}

$label = $bootstrap['activity']['uiLabels']['guestTypes'] ?? 'How many people are in your group?';
?>
<section class="space-y-4" data-component="guest-types">

    <header>
        <h2 class="text-lg font-semibold mb-0"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600 mb-0">Adjust the number of guests for each category.</p>
    </header>

    <div class="space-y-4" data-guest-types>
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
                <div class="flex flex-wrap items-center justify-between hidden"
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
                                    class="h-14 w-20 appearance-none rounded-l-xl bg-[var(--sgc-brand-primary)] px-3 pr-8 pl-5 text-center text-base font-normal text-white shadow-sm focus:outline-none"
                                    data-guest-select>
                                <?php for ($value = $minValue; $value <= $maxValue; $value++): ?>
                                    <option value="<?= $value ?>"<?= $value === $minValue ? ' selected' : '' ?>><?= $value ?></option>
                                <?php endfor; ?>
                            </select>
                            <span aria-hidden="true" class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-sm text-blue-50">
                                <?= $chevronIcon ?>
                            </span>
                        </div>
                        <div class="min-w-0 space-y-0">
                            <p class="font-medium -mb-0.5" id="<?= htmlspecialchars($labelId, ENT_QUOTES, 'UTF-8') ?>" data-guest-label><?= htmlspecialchars($guestType['label'], ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="text-xs text-slate-500 mb-0<?= $description === null || $description === '' ? ' hidden' : '' ?>"
                               data-guest-description><?= htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                    <?php if (! $isPrivateActivity): ?>
                        <div class="flex items-center text-right h-14 pl-4 border-l border-slate-200">
                            <p class="font-medium text-slate-900 mb-0" data-guest-price>--</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
</section>
