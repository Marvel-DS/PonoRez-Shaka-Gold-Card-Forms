<?php

declare(strict_types=1);

use PonoRez\SGCForms\UtilityService;

/**
 * Shared footer for supplier pages.
 *
 * Expects `$footerContext` array with:
 *  - supplier: supplier config array (requires `supplierName` and optional `links`)
 */

$footerContext = $footerContext ?? [];
$supplier = $footerContext['supplier'] ?? [];
$supplierSlug = $footerContext['supplierSlug'] ?? ($supplier['supplierSlug'] ?? null);

if (isset($supplier['supplierName']) && is_string($supplier['supplierName']) && trim($supplier['supplierName']) !== '') {
    $supplierName = trim($supplier['supplierName']);
} elseif (is_string($supplierSlug) && $supplierSlug !== '') {
    $supplierName = ucwords(str_replace(['-', '_'], ' ', $supplierSlug));
} else {
    $supplierName = 'Supplier';
}

$linksConfig = is_array($supplier['links'] ?? null) ? $supplier['links'] : [];

$footerLinks = [];

if (isset($linksConfig['faq']) && is_string($linksConfig['faq']) && trim($linksConfig['faq']) !== '') {
    $footerLinks[] = sprintf(
        '<a class="inline-flex items-center gap-1 text-[var(--sgc-brand-primary)] hover:underline" target="_blank" rel="noopener" href="%s">FAQ</a>',
        htmlspecialchars(trim($linksConfig['faq']), ENT_QUOTES, 'UTF-8')
    );
}

if (isset($linksConfig['terms']) && is_string($linksConfig['terms']) && trim($linksConfig['terms']) !== '') {
    $footerLinks[] = sprintf(
        '<a class="inline-flex items-center gap-1 text-[var(--sgc-brand-primary)] hover:underline" target="_blank" rel="noopener" href="%s">Terms &amp; Policies</a>',
        htmlspecialchars(trim($linksConfig['terms']), ENT_QUOTES, 'UTF-8')
    );
}

?>
<footer class="mt-4 flex flex-col gap-2 text-xs text-slate-500 md:flex-row md:items-center md:justify-between">
    <div class="flex flex-wrap items-center justify-center gap-2 md:justify-start">
        <span>© <?= date('Y') ?> <?= htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8') ?></span>
    </div>
    <div class="flex flex-wrap items-center md:items-end justify-center md:justify-end gap-2">
        <?= $footerLinks === [] ? '' : implode(' - ', $footerLinks) ?>
    </div>
</footer>
