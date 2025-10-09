<?php

declare(strict_types=1);

$partials = [
    __DIR__ . '/activity-description.php',
    __DIR__ . '/activity-notes.php',
    __DIR__ . '/activity-highlights.php',
    __DIR__ . '/activity-restrictions.php',
    __DIR__ . '/activity-directions.php',
];

$renderedBlocks = [];

foreach ($partials as $partialPath) {
    if (!is_file($partialPath)) {
        continue;
    }

    ob_start();
    include $partialPath;
    $content = trim((string) ob_get_clean());

    if ($content !== '') {
        $renderedBlocks[] = $content;
    }
}

if ($renderedBlocks === []) {
    return;
}
?>
<section class="space-y-6" data-component="activity-info">
    <?= implode("\n", $renderedBlocks) ?>
</section>
