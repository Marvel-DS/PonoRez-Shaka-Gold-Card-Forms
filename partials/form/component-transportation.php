<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$transportation = $bootstrap['activity']['transportation'] ?? [];
$routes = $transportation['routes'] ?? [];

if ($routes === []) {
    return;
}

$label = $bootstrap['activity']['uiLabels']['transportation'] ?? 'Transportation';
$mandatory = !empty($transportation['mandatory']);
$defaultRouteId = $transportation['defaultRouteId'] ?? null;
?>
<section class="space-y-4" data-component="transportation">
    <header>
        <h2 class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></h2>
        <p class="text-sm text-slate-600">
            <?= $mandatory ? 'Transportation is required for this activity.' : 'Transportation is optional; you may drive yourself.' ?>
        </p>
    </header>

    <div class="space-y-3" data-transportation-options>
        <?php foreach ($routes as $route): ?>
            <?php
            $id = (string) ($route['id'] ?? '');
            if ($id === '') {
                continue;
            }
            $labelText = $route['label'] ?? $id;
            $description = $route['description'] ?? null;
            $price = isset($route['price']) ? (float) $route['price'] : null;
            $capacity = isset($route['capacity']) ? (int) $route['capacity'] : null;
            $isDefault = $defaultRouteId !== null && $defaultRouteId === $id;
            ?>
            <label class="flex items-start gap-3 bg-white border border-slate-200 rounded-lg p-4 cursor-pointer shadow-sm"
                   data-transportation-option="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                   <?= $price !== null ? 'data-price="' . htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8') . '"' : '' ?>
                   <?= $capacity !== null ? 'data-capacity="' . $capacity . '"' : '' ?>>
                <input type="radio"
                       name="transportationRouteId"
                       value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                       <?= $isDefault ? 'checked' : '' ?>
                       class="mt-1 h-4 w-4 border-slate-300 text-blue-600 focus:ring-blue-500">
                <div class="space-y-1">
                    <p class="font-medium text-slate-900">
                        <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($price !== null && $price > 0): ?>
                            <span class="ml-2 text-sm text-slate-500">+ $<?= number_format($price, 2) ?></span>
                        <?php elseif ($price === 0.0): ?>
                            <span class="ml-2 text-sm text-green-600">Included</span>
                        <?php endif; ?>
                    </p>
                    <?php if ($description): ?>
                        <p class="text-sm text-slate-500"><?= htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') ?></p>
                    <?php endif; ?>
                    <?php if ($capacity !== null): ?>
                        <p class="text-xs text-slate-400">Capacity: <?= $capacity ?></p>
                    <?php endif; ?>
                </div>
            </label>
        <?php endforeach; ?>
    </div>
</section>
