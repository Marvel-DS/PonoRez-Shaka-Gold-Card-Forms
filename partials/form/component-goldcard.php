<?php

declare(strict_types=1);

require_once __DIR__ . '/component-goldcard-context.php';

$sectionContext = $goldCardNumberContext ?? null;
if ($sectionContext === null) {
    $page = $pageContext ?? [];
    $fullContext = sgcFormsBuildGoldCardContext($page);
    $sectionContext = $fullContext['number'];
}

$isCompositeRender = isset($shakaGoldCardComposite) && $shakaGoldCardComposite === true;

$inputId = $sectionContext['inputId'] ?? 'shaka-gold-card-number';
$label = $sectionContext['label'] ?? 'Shaka Gold Card Number';
$description = $sectionContext['description'] ?? 'Confirm your Shaka Gold Card number so we can apply your discount.';
$value = $sectionContext['value'] ?? '';

if (!is_string($value)) {
    $value = '';
}
?>
<?php if (! $isCompositeRender): ?>
<section class="space-y-6" data-component="shaka-gold-card-number">
<?php endif; ?>
    <div class="space-y-2">

        <header>
            <label for="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>" class="text-xl font-semibold text-slate-900">
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </label>
            <p class="text-sm text-slate-600">
                <?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </header>

        <input
            id="<?= htmlspecialchars($inputId, ENT_QUOTES, 'UTF-8') ?>"
            name="shakaGoldCardNumber"
            type="text"
            value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
            inputmode="text"
            autocomplete="off"
            class="w-full rounded-xl border border-slate-200 bg-white px-4 py-3 text-base shadow-xs"
            data-goldcard-number
        >

    </div>
<?php if (! $isCompositeRender): ?>
</section>
<?php endif; ?>
