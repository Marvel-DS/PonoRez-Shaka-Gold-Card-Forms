<?php

declare(strict_types=1);

use PonoRez\SGCForms\UtilityService;

/**
 * Render the activity restrictions information block.
 */

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activity = $bootstrap['activity'] ?? [];
$supplier = $bootstrap['supplier'] ?? [];
$infoBlocks = $activity['infoBlocks'] ?? [];
$details = $activity['details'] ?? [];

$config = $infoBlocks['restrictions'] ?? [];
$enabled = !isset($config['enabled']) || $config['enabled'];

if (!$enabled) {
    return;
}

$restrictionTitle = $config['title'] ?? 'Restrictions';
$content = $config['content'] ?? ($details['notes'] ?? null);

if ($content === null) {
    return;
}

$sanitizedContent = UtilityService::formatSupplierContent((string) $content, $supplier);
if ($sanitizedContent === '') {
    return;
}
?>

<article class="space-y-2" data-info-block="restrictions">

    <header>
        <h3 class="text-lg font-semibold text-slate-900">
            <?= htmlspecialchars($restrictionTitle, ENT_QUOTES, 'UTF-8') ?>
        </h3>
    </header>

    <div class="text-sm text-slate-700">
        <?= $sanitizedContent ?>
    </div>

</article>
