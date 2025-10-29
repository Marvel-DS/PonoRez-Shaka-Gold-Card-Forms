<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\DTO\AvailabilityCalendar;
use PonoRez\SGCForms\DTO\TransportationSet;
use PonoRez\SGCForms\DTO\UpgradeCollection;
use PonoRez\SGCForms\Services\ActivityInfoService;
use PonoRez\SGCForms\Services\AvailabilityService;
use PonoRez\SGCForms\Services\GuestTypeService;
use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\Services\TransportationService;
use PonoRez\SGCForms\Services\UpgradeService;
use PonoRez\SGCForms\UtilityService;
use SoapFault;

final class PonorezFullStackIntegrationTest extends TestCase
{
    public function testFetchesCompleteDatasetForConfiguredActivity(): void
    {
        $supplierSlug = getenv('PONOREZ_TEST_SUPPLIER_SLUG') ?: '';
        $activitySlug = getenv('PONOREZ_TEST_ACTIVITY_SLUG') ?: '';

        if ($supplierSlug === '' || $activitySlug === '') {
            self::markTestSkipped(
                'Set PONOREZ_TEST_SUPPLIER_SLUG and PONOREZ_TEST_ACTIVITY_SLUG to run the full-stack integration test.'
            );
        }

        $travelDate = $this->normaliseTravelDate(getenv('PONOREZ_TEST_TRAVEL_DATE') ?: null);
        $guestCounts = $this->resolveGuestCounts();

        $supplierConfig = UtilityService::loadSupplierConfig($supplierSlug);
        $activityConfig = UtilityService::loadActivityConfig($supplierSlug, $activitySlug);
        $primaryActivityId = UtilityService::getPrimaryActivityId($activityConfig);

        self::assertNotNull($primaryActivityId, 'Primary activity ID is required for Ponorez lookups.');

        $soapClientBuilder = new SoapClientBuilder();
        $soapClient = $soapClientBuilder->build();

        $guestTypeService = new GuestTypeService(
            UtilityService::createCache('cache/guest-types'),
            $soapClientBuilder
        );
        $guestTypes = $this->callSoapOrSkip(
            static fn () => $guestTypeService->fetch($supplierSlug, $activitySlug, $travelDate, $guestCounts),
            'guest types'
        );
        self::assertGreaterThan(0, $guestTypes->count(), 'Expected at least one guest type.');

        $guestTypesPayload = [
            'serviceLogin' => [
                'username' => (string) $supplierConfig['soapCredentials']['username'],
                'password' => (string) $supplierConfig['soapCredentials']['password'],
            ],
            'supplierId' => (int) $supplierConfig['supplierId'],
            'activityId' => $primaryActivityId,
            'date' => $travelDate,
        ];

        if ($guestCounts !== []) {
            $guestTypesPayload['guestCounts'] = array_map(
                static fn ($id, $count) => ['guestTypeId' => $id, 'guestCount' => $count],
                array_keys($guestCounts),
                array_values($guestCounts)
            );
        }

        $guestTypesRaw = $this->normalizeSoapList(
            $this->callSoapOrSkip(
                static fn () => $soapClient->__soapCall('getActivityGuestTypes', [$guestTypesPayload]),
                'guest types (raw)'
            )
        );

        $activityInfoService = new ActivityInfoService(
            UtilityService::createCache('cache/activity-info'),
            $soapClientBuilder
        );
        $activityInfoNormalized = $this->callSoapOrSkip(
            static fn () => $activityInfoService->getActivityInfo($supplierSlug, $activitySlug),
            'activity info'
        );

        $activityInfoRaw = $this->normalizeSoapObject(
            $this->callSoapOrSkip(
                static fn () => $soapClient->__soapCall('getActivity', [[
                    'serviceLogin' => [
                        'username' => (string) $supplierConfig['soapCredentials']['username'],
                        'password' => (string) $supplierConfig['soapCredentials']['password'],
                    ],
                    'supplierId' => (int) $supplierConfig['supplierId'],
                    'activityId' => $primaryActivityId,
                ]]),
                'activity info (raw)'
            )
        );

        $availabilityService = new AvailabilityService(
            $soapClientBuilder,
            null,
            UtilityService::createCache('cache/availability')
        );
        $availability = $this->callSoapOrSkip(
            static fn () => $availabilityService->fetchCalendar(
                $supplierSlug,
                $activitySlug,
                $travelDate,
                $guestCounts
            ),
            'availability'
        );
        self::assertInstanceOf(AvailabilityCalendar::class, $availability['calendar']);

        $transportationService = new TransportationService(
            UtilityService::createCache('cache/transportation'),
            $soapClientBuilder
        );
        $transportationSet = $this->callSoapOrSkip(
            static fn () => $transportationService->fetch($supplierSlug, $activitySlug),
            'transportation'
        );
        self::assertInstanceOf(TransportationSet::class, $transportationSet);

        $transportationRaw = $this->normalizeSoapList(
            $this->callSoapOrSkip(
                static fn () => $soapClient->__soapCall('getActivityTransportationRoutes', [[
                    'serviceLogin' => [
                        'username' => (string) $supplierConfig['soapCredentials']['username'],
                        'password' => (string) $supplierConfig['soapCredentials']['password'],
                    ],
                    'supplierId' => (int) $supplierConfig['supplierId'],
                    'activityId' => $primaryActivityId,
                ]]),
                'transportation (raw)'
            )
        );

        $upgradeService = new UpgradeService(
            UtilityService::createCache('cache/upgrades'),
            $soapClientBuilder
        );
        $upgradeCollection = $this->callSoapOrSkip(
            static fn () => $upgradeService->fetch($supplierSlug, $activitySlug),
            'upgrades'
        );
        self::assertInstanceOf(UpgradeCollection::class, $upgradeCollection);

        $upgradesRaw = $this->normalizeSoapList(
            $this->callSoapOrSkip(
                static fn () => $soapClient->__soapCall('getActivityUpgrades', [[
                    'serviceLogin' => [
                        'username' => (string) $supplierConfig['soapCredentials']['username'],
                        'password' => (string) $supplierConfig['soapCredentials']['password'],
                    ],
                    'supplierId' => (int) $supplierConfig['supplierId'],
                    'activityId' => $primaryActivityId,
                    'date' => $travelDate,
                ]]),
                'upgrades (raw)'
            )
        );

        $export = [
            'supplier' => $supplierSlug,
            'activity' => $activitySlug,
            'travelDate' => $travelDate,
            'guestCounts' => $guestCounts,
            'guestTypes' => [
                'ponorez' => $guestTypesRaw,
                'merged' => $guestTypes->toArray(),
            ],
            'activityInfo' => [
                'ponorez' => $activityInfoRaw,
                'normalized' => $activityInfoNormalized,
            ],
            'availability' => [
                'calendar' => $availability['calendar']->toArray(),
                'timeslots' => array_map(
                    static fn ($slot) => $slot->toArray(),
                    $availability['timeslots']
                ),
                'metadata' => $availability['metadata'],
            ],
            'transportation' => [
                'ponorez' => $transportationRaw,
                'merged' => $transportationSet->toArray(),
            ],
            'upgrades' => [
                'ponorez' => $upgradesRaw,
                'merged' => $upgradeCollection->toArray(),
            ],
        ];

        $this->assertNotEmpty($export['guestTypes']['ponorez'], 'Ponorez guest type export should not be empty.');
        $this->assertArrayHasKey('activities', $export['activityInfo']['normalized']);
        $this->assertArrayHasKey('calendar', $export['availability']);
        $this->assertArrayHasKey('metadata', $export['availability']);

        fwrite(
            \STDOUT,
            PHP_EOL . 'Ponorez full-stack dataset snapshot:' . PHP_EOL .
            json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL
        );
    }

    private function normaliseTravelDate(?string $value): string
    {
        if ($value !== null && trim($value) !== '') {
            return trim($value);
        }

        return (new DateTimeImmutable('+7 days'))->format('Y-m-d');
    }

    /**
     * @return array<string|int,int>
     */
    private function resolveGuestCounts(): array
    {
        $raw = getenv('PONOREZ_TEST_GUEST_COUNTS') ?: null;
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            return [];
        }

        $counts = [];
        foreach ($decoded as $key => $value) {
            if (!is_numeric($value)) {
                continue;
            }

            if (is_int($key) || ctype_digit((string) $key)) {
                $counts[(int) $key] = (int) $value;
                continue;
            }

            $counts[$key] = (int) $value;
        }

        return $counts;
    }

    /**
     * @template T
     * @param callable():T $callback
     * @return T
     */
    private function callSoapOrSkip(callable $callback, string $context)
    {
        try {
            return $callback();
        } catch (SoapFault $exception) {
            self::markTestSkipped(sprintf(
                'Ponorez %s SOAP call failed: %s',
                $context,
                $exception->getMessage()
            ));
        }
    }

    private function normalizeSoapList(mixed $payload): array
    {
        if ($payload instanceof \stdClass && property_exists($payload, 'return')) {
            $payload = $payload->return;
        }

        if ($payload instanceof \stdClass) {
            $payload = [$payload];
        }

        if (!is_array($payload)) {
            return [];
        }

        $normalized = [];
        foreach ($payload as $row) {
            if ($row instanceof \stdClass) {
                $row = json_decode(json_encode($row), true);
            }
            if (!is_array($row)) {
                continue;
            }
            $normalized[] = $row;
        }

        return $normalized;
    }

    private function normalizeSoapObject(mixed $payload): ?array
    {
        if ($payload instanceof \stdClass && property_exists($payload, 'return')) {
            $payload = $payload->return;
        }

        if ($payload instanceof \stdClass) {
            $payload = json_decode(json_encode($payload), true);
        }

        return is_array($payload) ? $payload : null;
    }
}
