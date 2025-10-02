<?php
/**
 * Section Layout: Footer
 *
 * Displays site footer with supplier details.
 */

$supplierName = $supplierConfig['supplierName'] ?? 'Supplier';
?>

<footer class="mx-auto flex flex-col md:flex-row max-w-6xl items-center justify-between gap-4 p-6 md:px-8 text-xs font-sans text-center md:text-left text-slate-500" role="contentinfo">
  <p>
    &copy; <?= date('Y'); ?> <?= htmlspecialchars($supplierName); ?>. All rights reserved.
    <?php if (!empty($footerText)): ?>
      <span class="block mt-2"><?= htmlspecialchars($footerText); ?></span>
    <?php endif; ?>
  </p>
  <p>All prices are in US dollars.</p>
</footer>