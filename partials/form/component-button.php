<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$label = $bootstrap['activity']['uiLabels']['bookNowButton'] ?? 'Book Now';
?>
<section class="bg-white border border-slate-200 rounded-xl shadow-sm p-6 space-y-4" data-component="booking-actions">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div class="space-y-1">
            <h2 class="text-lg font-semibold text-slate-900">Ready to reserve?</h2>
            <p class="text-sm text-slate-600">Submit to continue checkout securely with Ponorez.</p>
        </div>
        <button type="submit"
                class="inline-flex items-center justify-center gap-2 rounded-full bg-blue-600 px-6 py-3 text-base font-semibold text-white shadow-sm transition hover:bg-blue-700 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-blue-500"
                data-action="initiate-booking">
            <span data-button-label><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
            <span class="hidden h-4 w-4 animate-spin rounded-full border-2 border-white/60 border-t-white" data-button-spinner></span>
        </button>
    </div>
    <p class="text-xs text-slate-500">
        By continuing you agree to the supplier's cancellation policy and the Ponorez terms of service.
    </p>
</section>
