<?php
require_once __DIR__ . '/../../../vendor/autoload.php';

use PonoRez\SGCForms\UtilityService;

// Paths
$supplierDir = __DIR__ . '/..';
$formDir     = __DIR__;

// Load configs
$supplierConfig = UtilityService::loadConfig($supplierDir . '/supplier.config');
$formConfig     = UtilityService::loadConfig($formDir . '/activity.config');

// Validate configs
UtilityService::validateConfig($supplierConfig, [
  'supplierUsername',
  'supplierPassword',
  'brandColor',
  'brandLogo'
]);

UtilityService::validateConfig($formConfig, [
  'activityTitle',
  'activityIds',
  'guestTypeIds'
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>
    <?= htmlspecialchars($formConfig['activityTitle']); ?> |
    <?= htmlspecialchars($supplierConfig['supplierUsername']); ?>
  </title>
  <link rel="stylesheet" href="/assets/css/main.css">
  <script type="module" src="/assets/js/main.js"></script>
</head>
<body class="font-sans">

  <!-- Inject supplier brand color -->
  <?php include __DIR__ . '/../../../../partials/layout/branding.php'; ?>

  <!-- Header -->
  <?php include __DIR__ . '/../../../../partials/layout/header.php'; ?>

  <!-- Gallery -->
  <?php include __DIR__ . '/../../../../partials/shared/gallery.php'; ?>

  <!-- Booking Form -->
  <form class="BookingForm space-y-6 mt-6" data-activity-ids='<?= json_encode($formConfig["activityIds"]); ?>'>

    <?php if (!empty($formConfig['enableActivityInfo'])): ?>
      <?php include __DIR__ . '/../../../../partials/layout/form-advanced.php'; ?>
    <?php else: ?>
      <?php include __DIR__ . '/../../../../partials/layout/form-template.php'; ?>
    <?php endif; ?>

    <!-- Calendar -->
    <?php include __DIR__ . '/../../../../partials/form/calendar.php'; ?>

    <!-- Guest Types -->
    <?php include __DIR__ . '/../../../../partials/form/guest-types.php'; ?>

    <!-- Timeslot -->
    <?php include __DIR__ . '/../../../../partials/form/timeslot.php'; ?>

    <!-- Optional Gold Card Number (not yet scaffolded) -->
    <?php // include __DIR__ . '/../../../../partials/form/goldcard.php'; ?>

    <!-- Upsell -->
    <?php include __DIR__ . '/../../../../partials/form/goldcard-upsell.php'; ?>

    <!-- CTA -->
    <?php
      $label   = "Book Now";
      $type    = "submit";
      $variant = "primary";
      include __DIR__ . '/../../../../partials/shared/button.php';
    ?>

    <!-- Price Note -->
    <p class="text-xs text-gray-500 mt-2">All prices are in US dollars.</p>
  </form>

  <!-- Footer -->
  <?php include __DIR__ . '/../../../../partials/layout/footer.php'; ?>

</body>
</html>
