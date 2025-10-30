<?php

declare(strict_types=1);

use PonoRez\SGCForms\UtilityService;

/**
 * Render the activity highlights list.
 */

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activity = $bootstrap['activity'] ?? [];
$supplier = $bootstrap['supplier'] ?? [];
$infoBlocks = $activity['infoBlocks'] ?? [];

$config = $infoBlocks['highlights'] ?? [];
$enabled = !isset($config['enabled']) || $config['enabled'];

if (!$enabled) {
    return;
}

$highlightsTitle = $config['title'] ?? 'Highlights';

$items = [];
if (isset($config['items']) && is_array($config['items'])) {
    foreach ($config['items'] as $item) {
        $formatted = UtilityService::formatSupplierContent((string) $item, $supplier);
        $formatted = str_replace(['<p>', '</p>'], '', $formatted);
        if ($formatted !== '') {
            $items[] = $formatted;
        }
    }
}

if ($items === []) {
    return;
}
?>
<article class="space-y-2" data-info-block="highlights">
    
    <header>
        <h3 class="text-lg font-semibold text-slate-900">
            <?= htmlspecialchars($highlightsTitle, ENT_QUOTES, 'UTF-8') ?>
        </h3>
    </header>

    <ul class="list-disc list-inside text-sm text-slate-700 space-y-1">
        <?php foreach ($items as $item): ?>
            <li><?= $item ?></li>
        <?php endforeach; ?>
    </ul>

</article>
