<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Utility;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\UtilityService;

final class UtilityServiceTest extends TestCase
{
    public function testFormatSupplierContent(): void
    {
        $supplier = [
            'links' => [
                'faq' => 'https://example.com/faq',
                'terms' => 'https://example.com/terms',
            ],
        ];

        $input = '[p]Hello [b]World[/b][/p][FAQ] & [TERMS]';
        $formatted = UtilityService::formatSupplierContent($input, $supplier);

        self::assertStringContainsString('<p>Hello <strong>World</strong></p>', $formatted);
        self::assertStringContainsString('<a href="https://example.com/faq"', $formatted);
        self::assertStringContainsString('Terms &amp; Conditions', $formatted);
    }
}
