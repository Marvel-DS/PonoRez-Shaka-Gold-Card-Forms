<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$label = $bootstrap['activity']['uiLabels']['bookNowButton'] ?? 'Book Now';
?>
<section class="rounded-3xl bg-gradient-to-r from-[#1c54db] to-[#0b4f81] p-6 text-white shadow-card" data-component="booking-actions">
    <div class="flex flex-wrap items-center justify-between gap-6">
        <div class="space-y-1">
            <p class="text-xs font-semibold uppercase tracking-[0.35em] text-white/60">Ready to go?</p>
            <h2 class="text-xl font-semibold">Reserve your seats</h2>
            <p class="text-sm text-white/80">Submit the request to lock in availability and continue checkout securely with Ponorez.</p>
        </div>
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-full bg-white px-6 py-3 text-base font-semibold text-[#0b4f81] shadow-lg shadow-slate-900/10 transition hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white/70"
                data-action="initiate-booking">
            <span data-button-label><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-[#0b4f81]/20 border-t-[#0b4f81]" data-button-spinner></span>
        </button>
    </div>
    <p class="mt-4 text-xs text-white/70">
        By continuing you agree to the supplier's cancellation policy and the Ponorez terms of service.
    </p>
</section>
