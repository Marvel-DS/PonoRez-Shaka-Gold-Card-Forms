<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Utility;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\UtilityService;

final class UtilityServiceTest extends TestCase
{
    public function testLoadSupplierActivityInfoCache(): void
    {
        $supplierSlug = 'supplier-slug';
        $activitySlug = 'activity-slug';

        $supplierDir = UtilityService::supplierDirectory($supplierSlug);
        $cacheDir = $supplierDir . '/cache/activity-info';

        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
            self::fail('Unable to create supplier cache directory for test.');
        }

        $cacheFile = $cacheDir . '/' . $activitySlug . '.json';

        $payload = [
            ['id' => 123, 'times' => '7:30am Check In', 'name' => 'Test Activity'],
            ['activityId' => '456', 'times' => '9:00am Check In'],
        ];

        file_put_contents($cacheFile, json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            $result = UtilityService::loadSupplierActivityInfoCache($supplierSlug, $activitySlug);

            self::assertIsArray($result);
            self::assertArrayHasKey('123', $result);
            self::assertSame('7:30am Check In', $result['123']['times']);
            self::assertArrayHasKey('456', $result);
        } finally {
            @unlink($cacheFile);
            @rmdir($cacheDir);
            @rmdir($supplierDir . '/cache');
        }
    }
}

