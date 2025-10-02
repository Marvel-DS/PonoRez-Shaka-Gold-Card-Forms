<?php
use PonoRez\SGCForms\UtilityService;
?>

<main class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">
  <form class="space-y-8">

    <!-- Booking Form -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
      
      <div>

        <?php include UtilityService::partialsBaseDir() . '/form/component-guest-types.php'; ?>

        <?php include UtilityService::partialsBaseDir() . '/form/component-calendar.php'; ?>

      </div>

      <div>

        <?php include UtilityService::partialsBaseDir() . '/form/component-timeslot.php'; ?>

        <?php include UtilityService::partialsBaseDir() . '/form/component-goldcard.php'; ?>

        <?php include UtilityService::partialsBaseDir() . '/form/component-goldcard-upsell.php'; ?>

      </div>

    </div>

    <!-- Form Submit -->
    <div>
      <?php include UtilityService::partialsBaseDir() . '/form/component-button.php'; ?>
    </div>

  </form>
</main>