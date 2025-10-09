<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activity = $bootstrap['activity'] ?? [];
$infoBlocks = is_array($activity['infoBlocks'] ?? null) ? $activity['infoBlocks'] : [];
$details = is_array($activity['details'] ?? null) ? $activity['details'] : [];

$sections = [];

$showInfoColumn = isset($activity['showInfoColumn'])
    ? (bool) $activity['showInfoColumn']
    : ((bool) ($infoBlocks['showInfoColumn'] ?? true));

if (!$showInfoColumn) {
    return;
}

$descriptionConfig = $infoBlocks['description'] ?? [];
$descriptionEnabled = !isset($descriptionConfig['enabled']) || $descriptionConfig['enabled'];
$descriptionContent = $descriptionConfig['content'] ?? ($details['description'] ?? ($activity['summary'] ?? null));
if ($descriptionEnabled && $descriptionContent) {
    $sections[] = [
        'key' => 'description',
        'title' => $descriptionConfig['title'] ?? 'Activity Details',
        'body' => $descriptionContent,
    ];
}

$notesConfig = $infoBlocks['notes'] ?? [];
$notesEnabled = !isset($notesConfig['enabled']) || $notesConfig['enabled'];
$notesContent = $notesConfig['content'] ?? null;
if ($notesEnabled && $notesContent) {
    $sections[] = [
        'key' => 'notes',
        'title' => $notesConfig['title'] ?? 'Additional Information',
        'body' => $notesContent,
    ];
}

$highlightsConfig = $infoBlocks['highlights'] ?? [];
$highlightsEnabled = !isset($highlightsConfig['enabled']) || $highlightsConfig['enabled'];
$highlightsItems = $highlightsConfig['items'] ?? [];
if ($highlightsEnabled && is_array($highlightsItems) && $highlightsItems !== []) {
    $sections[] = [
        'key' => 'highlights',
        'title' => $highlightsConfig['title'] ?? 'What to bring',
        'items' => $highlightsItems,
    ];
}

$restrictionsConfig = $infoBlocks['restrictions'] ?? [];
$restrictionsEnabled = !isset($restrictionsConfig['enabled']) || $restrictionsConfig['enabled'];
$restrictionsContent = $restrictionsConfig['content'] ?? ($details['notes'] ?? null);
if ($restrictionsEnabled && $restrictionsContent) {
    $sections[] = [
        'key' => 'restrictions',
        'title' => $restrictionsConfig['title'] ?? 'Restrictions',
        'body' => $restrictionsContent,
    ];
}

$directionsConfig = $infoBlocks['directions'] ?? [];
$directionsEnabled = !isset($directionsConfig['enabled']) || $directionsConfig['enabled'];
$directionsContent = $directionsConfig['content'] ?? ($details['directions'] ?? null);
if ($directionsEnabled && $directionsContent) {
    $sections[] = [
        'key' => 'directions',
        'title' => $directionsConfig['title'] ?? 'Directions',
        'body' => $directionsContent,
    ];
}

if ($sections === []) {
    return;
}
?>
<section class="space-y-10" data-component="activity-info-column">
    <?php foreach ($sections as $section): ?>
        <article class="space-y-4">
            <header>
                <h2 class="text-lg font-semibold text-slate-900">
                    <?= htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8') ?>
                </h2>
            </header>

            <?php if (!empty($section['body'])): ?>
                <div class="prose prose-slate max-w-none text-sm leading-relaxed">
                    <?= $section['body'] ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($section['items']) && is_array($section['items'])): ?>
                <ul class="list-disc space-y-2 pl-5 text-sm text-slate-700">
                    <?php foreach ($section['items'] as $item): ?>
                        <li><?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
