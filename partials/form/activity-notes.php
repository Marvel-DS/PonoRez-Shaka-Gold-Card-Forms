<?php

declare(strict_types=1);

/**
 * Render activity notes block.
 */

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activity = $bootstrap['activity'] ?? [];
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

$allowedTags = '<p><br><strong><em><ul><ol><li><a>';
$sanitizedContent = strip_tags((string) $content, $allowedTags);
$sanitizedContent = preg_replace('/javascript:/i', '', $sanitizedContent ?? '') ?? '';

if ($sanitizedContent === '') {
    return;
}
?>
<article class="space-y-0" data-info-block="notes">
    <header>
        <h3 class="text-lg font-semibold text-slate-900">
            <?= htmlspecialchars($notesTitle, ENT_QUOTES, 'UTF-8') ?>
        </h3>
    </header>

    <div class="text-sm text-slate-700">
        <?= $sanitizedContent ?>
    </div>
</article>
