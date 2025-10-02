<?php
/**
 * Section Layout: Header
 *
 * Displays supplier logo and optional link back to all discounted activities.
 */

use PonoRez\SGCForms\UtilityService;

$supplierName         = $supplierConfig['supplierName'] ?? 'Supplier';
$showSupplierHomeLink = $supplierConfig['showSupplierHomeLink'] ?? false;
$supplierHomeLinkText = $supplierConfig['supplierHomeLinkText'] ?? 'See All Discounted Activities';
$supplierHomeLinkUrl  = $supplierConfig['supplierHomeLinkUrl'] ?? '#';

// Resolve logo path safely
$logoSrc = UtilityService::resolveSupplierImagePath($supplierConfig['brandLogo'], $_SERVER['SCRIPT_NAME']);
$logoAlt = $supplierName . ' official logo';
?>

<header role="banner">
  <nav aria-label="Global Navigation" class="mx-auto flex flex-col md:flex-row max-w-6xl items-center justify-between gap-4 p-6 md:px-8">
    
    <!-- Supplier Logo -->
    <div class="flex justify-center lg:justify-start lg:flex-1">
      <a href="<?= htmlspecialchars($supplierHomeLinkUrl); ?>" aria-label="Go to <?= htmlspecialchars($supplierName); ?> homepage" class="-m-1.5 p-1.5">
        <img 
          src="<?= htmlspecialchars($logoSrc); ?>" 
          alt="<?= htmlspecialchars($logoAlt); ?>" 
          class="h-12 w-auto dark:hidden"
          loading="eager" 
          decoding="async"
        />
      </a>
    </div>

    <!-- Supplier Home Link (optional) -->
    <?php if ($showSupplierHomeLink): ?>
      <div class="flex flex-1 justify-center lg:justify-end">
        <a 
          href="<?= htmlspecialchars($supplierHomeLinkUrl); ?>" 
          class="px-4 py-3 bg-slate-100 text-[color:var(--brand-color)] uppercase rounded-md text-center text-xs font-heading font-medium tracking-wider hover:bg-[color:var(--brand-color)] hover:text-white transition-all duration-300"
        >
          <?= htmlspecialchars($supplierHomeLinkText); ?>
        </a>
      </div>
    <?php endif; ?>
    
  </nav>
</header>