<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\DTO\AvailabilityDay;
use PonoRez\SGCForms\DTO\Timeslot;
use PonoRez\SGCForms\Services\AvailabilityService;

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
            $extended['d' . $day] = ['aids' => [369, 482, 999]];
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

        $service = new AvailabilityService(null, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-03-15',
            ['345' => 2],
            [369, 482, 777],
            '2024-03'
        );

        self::assertSame('COMMON_AVAILABILITYCHECKJSON', $capturedParams['action']);
        self::assertSame('369|482|777', $capturedParams['activityid']);
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
        self::assertSame('369', $timeslots[0]->getId());
        self::assertSame('482', $timeslots[1]->getId());

        $metadata = $result['metadata'];
        self::assertSame('ponorez-json', $metadata['source']);
        self::assertSame(2, $metadata['requestedSeats']);
        self::assertSame('available', $metadata['selectedDateStatus']);
        self::assertSame('available', $metadata['timeslotStatus']);
        self::assertSame('2024-03-01', $metadata['firstAvailableDate']);
        self::assertSame('verified', $metadata['certificateVerification']);
        self::assertSame([369, 482], $metadata['extended']['2024-03-15']);
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

        $service = new AvailabilityService(null, $httpFetcher);
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

        $service = new AvailabilityService(null, $httpFetcher);
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
    }

    public function testFetchCalendarReturnsTimeslotsWhenExtendedShowsAvailability(): void
    {
        $seats = [];
        $extended = [];
        for ($day = 1; $day <= 31; $day++) {
            $seats['d' . $day] = 0;
            $extended['d' . $day] = ['aids' => [369]];
        }

        $httpResponse = json_encode([
            'yearmonth_2024_6' => $seats,
            'yearmonth_2024_6_ex' => $extended,
        ], JSON_THROW_ON_ERROR);

        $httpFetcher = fn () => $httpResponse;

        $service = new AvailabilityService(null, $httpFetcher);
        $result = $service->fetchCalendar(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-06-15',
            ['345' => 2]
        );

        self::assertSame('available', $result['metadata']['selectedDateStatus']);
        self::assertSame('available', $result['metadata']['timeslotStatus']);

        $timeslots = $result['timeslots'];
        self::assertCount(1, $timeslots);
        self::assertSame('369', $timeslots[0]->getId());
    }
}
