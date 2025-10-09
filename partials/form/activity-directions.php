<?php

declare(strict_types=1);

/**
 * Render the activity directions info block.
 */

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activity = $bootstrap['activity'] ?? [];
$infoBlocks = $activity['infoBlocks'] ?? [];
$details = $activity['details'] ?? [];

$config = $infoBlocks['directions'] ?? [];
$enabled = !isset($config['enabled']) || $config['enabled'];

if (!$enabled) {
    return;
}

$title = $config['title'] ?? 'Directions';
$content = $config['content'] ?? ($details['directions'] ?? null);

if ($content === null) {
    return;
}
?>

<article class="space-y-0" data-info-block="directions">
    
    <header>
        <h3 class="text-lg font-semibold text-slate-900">
            <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
        </h3>
    </header>

    <div class="text-sm text-slate-700">
        <?= $content ?>
    </div>

</article>
