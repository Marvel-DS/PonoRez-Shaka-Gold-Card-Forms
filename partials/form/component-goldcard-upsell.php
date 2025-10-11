<?php

declare(strict_types=1);

require_once __DIR__ . '/component-goldcard-context.php';

$upsellContext = $goldCardUpsellContext ?? null;
if ($upsellContext === null) {
    $page = $pageContext ?? [];
    $fullContext = sgcFormsBuildGoldCardContext($page);
    $upsellContext = $fullContext['upsell'];
}

$isCompositeRender = isset($shakaGoldCardComposite) && $shakaGoldCardComposite === true;

$checkboxId = $upsellContext['checkboxId'] ?? 'shaka-gold-card-upsell';
$label = $upsellContext['label'] ?? 'Buying for someone else?';
$description = $upsellContext['description'] ?? 'Add a Shaka Gold Card for $30 and help them save on future adventures.';
$coverageHint = $upsellContext['coverageHint'] ?? 'Covers up to 4 guests. Additional guests coverage available for purchase for $7.50 each.';
?>
<?php if (! $isCompositeRender): ?>
<section class="space-y-6" data-component="shaka-gold-card-upsell">
<?php endif; ?>
    <div class="relative" data-goldcard-upsell-container>
        <input
            id="<?= htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') ?>"
            name="buyGoldCard"
            type="checkbox"
            class="peer sr-only"
            data-goldcard-upsell
        >
        <label for="<?= htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') ?>" class="flex cursor-pointer items-start gap-3 peer-disabled:cursor-not-allowed peer-checked:[&_span[data-role='checkbox-indicator']]:border-transparent peer-checked:[&_span[data-role='checkbox-indicator']]:bg-[var(--sgc-brand-primary)] peer-disabled:[&_span[data-role='checkbox-indicator']]:border-slate-200 peer-disabled:[&_span[data-role='checkbox-indicator']]:bg-slate-100 peer-checked:[&_svg[data-role='checkbox-check']]:opacity-100 peer-checked:[&_svg[data-role='checkbox-check']]:scale-100">
            <span data-role="checkbox-indicator" class="relative mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-md border border-slate-300 bg-white transition-all duration-300">
                <svg data-role="checkbox-check" class="h-3.5 w-3.5 text-white opacity-0 scale-75 transition-all duration-200" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true">
                    <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 0 1 0 1.414l-7.25 7.25a1 1 0 0 1-1.414 0l-3.25-3.25a1 1 0 1 1 1.414-1.414L8.5 11.586l6.543-6.543a1 1 0 0 1 1.414 0Z" clip-rule="evenodd" />
                </svg>
            </span>
            <span class="flex flex-col gap-1">
                <span class="text-base font-semibold text-slate-900">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>
                </span>
                <p class="text-xs text-slate-500 mb-0">
                    <?= htmlspecialchars($coverageHint, ENT_QUOTES, 'UTF-8') ?>
                </p>
            </span>
        </label>
    </div>
<?php if (! $isCompositeRender): ?>
</section>
<?php endif; ?>
