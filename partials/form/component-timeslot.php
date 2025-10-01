<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$apiEndpoints = $page['apiEndpoints'] ?? [];
$label = $bootstrap['activity']['uiLabels']['timeslots'] ?? 'Choose Departure Time';
$timeslotEndpoint = $apiEndpoints['availability'] ?? null;
?>
<section class="space-y-4" data-component="timeslots">
    <header class="space-y-1">
        <h2 class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600">Pick a departure time after selecting your date.</p>
    </header>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm" data-timeslot-panel>
        <div class="border-b border-slate-200 px-4 py-3 text-sm text-slate-500" data-state="summary" role="status" aria-live="polite">
            Select a date to load available timeslots.
        </div>
        <div class="hidden px-4 py-4" data-state="loading">
            <div class="flex items-center gap-3 text-sm text-slate-500">
                <span class="h-3 w-3 animate-spin rounded-full border-2 border-slate-300 border-t-blue-500"></span>
                Fetching latest availability...
            </div>
        </div>
        <div class="hidden px-4 py-4 text-sm text-slate-500" data-state="empty">
            No departures are available for the selected date. Try another date or adjust your guest counts.
        </div>
        <ul class="hidden divide-y divide-slate-200" data-timeslot-list role="radiogroup">
            <!-- Timeslots injected by JavaScript -->
        </ul>
        <div class="hidden border-t border-slate-200 bg-slate-50 px-4 py-3 text-xs font-mono text-slate-600 whitespace-pre-wrap break-words" data-availability-metadata></div>
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
