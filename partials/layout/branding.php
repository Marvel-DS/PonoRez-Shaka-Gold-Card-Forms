<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$branding = $page['branding'] ?? [];
$primary = $branding['primaryColor'] ?? '#1C55DB';

?>
<style>
    :root {
        --sgc-brand-primary: <?= htmlspecialchars($primary, ENT_QUOTES, 'UTF-8') ?>;
        --sgc-brand-primary-dark: color-mix(in srgb, var(--sgc-brand-primary) 90%, black);
        --sgc-brand-primary-light: color-mix(in srgb, var(--sgc-brand-primary) 90%, white);
    }
</style>
