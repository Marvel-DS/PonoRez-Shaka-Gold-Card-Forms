<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\DTO\AvailabilityDay;
use PonoRez\SGCForms\DTO\Timeslot;
use PonoRez\SGCForms\Services\AvailabilityService;
use PonoRez\SGCForms\Services\SoapClientFactory;
use RuntimeException;
use SoapClient;

final class AvailabilityTest extends TestCase
{
    private const SUPPLIER_SLUG = 'supplier-slug';
    private const ACTIVITY_SLUG = 'activity-slug';

    public function testFetchCalendarBuildsAvailabilityFromHttp(): void
    {
        $seats = [];
        $extended = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 10;

            $entry = ['aids' => [369]];
            if ($day === 15) {
                $entry['activities'] = [[
                    'activityId' => 369,
                    'activityName' => 'Deluxe Morning Napali Coast Snorkel Tour 8:00am',
                    'available' => true,
                    'details' => [
                        'island' => 'Kauai',
                        'times' => '8:00am Check In',
                    ],
                ]];
            }

            $extended['d' . $day] = $entry;
        }

        $httpResponse = json_encode([
            'yearmonth_2024_3' => $seats,
            'yearmonth_2024_3_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $capturedRequests = [];
        $httpFetcher = function (string $url, array $params) use (&$capturedRequests, $httpResponse): string {
            $capturedRequests[] = $params;
            return $httpResponse;
        };

        $handlers = [
            'getActivityTimeslots' => static fn () => [
                'timeslots' => [
                    ['id' => '730', 'label' => '7:30 AM Departure', 'available' => 10],
                    ['id' => '1230', 'label' => '12:30 PM Departure', 'available' => 6],
                ],
            ],
        ];

        $client = new AvailabilityRecordingSoapClient($handlers);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-03-15',
            ['345' => 2],
            [369],
            '2024-03'
        );

        self::assertNotEmpty($capturedRequests);
        $firstRequest = $capturedRequests[0];
        self::assertSame('COMMON_AVAILABILITYCHECKJSON', $firstRequest['action']);
        self::assertSame('369', $firstRequest['activityid']);
        self::assertSame('2024_3', $firstRequest['year_months']);
        self::assertNotEmpty($firstRequest['minavailability']);

        $calendar = $result['calendar'];
        self::assertSame(31, $calendar->count());
        foreach ($calendar->all() as $day) {
            self::assertInstanceOf(AvailabilityDay::class, $day);
            self::assertSame('available', $day->getStatus());
        }

        $timeslots = $result['timeslots'];
        self::assertCount(1, $timeslots);
        self::assertContainsOnlyInstancesOf(Timeslot::class, $timeslots);
        self::assertSame('369', $timeslots[0]->getId());
        self::assertSame('8:00am Check In', $timeslots[0]->getLabel());
        self::assertSame([
            'island' => 'Kauai',
            'times' => '8:00am Check In',
        ], $timeslots[0]->getDetails());

        $metadata = $result['metadata'];
        self::assertArrayNotHasKey('source', $metadata);
        self::assertSame(2, $metadata['requestedSeats']);
        self::assertSame('available', $metadata['selectedDateStatus']);
        self::assertSame('available', $metadata['timeslotStatus']);
        self::assertSame('2024-03-01', $metadata['firstAvailableDate']);
        self::assertSame('verified', $metadata['certificateVerification']);
        self::assertSame([369], $metadata['extended']['2024-03-15']['activityIds']);
        self::assertSame([369], $metadata['extended']['2024-03-15']['availableActivityIds']);
        self::assertSame([
            [
                'activityId' => 369,
                'activityName' => 'Deluxe Morning Napali Coast Snorkel Tour 8:00am',
                'available' => true,
                'details' => [
                    'island' => 'Kauai',
                    'times' => '8:00am Check In',
                ],
            ],
        ], $metadata['extended']['2024-03-15']['activities']);

        self::assertSame(0, $factory->buildCount);
        self::assertSame([], $client->calls);
    }

    public function testExtendedActivitiesAreSynthesizedFromTimes(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 0;
        }
        $seats['d2'] = 12;

        $extended = [
            'd2' => [
                'aids' => [639],
                'times' => [
                    639 => '8:00am Check In',
                ],
            ],
        ];

        $httpResponse = json_encode([
            'yearmonth_2024_5' => $seats,
            'yearmonth_2024_5_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-05-02',
            ['345' => 2],
            [639],
            '2024-05'
        );

        $metadata = $result['metadata'];
        $this->assertArrayHasKey('extended', $metadata);
        $dayEntry = $metadata['extended']['2024-05-02'];

        self::assertSame([639], $dayEntry['activityIds']);
        self::assertSame([639], $dayEntry['availableActivityIds']);
        self::assertCount(1, $dayEntry['activities']);
        $activity = $dayEntry['activities'][0];
        self::assertSame(639, $activity['activityId']);
        self::assertSame('8:00am Check In', $activity['activityName']);
        self::assertSame(['times' => '8:00am Check In'], $activity['details']);

        $timeslots = $result['timeslots'];
        self::assertCount(1, $timeslots);
        self::assertSame('639', $timeslots[0]->getId());
        self::assertSame('8:00am Check In', $timeslots[0]->getLabel());
        self::assertSame(['times' => '8:00am Check In'], $timeslots[0]->getDetails());

        self::assertSame('available', $metadata['timeslotStatus']);
        self::assertSame(0, $factory->buildCount);
        self::assertSame([], $client->calls);
    }

    public function testFetchCalendarMarksLimitedWhenSeatCountLow(): void
    {
        $seats = [];
        for ($day = 1; $day <= 30; $day++) {
            $seats['d' . $day] = 3;
        }

        $httpResponse = json_encode([
            'yearmonth_2024_4' => $seats,
            'yearmonth_2024_4_ex' => [],
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => ['timeslots' => []],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-04-10',
            ['345' => 2],
            [369],
            '2024-04'
        );

        foreach ($result['calendar']->all() as $day) {
            self::assertSame('limited', $day->getStatus());
        }

        self::assertSame('unavailable', $result['metadata']['timeslotStatus']);
        self::assertSame('verified', $result['metadata']['certificateVerification']);
    }

    public function testFetchCalendarMarksSoldOutWhenSeatsZero(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 0;
        }

        $httpResponse = json_encode([
            'yearmonth_2024_5' => $seats,
            'yearmonth_2024_5_ex' => [],
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => ['timeslots' => []],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-05-05'
        );

        foreach ($result['calendar']->all() as $day) {
            self::assertSame('sold_out', $day->getStatus());
        }

        self::assertSame([], $result['timeslots']);
        self::assertSame('unavailable', $result['metadata']['timeslotStatus']);
        self::assertSame('verified', $result['metadata']['certificateVerification']);
        self::assertNotEmpty($client->calls);
    }

    public function testFetchCalendarTreatsExtendedAvailabilityAsTimeslotSignal(): void
    {
        $seats = [];
        $extended = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 5;
            if ($day === 12) {
                $extended['d' . $day] = ['aids' => [369, 555]];
            }
        }

        $httpResponse = json_encode([
            'yearmonth_2024_7' => $seats,
            'yearmonth_2024_7_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => ['timeslots' => []],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-07-12',
            ['345' => 2],
            [369, 555]
        );

        self::assertSame('available', $result['metadata']['timeslotStatus']);
        self::assertSame([369, 555], $result['metadata']['extended']['2024-07-12']['activityIds']);
        self::assertSame([369, 555], $result['metadata']['extended']['2024-07-12']['availableActivityIds']);
        self::assertSame([], $result['metadata']['extended']['2024-07-12']['activities']);
        self::assertSame([], $result['timeslots']);
    }

    public function testExtendedMetadataIncludesActivityDetailsWhenProvided(): void
    {
        $seats = [];
        for ($day = 1; $day <= 30; $day++) {
            $seats['d' . $day] = 10;
        }

        $extended = [
            'd30' => [
                'aids' => ['639', '5280'],
                'activities' => [
                    [
                        'activityId' => '639',
                        'activityName' => 'Morning Tour',
                        'available' => 'Y',
                        'details' => [
                            'times' => '8:00am Check In',
                            'island' => 'Kauai',
                        ],
                    ],
                    [
                        'activityId' => '5280',
                        'activityname' => 'Afternoon Tour',
                        'status' => 'unavailable',
                        'details' => (object) ['times' => '12:00pm Check In'],
                        'checkin' => '11:30am',
                    ],
                    [
                        'activityId' => '9999',
                        'activityName' => 'Hidden Tour',
                    ],
                ],
            ],
        ];

        $httpResponse = json_encode([
            'yearmonth_2024_6' => $seats,
            'yearmonth_2024_6_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => ['timeslots' => []],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-06-30',
            ['345' => 2],
            [639, 5280]
        );

        $metadataEntry = $result['metadata']['extended']['2024-06-30'];

        self::assertSame([639, 5280], $metadataEntry['activityIds']);
        self::assertSame([639], $metadataEntry['availableActivityIds']);
        self::assertSame([
            [
                'activityId' => 639,
                'activityName' => 'Morning Tour',
                'available' => true,
                'details' => [
                    'times' => '8:00am Check In',
                    'island' => 'Kauai',
                ],
            ],
            [
                'activityId' => 5280,
                'activityName' => 'Afternoon Tour',
                'available' => false,
                'details' => [
                    'times' => '12:00pm Check In',
                    'checkin' => '11:30am',
                ],
            ],
        ], $metadataEntry['activities']);

        self::assertSame([
            639 => '8:00am Check In',
            5280 => '12:00pm Check In',
        ], $metadataEntry['times']);
    }

    public function testExtendedMetadataIncludesTopLevelTimes(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 10;
        }

        $extended = [
            'd15' => [
                'aids' => ['639', '5280'],
                'times' => [
                    '639' => '8:00am Check In',
                    '5280' => '12:00pm Check In',
                ],
            ],
        ];

        $httpResponse = json_encode([
            'yearmonth_2024_5' => $seats,
            'yearmonth_2024_5_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => ['timeslots' => []],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-05-15',
            ['345' => 2],
            [639, 5280]
        );

        $metadataEntry = $result['metadata']['extended']['2024-05-15'];

        self::assertSame([639, 5280], $metadataEntry['activityIds']);
        self::assertSame([639, 5280], $metadataEntry['availableActivityIds']);
        self::assertSame([
            639 => '8:00am Check In',
            5280 => '12:00pm Check In',
        ], $metadataEntry['times']);
    }

    public function testExtendedMetadataExtractsDepartureDetails(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 10;
        }

        $extended = [
            'd20' => [
                'aids' => ['639', '5280'],
                'departures' => [
                    [
                        'id' => '639',
                        'label' => '8:00am Check In',
                        'checkin' => '7:30am',
                        'available' => 'Y',
                    ],
                    [
                        'departureId' => '5280',
                        'time' => '12:00pm Check In',
                        'availability' => 'N',
                    ],
                ],
            ],
        ];

        $httpResponse = json_encode([
            'yearmonth_2024_5' => $seats,
            'yearmonth_2024_5_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => ['timeslots' => []],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-05-20',
            ['345' => 2],
            [639, 5280]
        );

        $metadataEntry = $result['metadata']['extended']['2024-05-20'];

        self::assertSame([639, 5280], $metadataEntry['activityIds']);
        self::assertSame([
            [
                'activityId' => 639,
                'activityName' => '8:00am Check In',
                'available' => true,
                'details' => [
                    'checkin' => '7:30am',
                    'times' => '8:00am Check In',
                ],
            ],
            [
                'activityId' => 5280,
                'available' => false,
                'details' => [
                    'time' => '12:00pm Check In',
                    'times' => '12:00pm Check In',
                ],
                'activityName' => '12:00pm Check In',
            ],
        ], $metadataEntry['activities']);

        self::assertSame([
            639 => '8:00am Check In',
            5280 => '12:00pm Check In',
        ], $metadataEntry['times']);
    }

    public function testFetchCalendarTreatsUnavailableActivitiesAsSoldOut(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 10;
        }
        $seats['d12'] = 'unknown';

        $extended = [
            'd12' => [
                'aids' => ['639', '5280'],
                'activities' => [
                    [
                        'activityId' => '639',
                        'available' => 'N',
                        'details' => ['times' => '8:00am Check In'],
                    ],
                    [
                        'activityId' => '5280',
                        'available' => false,
                        'details' => ['times' => '12:00pm Check In'],
                    ],
                ],
                'times' => [
                    '639' => '8:00am Check In',
                    '5280' => '12:00pm Check In',
                ],
            ],
        ];

        $httpResponse = json_encode([
            'yearmonth_2024_5' => $seats,
            'yearmonth_2024_5_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => ['timeslots' => []],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-05-12',
            ['345' => 2],
            [639, 5280]
        );

        $calendarDay = null;
        foreach ($result['calendar']->all() as $day) {
            if ($day->getDate() === '2024-05-12') {
                $calendarDay = $day;
                break;
            }
        }

        self::assertInstanceOf(AvailabilityDay::class, $calendarDay);
        self::assertSame('sold_out', $calendarDay->getStatus());

        $metadataEntry = $result['metadata']['extended']['2024-05-12'];
        self::assertSame([], $metadataEntry['availableActivityIds']);
        self::assertSame('unavailable', $result['metadata']['timeslotStatus']);
        self::assertSame([], $result['timeslots']);
        self::assertSame(0, $factory->buildCount);
        self::assertSame([], $client->calls);
    }

    public function testFetchCalendarSkipsTimeslotLookupWhenExtendedShowsSoldOut(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 0;
        }

        $extended = [
            'd10' => [],
        ];

        $httpResponse = json_encode([
            'yearmonth_2024_5' => $seats,
            'yearmonth_2024_5_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static function (): void {
                throw new RuntimeException('Timeslots should not be fetched for sold out dates.');
            },
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-05-10',
            ['345' => 2],
            [639]
        );

        self::assertSame([], $result['timeslots']);
        self::assertSame('unavailable', $result['metadata']['timeslotStatus']);
        self::assertSame(0, $factory->buildCount);
        self::assertSame([], $client->calls);
    }

    public function testFetchCalendarUsesExtendedTimesMetadataWithoutSoapLookup(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 5;
        }

        $extended = [
            'd12' => [
                'aids' => ['639', '5280'],
                'departures' => [
                    [
                        'id' => '639',
                        'label' => '8:00am Check In',
                        'checkin' => '7:30am',
                    ],
                    [
                        'departureId' => '5280',
                        'time' => '12:00pm Check In',
                    ],
                ],
            ],
        ];

        $httpResponse = json_encode([
            'yearmonth_2024_7' => $seats,
            'yearmonth_2024_7_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static function (): void {
                throw new RuntimeException('Timeslots should be synthesized from metadata.');
            },
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-07-12',
            ['345' => 2],
            [639, 5280]
        );

        $timeslots = $result['timeslots'];

        self::assertCount(2, $timeslots);
        self::assertContainsOnlyInstancesOf(Timeslot::class, $timeslots);
        self::assertSame('639', $timeslots[0]->getId());
        self::assertSame('8:00am Check In', $timeslots[0]->getLabel());
        self::assertSame([
            'checkin' => '7:30am',
            'times' => '8:00am Check In',
        ], $timeslots[0]->getDetails());
        self::assertSame('5280', $timeslots[1]->getId());
        self::assertSame('12:00pm Check In', $timeslots[1]->getLabel());
        self::assertSame([
            'time' => '12:00pm Check In',
            'times' => '12:00pm Check In',
        ], $timeslots[1]->getDetails());

        self::assertSame('available', $result['metadata']['timeslotStatus']);
        self::assertSame(0, $factory->buildCount);
        self::assertSame([], $client->calls);
    }

    public function testFetchCalendarUsesDetailsTimesForTimeslotLabel(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 10;
        }

        $httpResponse = json_encode([
            'yearmonth_2024_8' => $seats,
            'yearmonth_2024_8_ex' => [],
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => [
                'timeslots' => [
                    [
                        'id' => '5260',
                        'details' => ['times' => '8:00am Check In'],
                        'available' => 12,
                    ],
                ],
            ],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-08-15',
            ['345' => 2],
            [369]
        );

        $timeslots = $result['timeslots'];
        self::assertCount(1, $timeslots);
        self::assertSame('5260', $timeslots[0]->getId());
        self::assertSame('8:00am Check In', $timeslots[0]->getLabel());
        self::assertSame(['times' => '8:00am Check In'], $timeslots[0]->getDetails());
    }

    public function testFetchCalendarUsesNestedDetailsTimesForTimeslotLabel(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 10;
        }

        $httpResponse = json_encode([
            'yearmonth_2024_8' => $seats,
            'yearmonth_2024_8_ex' => [],
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => [
                'timeslots' => [
                    [
                        'id' => '5260',
                        'label' => 'Departure 639',
                        'details' => [
                            'times' => [
                                'provided' => '8:00am Check In',
                                'display' => '8:00 AM Check-In',
                            ],
                        ],
                        'available' => 12,
                    ],
                ],
            ],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-08-15',
            ['345' => 2],
            [369]
        );

        $timeslots = $result['timeslots'];
        self::assertCount(1, $timeslots);
        self::assertSame('5260', $timeslots[0]->getId());
        self::assertSame('8:00am Check In', $timeslots[0]->getLabel());
        self::assertSame(['times' => '8:00am Check In'], $timeslots[0]->getDetails());
    }

    public function testFetchCalendarReturnsTimeslotsEvenWhenCalendarShowsSoldOut(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 0;
        }

        $httpResponse = json_encode([
            'yearmonth_2024_6' => $seats,
            'yearmonth_2024_6_ex' => [],
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => [
                'timeslots' => [
                    ['id' => '900', 'label' => '9:00 AM Departure', 'available' => 6],
                    ['id' => '1300', 'label' => '1:00 PM Departure', 'available' => 2],
                ],
            ],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-06-15',
            ['345' => 2]
        );

        self::assertSame('sold_out', $result['metadata']['selectedDateStatus']);
        self::assertSame('available', $result['metadata']['timeslotStatus']);

        $timeslots = $result['timeslots'];
        self::assertCount(2, $timeslots);
        self::assertSame('900', $timeslots[0]->getId());
        self::assertSame('1300', $timeslots[1]->getId());
    }

    public function testCalendarTreatsDayAsUnavailableWhenSeatsBelowRequestedCount(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = $day === 15 ? 3 : 12;
        }

        $extended = [
            'd15' => [
                'times' => [369 => '8:00am Check In'],
                'activities' => [[
                    'activityId' => 369,
                    'available' => true,
                    'remaining' => 3,
                    'details' => ['times' => '8:00am Check In'],
                ]],
            ],
        ];

        $httpResponse = json_encode([
            'yearmonth_2024_3' => $seats,
            'yearmonth_2024_3_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-03-15',
            ['345' => 4],
            [369],
            '2024-03'
        );

        $calendarEntries = $result['calendar']->toArray();
        $dayEntry = null;
        foreach ($calendarEntries as $entry) {
            if ($entry['date'] === '2024-03-15') {
                $dayEntry = $entry;
                break;
            }
        }

        self::assertNotNull($dayEntry);
        self::assertSame('sold_out', $dayEntry['status']);
        self::assertSame('sold_out', $result['metadata']['selectedDateStatus']);
        self::assertSame('unavailable', $result['metadata']['timeslotStatus']);
        self::assertSame([], $result['timeslots']);
    }

    public function testTimeslotsBelowRequestedSeatsAreFilteredFromSoapResults(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 12;
        }

        $httpResponse = json_encode([
            'yearmonth_2024_7' => $seats,
            'yearmonth_2024_7_ex' => [],
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => [
                'timeslots' => [
                    ['id' => '800', 'label' => '8:00 AM Departure', 'available' => 3],
                    ['id' => '1300', 'label' => '1:00 PM Departure', 'available' => 6],
                ],
            ],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-07-15',
            ['345' => 4],
            [369],
            '2024-07'
        );

        $timeslots = $result['timeslots'];
        self::assertCount(1, $timeslots);
        self::assertSame('1300', $timeslots[0]->getId());
        self::assertSame('1:00 PM Departure', $timeslots[0]->getLabel());
        self::assertSame('available', $result['metadata']['timeslotStatus']);
    }

    public function testFetchCalendarHandlesNestedStdClassTimeslotResponse(): void
    {
        $seats = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 12;
        }

        $httpResponse = json_encode([
            'yearmonth_2024_9' => $seats,
            'yearmonth_2024_9_ex' => [],
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $client = new AvailabilityRecordingSoapClient([
            'getActivityTimeslots' => static fn () => [
                'timeslots' => [
                    (object) [
                        'id' => '930',
                        'label' => 'Departure 930',
                        'availableSpots' => '5',
                        'details' => (object) [
                            'times' => (object) [
                                'provided' => '9:30am Check In',
                                'display' => '9:30 AM Check-In',
                            ],
                        ],
                    ],
                ],
            ],
        ]);
        $factory = new AvailabilityStubSoapClientFactory($client);

        $service = new AvailabilityService($factory, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-09-15',
            ['345' => 2],
            [369],
            '2024-09'
        );

        $timeslots = $result['timeslots'];
        self::assertCount(1, $timeslots);
        self::assertSame('930', $timeslots[0]->getId());
        self::assertSame('9:30am Check In', $timeslots[0]->getLabel());
        self::assertSame(['times' => '9:30am Check In'], $timeslots[0]->getDetails());
        self::assertSame(5, $timeslots[0]->getAvailable());
    }

    public function testMonthAvailabilityIsRetrievedFromCacheWhenFresh(): void
    {
        $httpCalls = [];
        $httpFetcher = function (string $url, array $params) use (&$httpCalls): string {
            $httpCalls[] = $params;

            $yearMonth = (string) ($params['year_months'] ?? '2024_1');
            if (!preg_match('/^(\d{4})_(\d{1,2})$/', $yearMonth, $matches)) {
                throw new RuntimeException('Unexpected year_months parameter: ' . $yearMonth);
            }

            $year = (int) $matches[1];
            $month = (int) $matches[2];

            $seats = [];
            for ($day = 1; $day <= 31; $day++) {
                $seats['d' . $day] = $month === 3 && $day === 10 ? 12 : 0;
            }

            $extended = [];
            if ($month === 3) {
                $extended['d10'] = [
                    'times' => [369 => '10:00am Check In'],
                    'activities' => [[
                        'activityId' => 369,
                        'available' => true,
                        'remaining' => 12,
                        'details' => ['times' => '10:00am Check In'],
                    ]],
                ];
            }

            return json_encode([
                'yearmonth_' . $year . '_' . $month => $seats,
                'yearmonth_' . $year . '_' . $month . '_ex' => $extended,
            ], JSON_THROW_ON_ERROR);
        };

        $client = new AvailabilityRecordingSoapClient([]);
        $factory = new AvailabilityStubSoapClientFactory($client);
        $cache = new AvailabilityCacheStub();

        $service = new AvailabilityService($factory, $httpFetcher, $cache);

        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-03-10',
            ['345' => 2],
            [369],
            '2024-03'
        );

        self::assertSame('available', $result['metadata']['selectedDateStatus']);
        self::assertSame('available', $result['metadata']['timeslotStatus']);
        self::assertCount(1, $result['timeslots']);
        self::assertSame(6, count($httpCalls));

        $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-03-10',
            ['345' => 2],
            [369],
            '2024-03'
        );

        self::assertSame(6, count($httpCalls));
    }

    public function testMonthAvailabilityCacheExpiresAfterTtl(): void
    {
        $httpCalls = [];
        $httpFetcher = function (string $url, array $params) use (&$httpCalls): string {
            $httpCalls[] = $params;

            $yearMonth = (string) ($params['year_months'] ?? '2024_1');
            if (!preg_match('/^(\d{4})_(\d{1,2})$/', $yearMonth, $matches)) {
                throw new RuntimeException('Unexpected year_months parameter: ' . $yearMonth);
            }

            $year = (int) $matches[1];
            $month = (int) $matches[2];

            $seats = [];
            for ($day = 1; $day <= 31; $day++) {
                $seats['d' . $day] = $month === 3 && $day === 10 ? 12 : 0;
            }

            $extended = [];
            if ($month === 3) {
                $extended['d10'] = [
                    'times' => [369 => '10:00am Check In'],
                    'activities' => [[
                        'activityId' => 369,
                        'available' => true,
                        'remaining' => 12,
                        'details' => ['times' => '10:00am Check In'],
                    ]],
                ];
            }

            return json_encode([
                'yearmonth_' . $year . '_' . $month => $seats,
                'yearmonth_' . $year . '_' . $month . '_ex' => $extended,
            ], JSON_THROW_ON_ERROR);
        };

        $client = new AvailabilityRecordingSoapClient([]);
        $factory = new AvailabilityStubSoapClientFactory($client);
        $cache = new AvailabilityCacheStub();

        $service = new AvailabilityService($factory, $httpFetcher, $cache);

        $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-03-10',
            ['345' => 2],
            [369],
            '2024-03'
        );

        self::assertSame(6, count($httpCalls));

        $cache->advanceTime(181);

        $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-03-10',
            ['345' => 2],
            [369],
            '2024-03'
        );

        self::assertSame(12, count($httpCalls));
    }

    public function testRequestingNewMonthExtendsCacheWindow(): void
    {
        $httpCalls = [];
        $httpFetcher = function (string $url, array $params) use (&$httpCalls): string {
            $httpCalls[] = $params;

            $yearMonth = (string) ($params['year_months'] ?? '2024_1');
            if (!preg_match('/^(\d{4})_(\d{1,2})$/', $yearMonth, $matches)) {
                throw new RuntimeException('Unexpected year_months parameter: ' . $yearMonth);
            }

            $year = (int) $matches[1];
            $month = (int) $matches[2];

            $seats = [];
            for ($day = 1; $day <= 31; $day++) {
                $seats['d' . $day] = $day === 10 ? 6 : 0;
            }

            $extended = [
                'd10' => [
                    'times' => [369 => '10:00am Check In'],
                    'activities' => [[
                        'activityId' => 369,
                        'available' => true,
                        'remaining' => 6,
                        'details' => ['times' => '10:00am Check In'],
                    ]],
                ],
            ];

            return json_encode([
                'yearmonth_' . $year . '_' . $month => $seats,
                'yearmonth_' . $year . '_' . $month . '_ex' => $extended,
            ], JSON_THROW_ON_ERROR);
        };

        $client = new AvailabilityRecordingSoapClient([]);
        $factory = new AvailabilityStubSoapClientFactory($client);
        $cache = new AvailabilityCacheStub();

        $service = new AvailabilityService($factory, $httpFetcher, $cache);

        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-03-10',
            ['345' => 2],
            [369],
            '2024-03'
        );

        self::assertSame('available', $result['metadata']['selectedDateStatus']);
        self::assertSame(6, count($httpCalls));

        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-09-10',
            ['345' => 2],
            [369],
            '2024-09'
        );

        self::assertSame('available', $result['metadata']['selectedDateStatus']);
        self::assertSame(12, count($httpCalls));

        $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-04-10',
            ['345' => 2],
            [369],
            '2024-04'
        );

        self::assertSame(12, count($httpCalls));
    }
}

final class AvailabilityStubSoapClientFactory implements SoapClientFactory
{
    public int $buildCount = 0;

    public function __construct(private SoapClient $client)
    {
    }

    public function build(): SoapClient
    {
        $this->buildCount++;
        return $this->client;
    }
}

final class AvailabilityCacheStub implements CacheInterface
{
    /** @var array<string, array{value:mixed, expires_at:?int}> */
    private array $store = [];

    /** @var list<array{key:string,ttl:int}> */
    public array $setCalls = [];

    private int $now;

    public function __construct()
    {
        $this->now = 0;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!isset($this->store[$key])) {
            return $default;
        }

        $item = $this->store[$key];
        $expiresAt = $item['expires_at'];
        if ($expiresAt !== null && $expiresAt <= $this->now) {
            unset($this->store[$key]);
            return $default;
        }

        return $item['value'];
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $expiresAt = $ttl > 0 ? $this->now + $ttl : null;
        $this->store[$key] = [
            'value' => $value,
            'expires_at' => $expiresAt,
        ];

        $this->setCalls[] = ['key' => $key, 'ttl' => $ttl];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    public function advanceTime(int $seconds): void
    {
        $this->now += max(0, $seconds);
    }
}

final class AvailabilityRecordingSoapClient extends SoapClient
{
    /** @var array<string, callable> */
    private array $handlers;

    /** @var list<array{0:string,1:array}> */
    public array $calls = [];

    /**
     * @param array<string, callable> $handlers
     */
    public function __construct(array $handlers)
    {
        $this->handlers = $handlers;
        // Skip parent constructor; only dynamic dispatch is needed for tests.
    }

    public function __soapCall(string $name, array $arguments, ?array $options = null, mixed $inputHeaders = null, mixed &$outputHeaders = null): mixed
    {
        $this->calls[] = [$name, $arguments];

        if (isset($this->handlers[$name])) {
            return ($this->handlers[$name])($arguments);
        }

        throw new RuntimeException(sprintf('Unexpected SOAP call to "%s".', $name));
    }
}
