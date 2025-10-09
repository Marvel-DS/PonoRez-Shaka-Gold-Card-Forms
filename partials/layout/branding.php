<?php

declare(strict_types=1);

$page = $pageContext ?? [];
$branding = $page['branding'] ?? [];
$primary = $branding['primaryColor'] ?? '#1C55DB';

?>
<style>
    :root {
        --sgc-brand-primary: <?= htmlspecialchars($primary, ENT_QUOTES, 'UTF-8') ?>;
    }
</style>
