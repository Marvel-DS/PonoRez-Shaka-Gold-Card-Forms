<?php
/**
 * Form: Timeslot
 *
 * Dropdown to select available time slots.
 * Label can be customized via $formConfig['timeslotLabel'].
 */
?>

<div class="my-4 hidden">
  <label for="timeslot" class="block text-sm font-medium text-gray-700">
    <?= htmlspecialchars($formConfig['timeslotLabel'] ?? 'Select Time'); ?>
  </label>
  <select id="timeslot"
          data-role="timeslot-select"
          class="activitySelect border rounded px-3 py-2 mt-1 w-60">
    <option value="">-- Select --</option>
    <!-- Options will be injected dynamically via JS -->
  </select>
</div>

<section>
          <h2 class="text-base font-medium mb-2">Select a Time</h2>
          <div class="space-y-4">
            <!-- Timeslot 1 -->
            <label class=" border rounded-lg px-4 py-3 cursor-pointer hidden">
              <div class="flex items-center gap-3">
                <input type="radio" name="timeslot" checked class="accent-brand" />
                <div>
                  <p class="text-sm font-medium">7:00am</p>
                  <p class="text-xs text-red-600">Only 5 seats left!</p>
                </div>
              </div>
              <div class="mt-2 text-xs text-slate-600">
                <p>2 x Adults: $371.88</p>
                <p>1 x Youth: $172.98</p>
                <p>1 x Children: $160.00</p>
              </div>
              <div class="mt-2 flex justify-between items-center">
                <p class="text-sm font-medium">Total: $704.86 <span class="text-xs text-slate-500">Including taxes and fees</span></p>
              </div>
              <p class="text-xs text-brand mt-1">You save $129.73 with your Shaka Gold Card</p>
            </label>

            <!-- Timeslot 2 -->
            <label class="block border border-slate-200 rounded-xl p-4 cursor-pointer shadow-card">
              <div class="flex items-center gap-3 mb-3">
                <input type="radio" name="timeslot" class="accent-brand" />
                <div>
                  <p class="text-sm font-medium">8:00am</p>
                </div>
              </div>
              <div data-layer="price info" class="w-full self-stretch pt-4 border-t border-[#090b0a]/20 inline-flex justify-between items-center overflow-hidden">
                <div class="text-[#090b0a] text-xs font-medium leading-5 tracking-tight">
                  <p>0 x Adults: $0.0</p>
                  <p>0 x Youth: $0.00</p>
                  <p>0 x Children: $0.00</p>
                </div>
                <div class="Total inline-flex flex-col justify-start items-end">
                  <div class="text-[#090b0a] text-base font-semibold leading-normal tracking-tight">Total: $0.00</div>
                  <div class="text-[#090b0a]/50 text-xs font-medium leading-snug tracking-tight">Including taxes and fees</div>
                  <div class="text-[#1c55db] text-xs lg:text-sm font-semibold leading-[21px] tracking-tight">You save $0.00 with your Shaka Gold Card</div>
                </div>
              </div>
            </label>
          </div>
        </section>