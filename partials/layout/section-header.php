<?php

/**
 * Section Layout: Header
 *
 * Renders supplier branding and an optional CTA that links back to the
 * supplier’s discounted activities. Collapses to a centered stack on mobile
 * and switches to a left/right layout from the `md` breakpoint.
 */

declare(strict_types=1);

$supplierLogoUrl = $supplierLogoUrl ?? null;
$supplierName = $supplierName ?? '';
$homeLink = $homeLink ?? [];
$showHomeLink = $showHomeLink ?? true;

?>

<header class="flex flex-col md:flex-row px-4 md:px-6 gap-4 items-center md:justify-between text-center md:text-left" data-component="form-header" role="banner">

    <!-- Supplier Logo Block -->
    <div class="flex items-center justify-center gap-3 md:justify-start" aria-label="Supplier Logo">
        <?php if (!empty($supplierLogoUrl)): ?>
            <img src="<?= htmlspecialchars($supplierLogoUrl, ENT_QUOTES, 'UTF-8') ?>"
                 alt="<?= htmlspecialchars($supplierName ?: 'Supplier logo', ENT_QUOTES, 'UTF-8') ?>"
                 class="h-16 w-auto"
                 loading="lazy">
        <?php endif; ?>
    </div>

    <?php if ($showHomeLink && !empty($homeLink['enabled']) && !empty($homeLink['label']) && !empty($homeLink['url'])): ?>
        <!-- CTA to supplier's discounted activities list -->
        <a href="<?= htmlspecialchars($homeLink['url'], ENT_QUOTES, 'UTF-8') ?>"
           class="home-link inline-flex items-center justify-center gap-2 rounded-md px-5 py-4 text-xs font-semibold uppercase no-underline text-[var(--sgc-brand-primary)] hover:text-white bg-slate-100 hover:bg-[var(--sgc-brand-primary)] transition-all duration-500"
           aria-label="View all discounted activities for <?= htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($homeLink['label'], ENT_QUOTES, 'UTF-8') ?>
        </a>
    <?php endif; ?>

</header>
