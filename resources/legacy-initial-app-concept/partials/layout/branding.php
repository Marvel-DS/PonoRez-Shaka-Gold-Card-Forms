<?php
/**
 * Layout: Branding (Legacy Demo)
 *
 * Exposes supplier brand colours as CSS variables so they can be
 * used in conjunction with Tailwind to personalize the form to match the supplier branding colors.
 */

declare(strict_types=1);

$brandingConfig = $supplierConfig['branding'] ?? [];
$brandColor = $brandingConfig['primaryColor'] ?? '#156DB9';
$accentColor = $brandingConfig['secondaryColor'] ?? '#FFD700';

/**
 * Convert a hex colour to an "R G B" string suitable for CSS colour mixing.
 */
function legacyHexToRgbString(mixed $hex): string
{
    if (!is_string($hex) || $hex === '') {
        return '0 0 0';
    }

    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = preg_replace('/(.)/', '$1$1', $hex) ?? $hex;
    }

    if (strlen($hex) !== 6 || !ctype_xdigit($hex)) {
        return '0 0 0';
    }

    [$r, $g, $b] = sscanf($hex, "%02x%02x%02x");
    return sprintf('%d %d %d', $r, $g, $b);
}
?>

<style>
:root {
    --sgc-brand-color: <?= htmlspecialchars($brandColor, ENT_QUOTES, 'UTF-8'); ?>;
    --sgc-brand-color-rgb: <?= legacyHexToRgbString($brandColor); ?>;
    --sgc-brand-accent: <?= htmlspecialchars($accentColor, ENT_QUOTES, 'UTF-8'); ?>;
    --sgc-brand-accent-rgb: <?= legacyHexToRgbString($accentColor); ?>;
}
</style>
