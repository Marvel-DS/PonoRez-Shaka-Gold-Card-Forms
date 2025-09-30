<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$infoBlocks = $bootstrap['activity']['infoBlocks'] ?? [];

$sections = [
    'description' => [
        'icon' => '📝',
        'defaultTitle' => 'About this tour',
    ],
    'notes' => [
        'icon' => 'ℹ️',
        'defaultTitle' => 'Good to know',
    ],
    'highlights' => [
        'icon' => '⭐',
        'defaultTitle' => 'Highlights',
        'renderList' => true,
    ],
    'restrictions' => [
        'icon' => '⚠️',
        'defaultTitle' => 'Restrictions',
    ],
    'directions' => [
        'icon' => '📍',
        'defaultTitle' => 'Directions',
    ],
];
?>
<section class="space-y-6" data-component="activity-info">
    <?php foreach ($sections as $key => $meta): ?>
        <?php
        $config = $infoBlocks[$key] ?? [];
        $enabled = !isset($config['enabled']) || $config['enabled'];
        if (!$enabled) {
            continue;
        }
        $title = $config['title'] ?? $meta['defaultTitle'];
        $content = $config['content'] ?? null;
        $items = $config['items'] ?? [];

        if ($content === null && empty($items)) {
            continue;
        }
        ?>
        <article class="bg-white rounded-xl shadow-sm border border-slate-200 p-6 space-y-3"
                 data-info-block="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
            <header class="flex items-center gap-2">
                <span aria-hidden="true" class="text-lg"><?= $meta['icon'] ?></span>
                <h3 class="text-lg font-semibold text-slate-900">
                    <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                </h3>
            </header>

            <?php if ($content !== null): ?>
                <div class="prose prose-slate max-w-none text-sm text-slate-700">
                    <?= $content ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($items)): ?>
                <ul class="list-disc list-inside text-sm text-slate-700 space-y-1">
                    <?php foreach ($items as $item): ?>
                        <li><?= htmlspecialchars((string) $item, ENT_QUOTES, 'UTF-8') ?></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </article>
    <?php endforeach; ?>
</section>
