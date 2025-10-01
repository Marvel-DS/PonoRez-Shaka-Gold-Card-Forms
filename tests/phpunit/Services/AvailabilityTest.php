<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
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
            $extended['d' . $day] = ['aids' => [369]];
        }

        $httpResponse = json_encode([
            'yearmonth_2024_3' => $seats,
            'yearmonth_2024_3_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $capturedParams = null;
        $httpFetcher = function (string $url, array $params) use (&$capturedParams, $httpResponse): string {
            $capturedParams = $params;
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

        self::assertSame('COMMON_AVAILABILITYCHECKJSON', $capturedParams['action']);
        self::assertSame('369', $capturedParams['activityid']);
        self::assertSame('2024_3', $capturedParams['year_months']);
        self::assertNotEmpty($capturedParams['minavailability']);

        $calendar = $result['calendar'];
        self::assertSame(31, $calendar->count());
        foreach ($calendar->all() as $day) {
            self::assertInstanceOf(AvailabilityDay::class, $day);
            self::assertSame('available', $day->getStatus());
        }

        $timeslots = $result['timeslots'];
        self::assertCount(2, $timeslots);
        self::assertContainsOnlyInstancesOf(Timeslot::class, $timeslots);
        self::assertSame('730', $timeslots[0]->getId());

        $metadata = $result['metadata'];
        self::assertSame('ponorez-json', $metadata['source']);
        self::assertSame(2, $metadata['requestedSeats']);
        self::assertSame('available', $metadata['selectedDateStatus']);
        self::assertSame('available', $metadata['timeslotStatus']);
        self::assertSame('2024-03-01', $metadata['firstAvailableDate']);
        self::assertSame('verified', $metadata['certificateVerification']);
        self::assertSame([369], $metadata['extended']['2024-03-15']);

        self::assertNotEmpty($client->calls);
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
        self::assertSame([369, 555], $result['metadata']['extended']['2024-07-12']);
        self::assertSame([], $result['timeslots']);
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
