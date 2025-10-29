<?php
/**
 * Section Layout: Hero
 *
 * Displays the supplier activity hero carousel sourced from the resolved
 * gallery items. Falls back to a global placeholder when a supplier has not
 * configured any images.
 */

declare(strict_types=1);

$galleryItems = $galleryItems ?? [];
$title = $activityTitle ?? '';
$supplierName = $supplierDisplayName ?? '';

if ($galleryItems === []) {
    $galleryItems = [[
        'src' => '/assets/images/activity-cover-placeholder.jpg',
        'alt' => $title !== '' ? $title : 'Activity cover image',
    ]];
}

$multipleSlides = count($galleryItems) > 1;
?>
<section class="relative" data-component="hero-gallery">
    <div class="relative overflow-hidden rounded-2xl shadow-xl" data-gallery-root>
        <div class="relative h-[280px] w-full sm:h-[360px] lg:h-[420px] overflow-hidden">
            <?php foreach ($galleryItems as $index => $item): ?>
                <figure
                    class="absolute inset-0 transition-opacity duration-500 <?= $index === 0 ? 'opacity-100' : 'opacity-0' ?>"
                    data-gallery-slide="<?= $index ?>"
                    aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>"
                >
                    <img src="<?= htmlspecialchars($item['src'], ENT_QUOTES, 'UTF-8') ?>"
                            alt="<?= htmlspecialchars($item['alt'], ENT_QUOTES, 'UTF-8') ?>"
                            class="h-full w-full object-cover"
                            loading="lazy"
                            decoding="async">
                </figure>
            <?php endforeach; ?>

            <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/60 via-black/20 to-transparent"></div>

            <div class="absolute inset-x-0 bottom-12 md:bottom-16 flex flex-col items-center gap-2 md:gap-4 px-6 text-center text-white">

                <?php if ($supplierName !== ''): ?>
                    <p class="text-xs font-semibold uppercase leading-none tracking-widest mb-0 text-white/70">
                        <?= htmlspecialchars($supplierName, ENT_QUOTES, 'UTF-8') ?>
                    </p>
                <?php endif; ?>

                <?php if ($title !== ''): ?>
                    <h1 class="text-2xl md:text-4xl font-extrabold leading-snug md:leading-none">
                        <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>
                    </h1>
                <?php endif; ?>

            </div>

            <?php if ($multipleSlides): ?>
                
                <div class="absolute inset-x-0 top-1/2 -translate-y-1/2 px-4">
                    <div class="flex justify-between">
                        <button type="button"
                                class="inline-flex h-12 w-12 pr-1 items-center justify-center rounded-md text-white bg-white/20 hover:bg-[var(--sgc-brand-primary)] transition-all duration-500 cursor-pointer"
                                data-gallery-prev
                                aria-label="Previous slide">
                            <?= \PonoRez\SGCForms\UtilityService::renderSvgIcon('chevron-left.svg', 'h-8 w-8', '2') ?>
                        </button>
                        <button type="button"
                                class="inline-flex h-12 w-12 pl-1 items-center justify-center rounded-md text-white bg-white/20 hover:bg-[var(--sgc-brand-primary)] transition-all duration-500 cursor-pointer"
                                data-gallery-next
                                aria-label="Next slide">
                            <?= \PonoRez\SGCForms\UtilityService::renderSvgIcon('chevron-right.svg', 'h-8 w-8', '2') ?>
                        </button>
                    </div>
                </div>

                <div class="absolute inset-x-0 bottom-6 flex justify-center gap-2">
                    <?php foreach ($galleryItems as $index => $item): ?>
                        <button type="button"
                                class="h-2 w-2 rounded-full transition-all duration-300 <?= $index === 0 ? 'bg-[var(--sgc-brand-primary)] w-4' : 'bg-white/50 w-2' ?>"
                                data-gallery-dot="<?= $index ?>"
                                aria-label="Go to slide <?= $index + 1 ?>"
                                aria-current="<?= $index === 0 ? 'true' : 'false' ?>">
                        </button>
                    <?php endforeach; ?>
                </div>

            <?php endif; ?>

        </div>
    </div>
</section>
