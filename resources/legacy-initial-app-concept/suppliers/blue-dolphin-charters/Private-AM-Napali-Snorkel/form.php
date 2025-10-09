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
  <link rel="stylesheet" href="<?= UtilityService::getAssetsBaseUrl(__FILE__) ?>css/main.css">
  <script type="module" src="<?= UtilityService::getAssetsBaseUrl(__FILE__) ?>js/main.js"></script>
</head>
<body class="font-sans">

  <!-- Inject supplier branding -->
  <?php include UtilityService::partialsBaseDir() . '/layout/branding.php'; ?>

  <!-- Header -->
  <?php include UtilityService::partialsBaseDir() . '/layout/section-header.php'; ?>

  <!-- Gallery -->
  <?php include UtilityService::partialsBaseDir() . '/layout/section-hero.php'; ?>

  <!-- Booking Form -->
  <?php if (!empty($formConfig['enableActivityInfo'])): ?>
    <?php include UtilityService::partialsBaseDir() . '/layout/form-advanced.php'; ?>
  <?php else: ?>
    <?php include UtilityService::partialsBaseDir() . '/layout/form-template.php'; ?>
  <?php endif; ?>

  <!-- Footer -->
  <?php include UtilityService::partialsBaseDir() . '/layout/section-footer.php'; ?>

</body>
</html>
