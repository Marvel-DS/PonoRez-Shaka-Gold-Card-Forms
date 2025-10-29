<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$bootstrap = $page['bootstrap'] ?? [];
$activityConfig = $page['activity'] ?? [];
$supplier = $bootstrap['supplier'] ?? [];

$bootstrapPolicy = $bootstrap['activity']['cancellationPolicy'] ?? null;
$configPolicy = $activityConfig['cancellationPolicy'] ?? null;

$policy = [];
foreach ([$bootstrapPolicy, $configPolicy] as $source) {
    if (is_array($source)) {
        $policy = array_merge($policy, $source);
    }
}

$supplierPolicyUrl = $supplier['cancellationPolicy'] ?? null;

$enabledValue = $policy['enabled'] ?? null;
if ($enabledValue !== null) {
    $enabled = filter_var($enabledValue, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE);
    if ($enabled === false) {
        return;
    }
}

$normalizeString = static function (mixed $value): ?string {
    if ($value === null) {
        return null;
    }

    if (is_string($value)) {
        $trimmed = trim($value);
        return $trimmed === '' ? null : $trimmed;
    }

    if (is_array($value)) {
        $parts = [];
        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $trimmed = trim($item);
            if ($trimmed !== '') {
                $parts[] = $trimmed;
            }
        }

        if ($parts !== []) {
            return implode(PHP_EOL . PHP_EOL, $parts);
        }
    }

    return null;
};

$firstNonEmptyString = static function (array $candidates) use ($normalizeString): ?string {
    foreach ($candidates as $candidate) {
        $normalized = $normalizeString($candidate);
        if ($normalized !== null) {
            return $normalized;
        }
    }

    return null;
};

$title = $firstNonEmptyString([
    $policy['title'] ?? null,
    $policy['heading'] ?? null,
    'Cancellation Policy',
]) ?? 'Cancellation Policy';

$content = $firstNonEmptyString([
    $policy['content'] ?? null,
    $policy['text'] ?? null,
    $policy['description'] ?? null,
]);

$items = [];
foreach (['items', 'points', 'bullets'] as $key) {
    $rawItems = $policy[$key] ?? null;
    if (!is_array($rawItems) || $rawItems === []) {
        continue;
    }

    $items = array_values(array_filter(array_map($normalizeString, $rawItems)));
    if ($items !== []) {
        break;
    }
}

$linkUrl = $firstNonEmptyString([
    $policy['url'] ?? null,
    $policy['link'] ?? null,
    $policy['href'] ?? null,
    $supplierPolicyUrl,
]);

$linkLabel = $firstNonEmptyString([
    $policy['linkLabel'] ?? null,
    $policy['linkText'] ?? null,
    'View full cancellation policy',
]) ?? 'View full cancellation policy';

if ($content === null && $items === [] && $linkUrl === null) {
    return;
}

$contentHtml = $content !== null
    ? nl2br(htmlspecialchars($content, ENT_QUOTES, 'UTF-8'))
    : null;

$checkboxId = 'acknowledge-cancellation-policy';
$acknowledgementLabel = 'I have read and agree to the cancellation policy.';
?>
<section class="space-y-4" data-component="cancellation-policy">
    <header>
        <h2 class="text-lg font-semibold text-slate-900">
            <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p class="text-sm text-slate-600">
            Please review the cancellation terms before completing your booking.
        </p>
    </header>

    <div class="space-y-4" data-cancellation-container>
        <?php if ($contentHtml !== null): ?>
            <p class="text-sm text-slate-600"><?= $contentHtml ?></p>
        <?php endif; ?>

        <?php if ($items !== []): ?>
            <ul class="list-disc space-y-2 pl-5 text-sm text-slate-600">
                <?php foreach ($items as $item): ?>
                    <li><?= htmlspecialchars($item, ENT_QUOTES, 'UTF-8') ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($linkUrl !== null): ?>
            <a
                href="<?= htmlspecialchars($linkUrl, ENT_QUOTES, 'UTF-8') ?>"
                class="inline-flex items-center gap-2 text-sm font-semibold text-[var(--sgc-brand-primary)] hover:underline"
                target="_blank"
                rel="noopener"
                data-cancellation-policy-link
            >
                <?= htmlspecialchars($linkLabel, ENT_QUOTES, 'UTF-8') ?>
            </a>
        <?php endif; ?>

        <p class="hidden rounded-lg border border-rose-200 bg-rose-50 px-4 py-2 text-sm text-rose-700"
            data-cancellation-error
            role="alert"></p>

        <label for="<?= htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') ?>" class="flex items-start gap-3 rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700">
            <input
                id="<?= htmlspecialchars($checkboxId, ENT_QUOTES, 'UTF-8') ?>"
                name="acknowledgeCancellationPolicy"
                type="checkbox"
                class="mt-1 h-5 w-5 shrink-0 rounded border-slate-300 text-[var(--sgc-brand-primary)] focus:ring-[var(--sgc-brand-primary)]"
                data-cancellation-acknowledgement
            >
            <span>
                <?= htmlspecialchars($acknowledgementLabel, ENT_QUOTES, 'UTF-8') ?>
            </span>
        </label>
    </div>
</section>
