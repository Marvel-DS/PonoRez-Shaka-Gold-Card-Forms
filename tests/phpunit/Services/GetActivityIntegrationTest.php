<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\UtilityService;
use SoapClient;
use stdClass;

final class GetActivityIntegrationTest extends TestCase
{
    public function testGetActivityReturnsStaticProfile(): void
    {
        $supplierSlug = getenv('PONOREZ_TEST_SUPPLIER_SLUG') ?: '';
        $activitySlug = getenv('PONOREZ_TEST_ACTIVITY_SLUG') ?: '';

        if ($supplierSlug === '' || $activitySlug === '') {
            self::markTestSkipped('Set PONOREZ_TEST_SUPPLIER_SLUG and PONOREZ_TEST_ACTIVITY_SLUG to run integration test.');
        }

        $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
        $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);

        $client = $this->buildSoapClient();
        $payload = [
            'serviceLogin' => [
                'username' => $supplierConfig['soapCredentials']['username'],
                'password' => $supplierConfig['soapCredentials']['password'],
            ],
            'supplierId' => (int) $supplierConfig['supplierId'],
            'activityId' => (int) $activityConfig['activityId'],
        ];

        $response = $client->__soapCall('getActivity', [$payload]);
        self::assertInstanceOf(stdClass::class, $response);

        $activityInfo = $response->return ?? null;
        self::assertInstanceOf(stdClass::class, $activityInfo, 'Expected getActivity to return activity details.');

        $this->assertHasNonEmptyString($activityInfo, 'name');
        $this->assertHasStringOrNull($activityInfo, 'island');
        $this->assertHasStringOrNull($activityInfo, 'times');
        $this->assertHasStringOrNull($activityInfo, 'description');
        $this->assertHasStringOrNull($activityInfo, 'notes');
        $this->assertHasStringOrNull($activityInfo, 'directions');

        $this->assertPropertyExists($activityInfo, 'startTimeMinutes');
        $this->assertPropertyExists($activityInfo, 'transportationMandatory');

        $export = [
            'name' => $activityInfo->name ?? null,
            'island' => $activityInfo->island ?? null,
            'times' => $activityInfo->times ?? null,
            'startTimeMinutes' => $activityInfo->startTimeMinutes ?? null,
            'transportationMandatory' => $activityInfo->transportationMandatory ?? null,
            'description' => $activityInfo->description ?? null,
            'notes' => $activityInfo->notes ?? null,
            'directions' => $activityInfo->directions ?? null,
        ];

        fwrite(\STDOUT, PHP_EOL . 'getActivity response snapshot:' . PHP_EOL);
        fwrite(\STDOUT, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL);
    }

    private function buildSoapClient(): SoapClient
    {
        $builder = new SoapClientBuilder();

        return $builder->build();
    }

    private function assertHasNonEmptyString(stdClass $payload, string $property): void
    {
        $this->assertPropertyExists($payload, $property);
        $value = $payload->{$property};
        self::assertIsString($value);
        self::assertNotSame('', trim($value));
    }

    private function assertHasStringOrNull(stdClass $payload, string $property): void
    {
        $this->assertPropertyExists($payload, $property);
        $value = $payload->{$property};

        if ($value !== null) {
            self::assertIsString($value);
        }
    }

    private function assertPropertyExists(stdClass $payload, string $property): void
    {
        self::assertTrue(
            property_exists($payload, $property),
            sprintf('Property "%s" was not present on the activity payload.', $property)
        );
    }
}
