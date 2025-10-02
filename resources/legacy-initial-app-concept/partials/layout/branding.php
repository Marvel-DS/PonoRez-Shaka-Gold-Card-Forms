<?php
/**
 * Layout: Branding
 *
 * Injects supplier-specific CSS variables for branding.
 * Reads values from $supplierConfig.
 */

$brandColor      = $supplierConfig['brandColor'] ?? '#156DB9';
$secondaryColor  = $supplierConfig['secondaryColor'] ?? '#FFD700';
$fontHeading     = $supplierConfig['fontHeading'] ?? 'Poppins';
$fontBody        = $supplierConfig['fontBody'] ?? 'Inter';

/**
 * Convert HEX to "R G B" string for alpha-capable CSS vars.
 */
function hexToRgbString($hex) {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = preg_replace('/(.)/', '$1$1', $hex);
    }
    [$r, $g, $b] = sscanf($hex, "%02x%02x%02x");
    return "$r $g $b";
}
?>

<style>
:root {
  --brand-colors: <?= hexToRgbString($brandColor); ?>;
  --brand-color: <?= htmlspecialchars($brandColor); ?>;
  --secondary-color: <?= hexToRgbString($secondaryColor); ?>;
  --font-heading: '<?= htmlspecialchars($fontHeading); ?>', sans-serif;
  --font-body: '<?= htmlspecialchars($fontBody); ?>', sans-serif;
}

</style>