<?php
if (!empty($formConfig['enableActivityDirections']) && !empty($formConfig['activityDirections'])): ?>
<section class="activity-descriptio mb-8">
    <h2 class="activity-description__title text-base font-medium mb-2">Directions</h2>
    <div class="activity-description__content text-sm leading-normal text-slate-800">
        <?php echo nl2br(htmlspecialchars($formConfig['activityDirections'])); ?>
    </div>
</section>
<?php endif; ?>
