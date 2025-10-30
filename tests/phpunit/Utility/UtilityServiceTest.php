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
            'slug' => 'supplier-slug',
            'name' => 'Supplier Name',
            'links' => [
                'faq' => 'https://example.com/faq',
                'terms' => 'https://example.com/terms',
            ],
        ];

        $input = '[p]Hello [b]World[/b][/p][img activity-slug-01.jpg][FAQ] & [TERMS]';
        $formatted = UtilityService::formatSupplierContent($input, $supplier);

        self::assertStringContainsString('<p>Hello <strong>World</strong></p>', $formatted);
        self::assertStringContainsString('<a href="https://example.com/faq"', $formatted);
        self::assertStringContainsString('Terms &amp; Conditions', $formatted);
        self::assertStringContainsString('<img', $formatted);
        self::assertStringContainsString('suppliers/supplier-slug/images/activity-slug-01.jpg', $formatted);
        self::assertStringContainsString('class="w-full h-auto rounded-xl mb-4"', $formatted);
    }
}
