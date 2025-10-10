<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$label = $bootstrap['activity']['uiLabels']['bookNowButton'] ?? 'Book Now';
?>

<section data-component="booking-actions">
    <div class="space-y-8">

        <p class="mt-4 text-xs text-white/70">
            By continuing you agree to the supplier's cancellation policy and the Ponorez terms of service.
        </p>

        <button
            type="submit"
            class="w-full rounded-lg bg-[var(--sgc-brand-primary)] px-6 py-4 text-base font-semibold uppercase text-white transition-all duration-300 hover:bg-[var(--sgc-brand-primary-dark)] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:ring-[var(--sgc-brand-primary)]"
            data-action="initiate-booking"
        >
            <span class="flex items-center justify-center gap-3">
                <span data-button-label><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
                <span
                    class="hidden inline-flex h-5 w-5 items-center justify-center"
                    data-button-spinner
                    aria-hidden="true"
                >
                    <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white"></span>
                </span>
            </span>
        </button>

    </div>

</section>
