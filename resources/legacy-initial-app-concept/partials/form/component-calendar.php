<section aria-labelledby="calendar-heading" class="mb-5">

  <h2 id="calendar-heading" class="text-base font-medium mb-2">
    <?= htmlspecialchars($formConfig['calendarSectionLabel'] ?? 'Choose a date'); ?>
  </h2>

  <?php
    // Compute min date = today (server time)
    $minDate = (new DateTime('today'))->format('Y-m-d');
    $availabilityMap = $formConfig['availabilityMap'] ?? [];
  ?>

  <div class="w-full border border-slate-200 rounded-xl px-4 py-3 shadow-card"
       data-calendar
       data-min="<?= htmlspecialchars($minDate) ?>"
       data-supplier-id="<?= htmlspecialchars($supplierConfig['supplierId'] ?? '') ?>"
       data-activity-id="<?= htmlspecialchars($formConfig['activityIds'][0] ?? '') ?>"
       data-availability='<?= htmlspecialchars(json_encode($availabilityMap, JSON_UNESCAPED_SLASHES)) ?>'>
    <div class="flex items-center justify-between gap-2 mb-4 w-full">

      <div class="flex items-center gap-2 w-full">
        <button type="button" class="flex items-center justify-center px-3 py-2 rounded-lg hover:bg-slate-50 min-w-12 min-h-12" data-cal-prev aria-label="Previous month">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 mx-auto">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
          </svg>
        </button>
        <div aria-live="polite" aria-atomic="true" class="text-xs text-center font-medium tracking-wider uppercase w-full" data-cal-label></div>
        <button type="button" class="flex items-center justify-center px-3 py-2 rounded-lg hover:bg-slate-50 min-w-12 min-h-12" data-cal-next aria-label="Next month">
          <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" class="w-4 h-4 mx-auto">
            <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
          </svg>
        </button>
      </div>
    </div>

    <div class="grid grid-cols-7 text-xs text-center font-medium tracking-wider uppercase mb-2" aria-hidden="true">
      <div>Su</div><div>Mo</div><div>Tu</div><div>We</div><div>Th</div><div>Fr</div><div>Sa</div>
    </div>

    <!-- Day grid populated by JS -->
    <div class="grid grid-cols-7 gap-2" role="grid" aria-labelledby="calendar-heading" data-cal-grid></div>

    <!-- Hidden selected date -->
    <input type="hidden" name="selected_date" value="" data-selected-input>
  </div>

</section>