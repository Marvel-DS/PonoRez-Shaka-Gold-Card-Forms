<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$apiEndpoints = $page['apiEndpoints'] ?? [];
$label = $bootstrap['activity']['uiLabels']['timeslots'] ?? 'Choose Departure Time';
$timeslotEndpoint = $apiEndpoints['availability'] ?? null;
?>
<section class="space-y-6" data-component="timeslots">
    <header class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Departure times</p>
        <h2 class="text-2xl font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600">Choose your preferred departure to see pricing and savings details.</p>
    </header>

    <div class="rounded-3xl border border-slate-200 bg-white shadow-sm" data-timeslot-panel>
        <div class="border-b border-slate-200 px-6 py-4 text-sm text-slate-600" data-state="summary" role="status" aria-live="polite">
            Select a date to load available departure times.
        </div>
        <div class="hidden px-6 py-6" data-state="loading">
            <div class="flex items-center gap-3 text-sm text-slate-600">
                <span class="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-blue-500"></span>
                Fetching the latest availability…
            </div>
        </div>
        <div class="hidden px-6 py-6 text-sm text-slate-600" data-state="empty">
            No departures are available for the selected date. Try another date or adjust your guest counts.
        </div>
        <ul class="hidden list-none space-y-4 px-6 py-6" data-timeslot-list role="radiogroup">
            <!-- Timeslots injected by JavaScript -->
        </ul>
    </div>

    <?php if ($timeslotEndpoint): ?>
        <p class="text-xs text-slate-500">
            Developer note: availability and timeslots share the same endpoint &mdash;
            <a class="text-blue-600 underline decoration-dotted" href="<?= htmlspecialchars($timeslotEndpoint, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($timeslotEndpoint, ENT_QUOTES, 'UTF-8') ?>
            </a>
        </p>
    <?php endif; ?>
</section>
