<?php
/**
 * Template part for rendering additional activity information.
 *
 * This template displays the activity notes with limited HTML formatting support
 * for basic tags like links and emphasis. It also optionally renders an activity
 * checklist if enabled and provided. 
 */

if (!empty($formConfig['enableActivityNotes']) && !empty($formConfig['activityNotes'])): ?>
<section class="activity-description mb-8" aria-describedby="additional-info-desc">
    <!-- Section heading for additional information -->
    <h2 class="activity-description__title text-base font-medium mb-2">Additional Information</h2>
    <div id="additional-info-desc" class="activity-description__content text-sm leading-normal text-slate-800">
        <?php 
            // Output activity notes with limited HTML tags for basic formatting and links
            $allowedTags = '<a><strong><em><br><p>';
            echo nl2br(strip_tags($formConfig['activityNotes'], $allowedTags));
        ?>
    </div>
    <?php if (!empty($formConfig['enableActivityChecklist']) && !empty($formConfig['activityChecklist'])): ?>
        <?php
            // Prepare checklist items: extract first item as title, rest as list
            $activityChecklist = array_values($formConfig['activityChecklist']);
            $firstItem = array_shift($activityChecklist);
        ?>
        <?php if (!empty($firstItem)): ?>
            <!-- Checklist title using h3 for proper heading hierarchy -->
            <h3 class="activity-description__title text-sm font-medium mb-2 mt-4"><?php echo htmlspecialchars($firstItem); ?></h3>
        <?php endif; ?>
        <?php if (!empty($activityChecklist)): ?>
            <!-- Checklist items with accessible list role and descriptive aria-label -->
            <ul role="list" aria-label="Activity Checklist Items" class="activity-checklist list-disc list-inside text-sm text-slate-800">
                <?php foreach ($activityChecklist as $item): ?>
                    <li><?php echo htmlspecialchars($item); ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php endif; ?>
</section>
<?php endif; ?>
</file>
