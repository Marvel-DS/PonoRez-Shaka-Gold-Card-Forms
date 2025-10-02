<?php
/**
 * Form: Shaka Gold Card Upsell
 *
 * Optional upsell checkbox for gifting a Gold Card.
 * Controlled by $formConfig['enableGoldCardUpsell'].
 */


  $price = $formConfig['goldCardPrice'] ?? 30;
  $label = $formConfig['goldCardLabel'] ?? "Gift them a Shaka Gold Card for \${$price} so they can save in the future";
?>
  <div class="my-6 flex items-start">
    <div class="flex items-center h-5">
      <input
        id="buy-goldcard"
        name="buyGoldCard"
        type="checkbox"
        class="h-4 w-4 rounded border-gray-300 text-[rgb(var(--brand-color))] focus:ring-[rgb(var(--brand-color))]"
      />
    </div>
    <div class="ml-3 text-sm">
      <label for="buy-goldcard" class="font-medium text-gray-700">
        Buying for someone else?
      </label>
      <p class="font-sans text-gray-500"><?= htmlspecialchars($label); ?></p>
    </div>
  </div>
