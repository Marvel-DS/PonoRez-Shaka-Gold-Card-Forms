<?php

declare(strict_types=1);

if (!function_exists('sgcFormsBuildGoldCardContext')) {
    /**
     * Build the data context used across Shaka Gold Card components.
     *
     * @param array $pageContext Overall page context supplied to form components.
     *
     * @return array{
     *     number: array{
     *         inputId: string,
     *         label: string,
     *         description: string,
     *         placeholder: string,
     *         helperText: string,
     *         value: string
     *     },
     *     upsell: array{
     *         checkboxId: string,
     *         label: string,
     *         description: string,
     *         coverageHint: string
     *     }
     * }
     */
    function sgcFormsBuildGoldCardContext(array $pageContext): array
    {
        $page = $pageContext ?? [];
        $bootstrap = $page['bootstrap'] ?? [];
        $activity = $bootstrap['activity'] ?? [];
        $labels = is_array($activity['uiLabels'] ?? null) ? $activity['uiLabels'] : [];

        $numberLabel = $labels['shakaGoldCardNumber'] ?? 'Shaka Gold Card Number';
        $upsellLabel = $labels['shakaGoldCardUpsell'] ?? 'Buying for someone else?';

        $rawGoldCardNumber = $activity['shakaGoldCardNumber'] ?? '';
        $shakaGoldCardNumber = is_string($rawGoldCardNumber) ? trim($rawGoldCardNumber) : '';

        return [
            'number' => [
                'inputId' => 'shaka-gold-card-number',
                'label' => $numberLabel,
                'description' => 'Confirm your Shaka Gold Card number so we can apply your discount.',
                'value' => $shakaGoldCardNumber,
            ],
            'upsell' => [
                'checkboxId' => 'shaka-gold-card-upsell',
                'label' => $upsellLabel,
                'description' => 'Add a Shaka Gold Card for $30 and help them save on future adventures.',
                'coverageHint' => 'Covers up to 4 guests. Additional guests coverage available for purchase for $7.50 each',
            ],
        ];
    }
}

