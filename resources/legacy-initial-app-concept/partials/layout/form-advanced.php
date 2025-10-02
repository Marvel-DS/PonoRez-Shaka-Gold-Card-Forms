<?php
/**
 * Advanced Booking Form Layout
 *
 * This layout provides an advanced booking form that enhances user experience,
 * accessibility, and SEO. It includes semantic sections for descriptive content
 * and integrates various form components such as guest types, calendar, timeslot,
 * and promotional upsells. The form uses proper ARIA labels and method attributes
 * to ensure accessibility and usability.
 */

use PonoRez\SGCForms\UtilityService;
?>

<main class="max-w-6xl mx-auto p-4 sm:p-6 lg:p-8">
  <form class="space-y-8" method="post" aria-label="Booking form">

    <!-- Booking Form -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

      <div>

        <!-- Activity Description -->
        <?php include UtilityService::partialsBaseDir() . '/form/activity-description.php'; ?>

        <!-- Activity Additional Info -->
        <?php include UtilityService::partialsBaseDir() . '/form/activity-additional-infomation.php'; ?>

        <!-- Activity Restrictions -->
        <?php include UtilityService::partialsBaseDir() . '/form/activity-restrictions.php'; ?>

        <!-- Activity Directions -->
        <?php include UtilityService::partialsBaseDir() . '/form/activity-directions.php'; ?>

      </div>

      <div>
        <!-- Guest Types Component -->
        <?php include UtilityService::partialsBaseDir() . '/form/component-guest-types.php'; ?>

        <!-- Calendar Component -->
        <?php include UtilityService::partialsBaseDir() . '/form/component-calendar.php'; ?>

        <!-- Timeslot Component -->
        <?php include UtilityService::partialsBaseDir() . '/form/component-timeslot.php'; ?>

        <!-- Gold Card Component -->
        <?php include UtilityService::partialsBaseDir() . '/form/component-goldcard.php'; ?>

        <!-- Gold Card Upsell Component -->
        <?php include UtilityService::partialsBaseDir() . '/form/component-goldcard-upsell.php'; ?>
      </div>

    </div>

    <!-- Form Submit -->
    <div>
      <?php include UtilityService::partialsBaseDir() . '/form/component-button.php'; ?>
    </div>

  </form>
</main>