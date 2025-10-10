<?php

declare(strict_types=1);

/**
 * Render the calendar widget that lets guests choose a booking date.
 */

use PonoRez\SGCForms\UtilityService;

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$apiEndpoints = $page['apiEndpoints'] ?? [];
$label = $bootstrap['activity']['uiLabels']['calendar'] ?? 'When would you like to go?';
$currentDate = $bootstrap['environment']['currentDate'] ?? date('Y-m-d');
$calendarEndpoint = $apiEndpoints['availability'] ?? null;
$showDeveloperHints = UtilityService::getEnvironmentSetting('showDeveloperHints', false);

try {
    $current = new \DateTimeImmutable($currentDate);
} catch (\Exception) {
    $current = new \DateTimeImmutable();
}

$monthStart = $current->modify('first day of this month');
$gridStart = $monthStart->modify(sprintf('-%d days', (int) $monthStart->format('w')));
$today = new \DateTimeImmutable('today');
$todayKey = $today->format('Y-m-d');
$currentMonthKey = $current->format('Y-m');
$currentDateKey = $current->format('Y-m-d');
$weeks = [];

for ($index = 0; $index < 42; $index++) {
    $day = $gridStart->modify(sprintf('+%d days', $index));
    $weeks[(int) floor($index / 7)][] = $day;
}

$weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
$calendarLabelId = 'calendar-label-' . md5($label);
$calendarDescriptionId = 'calendar-instructions-' . md5($currentDateKey);
?>
<section class="space-y-3" data-component="calendar">
    <header>
        <h2 id="<?= htmlspecialchars($calendarLabelId, ENT_QUOTES, 'UTF-8') ?>" class="text-lg font-semibold text-slate-900 mb-0"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p id="<?= htmlspecialchars($calendarDescriptionId, ENT_QUOTES, 'UTF-8') ?>" class="text-sm text-slate-600 mb-0">Pick your preferred date to view available times.</p>
    </header>

    <div class="relative rounded-xl border border-slate-200 p-2 md:p-6 shadow-xs" data-calendar>
        <div class="absolute inset-0 z-10 hidden items-center justify-center rounded-xl bg-white" data-calendar-loading>
            <div class="h-10 w-10 animate-spin rounded-full border-4 border-t-transparent border-[var(--sgc-brand-primary)]"></div>
        </div>

        <div class="flex items-center justify-between" data-calendar-navigation aria-live="polite">
            <button type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-slate-400 hover:text-[var(--sgc-brand-primary)] transition-all duration-300 cursor-pointer"
                    data-action="previous-month"
                    aria-label="Previous month">
                <?= UtilityService::renderSvgIcon('chevron-left.svg', 'h-5 w-5') ?>
            </button>
            <p class="text-base font-semibold text-slate-900" data-calendar-month-label>
                <?= htmlspecialchars($current->format('F Y'), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <button type="button"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-slate-400 hover:text-[var(--sgc-brand-primary)] transition-all duration-300 cursor-pointer"
                    data-action="next-month"
                    aria-label="Next month">
                <?= UtilityService::renderSvgIcon('chevron-right.svg', 'h-5 w-5') ?>
            </button>
        </div>

        <div class="mt-6 grid grid-cols-7 gap-0 md:gap-3 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">
            <?php foreach ($weekdayLabels as $weekday): ?>
                <span><?= htmlspecialchars($weekday, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endforeach; ?>
        </div>

        <div class="mt-4 grid grid-cols-7 gap-0 md:gap-3" role="grid" aria-labelledby="<?= htmlspecialchars($calendarLabelId, ENT_QUOTES, 'UTF-8') ?>" aria-describedby="<?= htmlspecialchars($calendarDescriptionId, ENT_QUOTES, 'UTF-8') ?>">
            <?php foreach ($weeks as $week): ?>
                <div role="row" class="contents">
                <?php foreach ($week as $day): ?>
                    <?php
                    $dateValue = $day->format('Y-m-d');
                    $monthValue = $day->format('Y-m');
                    $isCurrentMonth = $monthValue === $currentMonthKey;
                    $isPast = $day < $today;
                    $isSelected = $dateValue === $currentDateKey;
                    $isToday = $dateValue === $todayKey;
                    ?>
                    <div role="gridcell" class="flex justify-center">
                        <button type="button"
                                class="flex h-12 w-12 items-center justify-center rounded-full text-sm font-medium transition-all focus:outline-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-400/70"
                                data-date="<?= htmlspecialchars($dateValue, ENT_QUOTES, 'UTF-8') ?>"
                                data-month="<?= htmlspecialchars($monthValue, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $isCurrentMonth ? '' : 'data-outside-month="1"' ?>
                                <?= $isSelected ? 'data-selected="1" aria-selected="true"' : 'aria-selected="false"' ?>
                                <?= $isToday ? 'aria-current="date"' : '' ?>
                                <?= $isPast ? 'disabled aria-disabled="true"' : '' ?>>
                            <time datetime="<?= htmlspecialchars($dateValue, ENT_QUOTES, 'UTF-8') ?>"
                                  class="text-base <?= $isCurrentMonth ? 'text-slate-700' : 'text-slate-300' ?>">
                                <?= $day->format('j') ?>
                            </time>
                        </button>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</section>
