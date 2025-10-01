<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Partials;

use PHPUnit\Framework\TestCase;

final class ComponentGuestTypesTest extends TestCase
{
    /**
     * @param array<string, mixed> $guestConfig
     */
    private function renderGuestTypes(array $guestConfig): string
    {
        $pageContext = [
            'bootstrap' => [
                'activity' => [
                    'guestTypes' => $guestConfig,
                    'uiLabels' => ['guestTypes' => 'Guests'],
                ],
            ],
        ];

        ob_start();
        /** @psalm-suppress UnresolvableInclude */
        include dirname(__DIR__, 3) . '/partials/form/component-guest-types.php';

        return (string) ob_get_clean();
    }

    public function testSelectGuestTypeIncludesConfiguredMaximum(): void
    {
        $markup = $this->renderGuestTypes([
            'ids' => ['child'],
            'labels' => ['child' => 'Child'],
            'descriptions' => ['child' => 'Ages 2-16'],
            'min' => ['child' => 0],
            'max' => ['child' => 10],
        ]);

        self::assertStringContainsString('data-guest-type="child"', $markup);
        self::assertStringContainsString('<option value="10">10</option>', $markup);
    }

    public function testSelectGuestTypeFallsBackWhenMaxBelowMin(): void
    {
        $markup = $this->renderGuestTypes([
            'ids' => ['duet'],
            'labels' => ['duet' => 'Duet'],
            'descriptions' => ['duet' => 'Two guests required'],
            'min' => ['duet' => 5],
            'max' => ['duet' => 2],
        ]);

        self::assertStringContainsString('data-guest-type="duet"', $markup);
        self::assertStringContainsString('data-max="15"', $markup);
        self::assertStringContainsString('<option value="15">15</option>', $markup);
    }
}

