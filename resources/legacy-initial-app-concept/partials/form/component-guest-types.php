<?php
/**
 * Form Component: Guest Types
 *
 * Renders guest type selection inputs based on the form configuration.
 *
 * If there is only one guest type and its max count is 1 it will hide the entire section visually.
 * If there are multiple guest types and one of them has a max count of 1 it will hide only that field.
 * Otherwise guest types controls will be rendered as dropdowns with optional descriptions and pricing.
 */

// Extract configuration safely
$names        = $formConfig['guestTypeName'] ?? [];
$descriptions  = $formConfig['guestTypeDescription'] ?? [];
$sectionLabel = $formConfig['guestTypesSectionLabel'] ?? 'Select Guests';

// Determine hiding logic
$singleGuestTypeId       = $formConfig['guestTypeIds'][0] ?? null;
$maxGuestCountForSingle  = $singleGuestTypeId !== null ? (int)($formConfig['maxGuestCount'][$singleGuestTypeId] ?? 10) : 10;
$hideSection = count($formConfig['guestTypeIds']) === 1 && $maxGuestCountForSingle === 1;
?>

<?php
  // Prepare values for API guest types URL (robust slug extraction)
  // We derive supplier/activity slugs from the *executing* script path (e.g., suppliers/{supplier}/{activity}/index.php),
  // not from this partial's directory. This prevents incorrect values like "public/SCG".
  $scriptPath = $_SERVER['SCRIPT_FILENAME'] ?? __FILE__;
  $normalized = str_replace('\\', '/', $scriptPath);

  // Allow config-provided slugs as fallbacks/overrides
  $supplierSlug = $formConfig['supplierSlug'] ?? '';
  $activitySlug = $formConfig['activitySlug'] ?? '';

  // Try to match "/suppliers/{supplier}/{activity}/" in the request path
  if (preg_match('~/suppliers/([^/]+)/([^/]+)/~', $normalized, $m)) {
      $supplierSlug = $m[1];
      $activitySlug = $m[2];
  } else {
      // Fallback to deriving from the script directory if pattern not found
      $scriptDir    = dirname($scriptPath);
      $activitySlug = $activitySlug ?: basename($scriptDir);
      $supplierSlug = $supplierSlug ?: basename(dirname($scriptDir));
  }

  $activityId   = $formConfig['activityIds'][0] ?? '';
  $today        = date('Y-m-d');

  // Dynamically determine app root (fully qualified base URL)
  $appRoot = \PonoRez\SGCForms\UtilityService::getAppRoot();

  // Build endpoint URL
  $apiGuestTypesPath = '/api/get-guest-types.php';
  $apiGuestTypesUrl = rtrim($appRoot, '/') . $apiGuestTypesPath .
      '?supplier='   . rawurlencode($supplierSlug) .
      '&activity='   . rawurlencode($activitySlug) .
      '&activityId=' . rawurlencode((string)$activityId) .
      '&date='       . rawurlencode($today);
?>
<section id="formSectionGuestTypes"
         data-guest-types
         data-supplier="<?= htmlspecialchars($supplierSlug, ENT_QUOTES) ?>"
         data-activity-id="<?= htmlspecialchars($activityId, ENT_QUOTES) ?>"
         data-api-guest-types="<?= htmlspecialchars($apiGuestTypesUrl, ENT_QUOTES) ?>"
         class="space-y-3 mb-5<?= $hideSection ? ' hidden' : '' ?>"
         <?= $hideSection ? 'aria-hidden="true"' : '' ?>>

  <?php if (!$hideSection): ?>
    <h2 class="text-base font-medium mb-2"><?= htmlspecialchars($sectionLabel); ?></h2>
  <?php endif; ?>

  <?php foreach ($formConfig['guestTypeIds'] as $guestTypeId): ?>
    <?php
      // Name fallback logic: prefer config, fallback to Ponorez-provided name, then placeholder
      $configName  = trim($names[$guestTypeId] ?? '');
      $ponorezName = $formConfig['ponorezGuestTypeName'][$guestTypeId] ?? '';
      $name        = $configName !== '' ? $configName : $ponorezName;

      // Description fallback logic: prefer config, fallback to Ponorez-provided description, then placeholder
      $configDesc  = trim($descriptions[$guestTypeId] ?? '');
      $ponorezDesc = $formConfig['ponorezGuestTypeDescriptions'][$guestTypeId] ?? '';
      $desc        = $configDesc !== '' ? $configDesc : $ponorezDesc;

      $maxForType = (int)($formConfig['maxGuestCount'][$guestTypeId] ?? 10);

      $needsNameFallback = $name === '';
      $needsDescFallback = $desc === '';
      $labelText = $needsNameFallback ? '--' : $name;
      $descText  = $needsDescFallback ? '--' : $desc;
      $fallbackAttr = htmlspecialchars((string)$guestTypeId, ENT_QUOTES);
    ?>

    <?php if ($maxForType === 1): ?>
      <!-- Guest type with max 1 → auto-selected hidden checkbox - used for private activities - to be developed -->
      <div class="hidden">
        <input type="checkbox"
               id="guest-type-<?= htmlspecialchars($guestTypeId, ENT_QUOTES) ?>-name"
               data-guest-type-id="<?= htmlspecialchars($guestTypeId, ENT_QUOTES) ?>"
               value="1"
               checked
               class="h-5 w-5 accent-[color:var(--brand-color)]"
               aria-label="<?= htmlspecialchars($labelText) ?>"
               />
        <label for="guest-type-<?= htmlspecialchars($guestTypeId, ENT_QUOTES) ?>-name"
               <?php if ($needsNameFallback): ?>data-fallback-name="<?= $fallbackAttr ?>"<?php endif; ?>
               class="sr-only">
          <?= htmlspecialchars($labelText); ?>
        </label>
      </div>

    <?php else: ?>
      <!-- Multi-selection allowed → dropdown UI -->
      <div class="flex w-full overflow-hidden rounded-xl">
        <!-- Dropdown -->
        <div class="relative bg-[color:var(--brand-color)] text-white flex items-center px-4 py-2 w-20 rounded-l-xl">
          <label for="guest-<?= htmlspecialchars($guestTypeId, ENT_QUOTES) ?>" class="sr-only"
                 <?php if ($needsNameFallback): ?>data-fallback-name="<?= $fallbackAttr ?>"<?php endif; ?>>
            <?= htmlspecialchars($labelText); ?>
          </label>
          <select id="guest-<?= htmlspecialchars($guestTypeId, ENT_QUOTES) ?>"
                  data-guest-type-id="<?= htmlspecialchars($guestTypeId, ENT_QUOTES) ?>"
                  class="appearance-none bg-transparent text-white font-semibold text-base w-full pr-4 focus:outline-none"
                  aria-describedby="guest-type-<?= htmlspecialchars($guestTypeId, ENT_QUOTES) ?>-desc">
            <?php for ($i = 0; $i <= $maxForType; $i++): ?>
              <option value="<?= $i ?>"><?= $i ?></option>
            <?php endfor; ?>
          </select>
          <!-- Dropdown arrow icon -->
          <svg xmlns="http://www.w3.org/2000/svg"
               fill="none"
               viewBox="0 0 24 24"
               stroke-width="2"
               stroke="currentColor"
               class="w-5 h-5 absolute right-2 pointer-events-none"
               aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M8.25 15 12 18.75 15.75 15m-7.5-6L12 5.25 15.75 9" />
          </svg>
        </div>
        <!-- Name & Description -->
        <div class="flex-1 bg-white border-t border-b border-slate-200 px-3 py-2.5">
          
          <p id="guest-type-<?= htmlspecialchars($guestTypeId, ENT_QUOTES) ?>-name"
             <?php if ($needsNameFallback): ?>data-fallback-name="<?= $fallbackAttr ?>"<?php endif; ?>
             class="text-base font-medium text-body"><?= htmlspecialchars($labelText); ?></p>
          <p id="guest-type-<?= htmlspecialchars($guestTypeId, ENT_QUOTES) ?>-desc"
             <?php if ($needsDescFallback): ?>data-fallback-desc="<?= $fallbackAttr ?>"<?php endif; ?>
             class="text-xs text-slate-500"><?= htmlspecialchars($descText); ?></p>

        </div>
        <!-- Price placeholder -->
        <div class="w-20 border border-slate-200 flex items-center justify-end px-3 py-2.5 rounded-r-xl">
          <p class="text-sm font-semibold text-body" data-guest-price="<?= htmlspecialchars($guestTypeId, ENT_QUOTES) ?>">0.00</p>
        </div>
      </div>
    <?php endif; ?>
  <?php endforeach; ?>

  <!-- Hidden input to track total guest counts -->
  <input type="hidden" name="guestCounts" data-guest-counts>
</section>
