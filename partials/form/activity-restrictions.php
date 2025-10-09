<?php

declare(strict_types=1);

/**
 * Render the activity restrictions information block.
 */

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activity = $bootstrap['activity'] ?? [];
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

$allowedTags = '<p><br><strong><em><ul><ol><li><a>';
$sanitizedContent = strip_tags((string) $content, $allowedTags);
$sanitizedContent = preg_replace('/javascript:/i', '', $sanitizedContent ?? '') ?? '';

if ($sanitizedContent === '') {
    return;
}
?>

<article class="space-y-0" data-info-block="restrictions">

    <header>
        <h3 class="text-lg font-semibold text-slate-900">
            <?= htmlspecialchars($restrictionTitle, ENT_QUOTES, 'UTF-8') ?>
        </h3>
    </header>

    <div class="text-sm text-slate-700">
        <?= $sanitizedContent ?>
    </div>

</article>
