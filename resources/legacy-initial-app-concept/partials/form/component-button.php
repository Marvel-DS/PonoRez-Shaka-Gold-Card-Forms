<?php
/**
 * Shared: Button
 *
 * Renders a Tailwind-styled button.
 * Usage:
 *   $label = "Book Now";
 *   $type = "submit"; // or "button"
 *   $variant = "primary"; // or "secondary" | "danger" | "disabled"
 */

$label   = !empty($formConfig['bookNowButtonLabel']) ? $formConfig['bookNowButtonLabel'] : 'Book Now';
$variant = $variant ?? 'primary';

$classes = match ($variant) {
    'secondary' => 'btn-secondary font-medium',
    'danger'    => 'btn bg-red-600 text-white hover:bg-red-700 font-medium',
    'disabled'  => 'btn bg-gray-300 text-gray-600 cursor-not-allowed opacity-50 font-medium',
    default     => 'btn-primary font-medium',
};
?>

<button type="button" class="<?= $classes; ?>" aria-label="<?= htmlspecialchars($label); ?>" <?= $variant === 'disabled' ? 'disabled' : ''; ?>>
  <?= htmlspecialchars($label); ?>
</button>