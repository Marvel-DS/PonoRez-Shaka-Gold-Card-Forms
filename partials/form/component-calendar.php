<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$apiEndpoints = $page['apiEndpoints'] ?? [];
$label = $bootstrap['activity']['uiLabels']['calendar'] ?? 'Choose Your Date';
$currentDate = $bootstrap['environment']['currentDate'] ?? date('Y-m-d');
$calendarEndpoint = $apiEndpoints['availability'] ?? null;

try {
    $current = new \DateTimeImmutable($currentDate);
} catch (\Exception) {
    $current = new \DateTimeImmutable();
}

$monthStart = $current->modify('first day of this month');
$gridStart = $monthStart->modify(sprintf('-%d days', (int) $monthStart->format('w')));
$today = new \DateTimeImmutable('today');
$weeks = [];

for ($index = 0; $index < 42; $index++) {
    $day = $gridStart->modify(sprintf('+%d days', $index));
    $weeks[(int) floor($index / 7)][] = $day;
}

$weekdayLabels = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
?>
<section class="space-y-4" data-component="calendar">
    <header class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-sm text-slate-600">Select a travel date to see available departure times.</p>
        </div>
        <div class="flex items-center gap-2" data-calendar-navigation>
            <button type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 text-slate-600 hover:bg-slate-100"
                    data-action="previous-month"
                    aria-label="Previous month">
                &lt;
            </button>
            <p class="min-w-[140px] text-center text-sm font-medium text-slate-700" data-calendar-month-label>
                <?= htmlspecialchars($current->format('F Y'), ENT_QUOTES, 'UTF-8') ?>
            </p>
            <button type="button"
                    class="inline-flex h-9 w-9 items-center justify-center rounded-full border border-slate-300 text-slate-600 hover:bg-slate-100"
                    data-action="next-month"
                    aria-label="Next month">
                &gt;
            </button>
        </div>
    </header>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-4" data-calendar>
        <div class="grid grid-cols-7 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">
            <?php foreach ($weekdayLabels as $weekday): ?>
                <span><?= htmlspecialchars($weekday, ENT_QUOTES, 'UTF-8') ?></span>
            <?php endforeach; ?>
        </div>
        <div class="mt-2 grid grid-cols-7 gap-2" role="grid" aria-label="Available dates">
            <?php foreach ($weeks as $week): ?>
                <?php foreach ($week as $day): ?>
                    <?php
                    $dateValue = $day->format('Y-m-d');
                    $monthValue = $day->format('Y-m');
                    $isCurrentMonth = $monthValue === $current->format('Y-m');
                    $isPast = $day < $today;
                    $isSelected = $dateValue === $current->format('Y-m-d');
                    ?>
                    <div role="gridcell">
                        <button type="button"
                                class="w-full rounded-lg border border-transparent px-2 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring focus-visible:ring-blue-500/40"
                                data-date="<?= htmlspecialchars($dateValue, ENT_QUOTES, 'UTF-8') ?>"
                                data-month="<?= htmlspecialchars($monthValue, ENT_QUOTES, 'UTF-8') ?>"
                                <?= $isCurrentMonth ? '' : 'data-outside-month="1"' ?>
                                <?= $isSelected ? 'data-selected="1"' : '' ?>
                                <?= $isPast ? 'disabled' : '' ?>>
                            <time datetime="<?= htmlspecialchars($dateValue, ENT_QUOTES, 'UTF-8') ?>"
                                  class="block text-sm <?= $isCurrentMonth ? 'text-slate-900' : 'text-slate-400' ?>">
                                <?= $day->format('j') ?>
                            </time>
                            <span class="mt-1 block text-xs text-slate-400" data-availability-indicator>--</span>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </div>
        <p class="mt-3 text-xs text-slate-400" data-calendar-hint>
            Availability syncs automatically. Select a date to load timeslots.
        </p>
    </div>

    <?php if ($calendarEndpoint): ?>
        <p class="text-xs text-slate-500">
            Developer note: API response preview &mdash;
            <a class="text-blue-600 underline decoration-dotted" href="<?= htmlspecialchars($calendarEndpoint, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($calendarEndpoint, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </p>
    <?php endif; ?>
</section>
