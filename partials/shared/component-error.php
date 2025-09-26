<?php
/**
 * Shared: Message
 *
 * Displays a styled message block.
 * Requires $message variable to be defined before include.
 * Optional:
 * - $title (string) defaults to "Error"
 * - $variant (string) one of "error" | "warning" | "info" | "success"
 */

$title   = $title   ?? 'Error';
$variant = $variant ?? 'error';

$classes = match ($variant) {
    'warning' => 'bg-yellow-50 border-yellow-200 text-yellow-800',
    'info'    => 'bg-blue-50 border-blue-200 text-blue-800',
    'success' => 'bg-green-50 border-green-200 text-green-800',
    default   => 'bg-red-50 border-red-200 text-red-700',
};
?>

<div class="my-4 p-3 rounded border <?= $classes; ?>" role="alert">
  <p class="font-medium"><?= htmlspecialchars($title); ?></p>
  <p class="font-sans"><?= htmlspecialchars($message ?? 'An unknown error occurred.'); ?></p>
</div>