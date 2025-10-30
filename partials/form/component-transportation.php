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

    <div class="space-y-4" data-transportation-options>
        <?php foreach ($routes as $route): ?>
            <?php
            $id = (string) ($route['id'] ?? '');

            $labelText = $route['label'] ?? $id;
            $description = $route['description'] ?? null;
            $price = isset($route['price']) ? (float) $route['price'] : null;
            $capacity = isset($route['capacity']) ? (int) $route['capacity'] : null;
            $isDefault = $defaultRouteId !== null && $defaultRouteId === $id;

            $cardClasses = 'flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white px-5 pb-5 pt-5 shadow-xs transition-all duration-300 hover:border-[var(--sgc-brand-primary)]/25 hover:shadow-xl';
            if ($isDefault) {
                $cardClasses .= ' border-[#1C55DB] bg-[#1C55DB]/10 shadow-lg';
            }

            $radioVisualClasses = 'relative flex h-6 w-6 shrink-0 items-center justify-center rounded-full border transition-all duration-300';
            $radioVisualClasses .= $isDefault
                ? ' border-transparent bg-[var(--sgc-brand-primary)]'
                : ' border-slate-300 bg-white';

            $checkIcon = $isDefault
                ? '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor" class="h-full w-full"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>'
                : '';

            $dataAttributes = [
                'data-transportation-option="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"',
            ];
            if ($price !== null) {
                $dataAttributes[] = 'data-price="' . htmlspecialchars((string) $price, ENT_QUOTES, 'UTF-8') . '"';
            }
            if ($capacity !== null) {
                $dataAttributes[] = 'data-capacity="' . htmlspecialchars((string) $capacity, ENT_QUOTES, 'UTF-8') . '"';
            }
            ?>
            <div class="w-full" <?= implode(' ', $dataAttributes) ?>>
                <label class="block w-full cursor-pointer focus-visible:outline-none">
                    <input type="radio"
                           name="transportationRouteId"
                           value="<?= htmlspecialchars($id, ENT_QUOTES, 'UTF-8') ?>"
                           <?= $isDefault ? 'checked' : '' ?>
                           aria-label="Select transportation option <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>"
                           class="sr-only">
                    <div class="<?= $cardClasses ?>" data-transportation-card>
                        <div class="flex items-start gap-4">
                            <span class="<?= $radioVisualClasses ?>" data-transportation-radio>
                                <span class="flex h-4 w-4 items-center justify-center transition-all duration-300 text-white"
                                      aria-hidden="true"
                                      data-transportation-icon><?= $checkIcon ?></span>
                            </span>
                            <div class="flex flex-col gap-1">
                                <span class="text-lg font-semibold tracking-tight text-slate-900">
                                    <?= htmlspecialchars($labelText, ENT_QUOTES, 'UTF-8') ?>
                                    <?php if ($price !== null && $price > 0): ?>
                                        <span class="ml-2 text-sm font-medium text-slate-500">+ $<?= htmlspecialchars(number_format($price, 2), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php elseif ($price === 0.0): ?>
                                        <span class="ml-2 text-sm font-semibold text-green-600">Included</span>
                                    <?php endif; ?>
                                </span>
                                <?php if ($description): ?>
                                    <span class="text-sm text-slate-500"><?= htmlspecialchars((string) $description, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                                <?php if ($capacity !== null): ?>
                                    <span class="text-xs text-slate-400">Capacity: <?= htmlspecialchars((string) $capacity, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </label>
            </div>
        <?php endforeach; ?>
    </div>
</section>
