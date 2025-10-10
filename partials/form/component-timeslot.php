<?php

declare(strict_types=1);

/**
 * Component: Timeslots/Departure Times list 
 * 
 * Render the timeslot selection skelton (summary/loading/empty states).
 * The actual list of departures is hydrated client-side after the user picks
 * a date.
 */

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$label = $bootstrap['activity']['uiLabels']['timeslots'] ?? 'Select a Departure Time';
?>
<section class="space-y-5" data-component="timeslots">

    <header>
        <h2 class="text-xl font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600">Pick a check-in time that works best for your group.</p>
    </header>

    <div class="space-y-4" data-timeslot-panel>

        <div class="rounded-xl border border-[var(--sgc-brand-primary)]/20 bg-[var(--sgc-brand-primary)]/10 px-5 py-4 text-sm text-[var(--sgc-brand-primary)] shadow-xs" data-state="summary" role="status" aria-live="polite">
            Select a date to see available departure times.
        </div>

        <div class="hidden rounded-xl border border-slate-200 bg-white px-5 py-5 shadow-xs" data-state="loading">
            <div class="flex items-center gap-3 text-sm text-slate-600">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-[var(--sgc-brand-primary)]"></span>
                Fetching the latest availability&hellip;
            </div>
        </div>

        <div class="hidden rounded-xl border border-slate-200 bg-white px-5 py-5 text-sm text-slate-600 shadow-xs" data-state="empty">
            No departures are available for the selected date. Try another date or adjust your guest counts.
        </div>

        <div class="hidden space-y-4" data-timeslot-list role="radiogroup" aria-label="Available departure times">
            <!-- Timeslots injected by JavaScript -->
        </div>
        
    </div>
</section>
