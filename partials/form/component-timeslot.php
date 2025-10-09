<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$label = $bootstrap['activity']['uiLabels']['timeslots'] ?? 'Select a Time';
?>
<section class="space-y-5" data-component="timeslots">
    <header class="space-y-1">
        <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">Departure Times</p>
        <h2 class="text-xl font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600">Choose the departure that fits your schedule. Availability updates instantly after each change.</p>
    </header>

    <div class="space-y-4" data-timeslot-panel>
        <div class="rounded-2xl border border-slate-200 bg-white px-5 py-4 text-sm text-slate-600 shadow-sm" data-state="summary" role="status" aria-live="polite">
            Select a date to load available departures.
        </div>

        <div class="hidden rounded-2xl border border-slate-200 bg-white px-5 py-5 shadow-sm" data-state="loading">
            <div class="flex items-center gap-3 text-sm text-slate-600">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-[var(--sgc-brand-primary)]"></span>
                Fetching the latest availability&hellip;
            </div>
        </div>

        <div class="hidden rounded-2xl border border-slate-200 bg-white px-5 py-5 text-sm text-slate-600 shadow-sm" data-state="empty">
            No departures are available for the selected date. Try another date or adjust your guest counts.
        </div>

        <div class="hidden space-y-4" data-timeslot-list role="radiogroup" aria-label="Available departure times">
            <!-- Timeslots injected by JavaScript -->
        </div>
    </div>
</section>
