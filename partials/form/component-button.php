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

        <button type="button" class="w-full p-4 text-center rounded-lg text-base font-semibold uppercase text-white bg-[var(--sgc-brand-primary)] hover:bg-[var(--sgc-brand-primary-dark)] transition-all duration-300 cursor-pointer" aria-label="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
        </button>

    </div>

</section>
