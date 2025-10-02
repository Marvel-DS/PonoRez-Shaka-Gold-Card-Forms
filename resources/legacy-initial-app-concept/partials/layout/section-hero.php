<?php
/**
 * Section Layout: Hero
 *
 * Displays supplier images related to a form.
 * - Loads activity-specific images if available
 * - Falls back to a placeholder if no images exist
 * - Depending on the number of added images renders a cover image or slider style gallery
 */

use PonoRez\SGCForms\UtilityService;

$imagesDir = $supplierDir . '/images/';
$activityFolder = basename(dirname($_SERVER['SCRIPT_NAME']));
$pattern = $imagesDir . $activityFolder . '-*.{jpg,jpeg,png,webp}';
$images = glob($pattern, GLOB_BRACE);
?>

<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
  <div class="rounded-xl shadow-card overflow-hidden">
    <!-- Fallback to Global Placeholder Image -->
    <?php if (empty($images)) : ?>
      <?php
        $placeholderSrc = UtilityService::getAssetsBaseUrl($_SERVER['SCRIPT_NAME']) . 'images/cover-placeholder.jpg'; 
        $activityTitle = $formConfig['activityTitle'] ?? 'Activity';
        $supplierName = $supplierConfig['supplierName'] ?? '';
      ?>
      <div class="relative">
        <img 
          src="<?= htmlspecialchars($placeholderSrc); ?>" 
          alt="Placeholder for <?= htmlspecialchars($activityTitle); ?>" 
          class="w-full aspect-[16/9] max-h-120 object-cover" 
          loading="lazy" 
          decoding="async"
        />
        <!-- Gradient Overlay -->
        <div class="absolute bottom-0 inset-x-0 h-1/3 bg-gradient-to-t from-[#01164b]/80 to-transparent z-10"></div>
        <!-- Activity Title Overlay -->
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-4 z-10">
          <div class="text-white text-xs font-heading font-medium leading-normal tracking-widest">
            <?php if (($formConfig['enableActivityIsland'] ?? false) && !empty($formConfig['activityIsland'])): ?>
              <?= htmlspecialchars($supplierName); ?>, <?= htmlspecialchars($formConfig['activityIsland']); ?>
            <?php else: ?>
              <?= htmlspecialchars($supplierName); ?>
            <?php endif; ?>
          </div>
          <h1 class="text-white text-4xl md:text-5xl font-heading font-bold leading-tight tracking-wide">
            <?= htmlspecialchars($activityTitle); ?>
          </h1>
        </div>
      </div>
    <!-- Cover Mode (Single Image) -->
    <?php elseif (count($images) === 1): ?>
      <?php
        // Single image case
        $relativePath = str_replace($supplierDir . '/', '', $images[0]);
        $src = UtilityService::resolveSupplierImagePath($relativePath, $_SERVER['SCRIPT_NAME']);
        $alt = $formConfig['activityTitle'] ?? ucfirst(pathinfo($images[0], PATHINFO_FILENAME));
        $supplierName = $supplierConfig['supplierName'] ?? '';
        $activityTitle = $formConfig['activityTitle'] ?? '';
      ?>
      <div class="relative">
        <img 
          src="<?= htmlspecialchars($src); ?>" 
          alt="<?= htmlspecialchars($alt); ?>" 
          class="w-full aspect-[16/9] max-h-120 object-cover" 
          loading="lazy" 
          decoding="async"
        />
        <!-- Gradient Overlay -->
        <div class="absolute bottom-0 inset-x-0 h-1/3 bg-gradient-to-t from-[#01164b]/80 to-transparent z-10"></div>
        <!-- Activity Title Overlay -->
        <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-4 z-10">
          <div class="text-white text-sm font-heading font-medium leading-normal tracking-widest">
            <?php if (($formConfig['enableActivityIsland'] ?? false) && !empty($formConfig['activityIsland'])): ?>
              <?= htmlspecialchars($supplierName); ?>, <?= htmlspecialchars($formConfig['activityIsland']); ?>
            <?php else: ?>
              <?= htmlspecialchars($supplierName); ?>
            <?php endif; ?>
          </div>
          <h1 class="text-white text-4xl md:text-5xl font-heading font-bold leading-tight tracking-wide">
            <?= htmlspecialchars($activityTitle); ?>
          </h1>
        </div>
      </div>
    <?php else: ?>
      <!-- Gallery Mode -->
      <div id="gallery" data-gallery class="relative">
        <h2 id="gallery-heading" class="sr-only">Activity Gallery</h2>
        <div class="w-full h-80 lg:h-120 relative overflow-hidden touch-pan-x" data-gallery-swipe>
          <ul class="w-full h-full relative" data-gallery-track>
            <?php foreach ($images as $i => $image):
              $relativePath = str_replace($supplierDir . '/', '', $image);
              $src = UtilityService::resolveSupplierImagePath($relativePath, $_SERVER['SCRIPT_NAME']);
              $alt = ($formConfig['activityTitle'] ?? 'Activity') . " – Image " . ($i + 1);
            ?>
              <li 
                id="slide-<?= $i ?>" 
                class="absolute inset-0 <?= $i === 0 ? '' : 'opacity-0'; ?> transition-opacity duration-300" 
                data-slide="<?= $i ?>" 
                aria-hidden="<?= $i === 0 ? 'false' : 'true' ?>"
              >
                <img 
                  src="<?= htmlspecialchars($src); ?>" 
                  alt="<?= htmlspecialchars($alt); ?>" 
                  class="w-full h-full object-cover" 
                  loading="lazy" 
                  decoding="async"
                />
              </li>
            <?php endforeach; ?>
          </ul>

          <!-- Gradient Overlay -->
          <div class="absolute bottom-0 inset-x-0 h-1/3 bg-gradient-to-t from-[#01164b]/80 to-transparent z-10"></div>
          
          <!-- Activity Title Overlay -->
          <div class="absolute inset-0 flex flex-col items-center justify-center text-center px-4 z-10">
            <div class="text-white text-sm font-heading font-medium leading-normal tracking-widest">
              <?php if (($formConfig['enableActivityIsland'] ?? false) && !empty($formConfig['activityIsland'])): ?>
                <?= htmlspecialchars($supplierConfig['supplierName'] ?? ''); ?>, <?= htmlspecialchars($formConfig['activityIsland']); ?>
              <?php else: ?>
                <?= htmlspecialchars($supplierConfig['supplierName'] ?? ''); ?>
              <?php endif; ?>
            </div>
            <h1 class="text-white text-4xl md:text-5xl font-heading font-bold leading-tight tracking-wide">
              <?= htmlspecialchars($formConfig['activityTitle'] ?? ''); ?>
            </h1>
          </div>

          <!-- Navigation Arrows -->
          <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 flex justify-between p-2 z-20">
            <button type="button" class="btn-gallery" data-gallery-prev aria-label="Previous slide">
              <!-- Left Arrow Icon -->
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
              </svg>
            </button>
            <button type="button" class="btn-gallery" data-gallery-next aria-label="Next slide">
              <!-- Right Arrow Icon -->
              <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
              </svg>
            </button>
          </div>

          <!-- Slide Indicators -->
          <div class="absolute bottom-3 inset-x-0 flex justify-center gap-2 z-20" role="tablist" aria-label="Gallery slides">
            <?php foreach ($images as $i => $image): ?>
              <button 
                class="w-3 h-3 rounded-full <?= $i === 0 ? 'bg-white/90' : 'bg-white/60'; ?> ring-1 ring-black/10" 
                role="tab" 
                aria-selected="<?= $i === 0 ? 'true' : 'false' ?>" 
                aria-controls="slide-<?= $i ?>" 
                data-dot="<?= $i ?>"
              ></button>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>
</div>