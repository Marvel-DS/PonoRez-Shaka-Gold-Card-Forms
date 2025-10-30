<?php

declare(strict_types=1);

use PonoRez\SGCForms\UtilityService;

/**
 * Render activity notes block.
 */

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activity = $bootstrap['activity'] ?? [];
$supplier = $bootstrap['supplier'] ?? [];
$infoBlocks = $activity['infoBlocks'] ?? [];

$config = $infoBlocks['notes'] ?? [];
$enabled = !isset($config['enabled']) || $config['enabled'];

if (!$enabled) {
    return;
}

$notesTitle = $config['title'] ?? 'Good to know';
$content = $config['content'] ?? null;

if ($content === null) {
    return;
}

$sanitizedContent = UtilityService::formatSupplierContent((string) $content, $supplier);
if ($sanitizedContent === '') {
    return;
}
?>
<article class="space-y-2" data-info-block="notes">
    <header>
        <h3 class="text-lg font-semibold text-slate-900">
            <?= htmlspecialchars($notesTitle, ENT_QUOTES, 'UTF-8') ?>
        </h3>
    </header>

    <div class="text-sm text-slate-700">
        <?= $sanitizedContent ?>
    </div>
</article>
