<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$branding = $page['branding'] ?? [];
$primary = $branding['primaryColor'] ?? '#1C55DB';
$secondary = $branding['secondaryColor'] ?? '#0B2E8F';
$background = $branding['backgroundColor'] ?? '#F8FAFC';
$fontStack = $branding['fontFamily'] ?? "'Inter', 'Poppins', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif";
?>
<style>
    :root {
        --sgc-brand-primary: <?= htmlspecialchars($primary, ENT_QUOTES, 'UTF-8') ?>;
        --sgc-brand-secondary: <?= htmlspecialchars($secondary, ENT_QUOTES, 'UTF-8') ?>;
        --sgc-brand-background: <?= htmlspecialchars($background, ENT_QUOTES, 'UTF-8') ?>;
        --sgc-brand-font: <?= htmlspecialchars($fontStack, ENT_QUOTES, 'UTF-8') ?>;
    }

    body {
        background-color: var(--sgc-brand-background);
        font-family: var(--sgc-brand-font);
    }

    .btn-primary {
        background-color: var(--sgc-brand-primary);
        color: #fff;
    }

    .btn-primary:hover,
    .btn-primary:focus-visible {
        background-color: var(--sgc-brand-secondary);
    }
</style>
