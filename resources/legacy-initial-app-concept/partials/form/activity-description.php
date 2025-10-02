<?php
if (!empty($formConfig['enableActivityDescription']) && !empty($formConfig['activityDescription'])): ?>
<section class="activity-descriptio mb-8">
    <h2 class="activity-description__title text-base font-medium mb-2">Activity Details</h2>
    <div class="activity-description__content text-sm leading-normal text-slate-800">
        <?php 
        // Allow limited HTML tags for basic formatting and links
        $allowedTags = '<a><strong><em><br><p>';
        echo nl2br(strip_tags($formConfig['activityDescription'], $allowedTags));
        ?>
    </div>
</section>
<?php endif; ?>
