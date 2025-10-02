<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\Services\AvailabilityMessagingService;
use PonoRez\SGCForms\Services\SoapClientFactory;
use RuntimeException;
use SoapClient;

final class AvailabilityMessagingServiceTest extends TestCase
{
    private const SUPPLIER_SLUG = 'supplier-slug';
    private const ACTIVITY_SLUG = 'activity-slug';

    public function testProbeMarksUnavailableWhenBaselineFails(): void
    {
        $responses = [
            369 => [
                '2024-08-01' => [
                    2 => false,
                ],
            ],
        ];

        $client = new AvailabilityProbeSoapClient($responses);
        $factory = new AvailabilityProbeSoapClientFactory($client);

        $service = new AvailabilityMessagingService($factory);
        $result = $service->probeTimeslots(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            [
                ['activityId' => 369, 'date' => '2024-08-01'],
            ],
            ['345' => 2]
        );

        self::assertSame(2, $result['requestedSeats']);
        self::assertCount(1, $client->calls);
        self::assertCount(1, $result['messages']);

        $message = $result['messages'][0];
        self::assertSame('369', $message['activityId']);
        self::assertSame('2024-08-01', $message['date']);
        self::assertSame('unavailable', $message['tier']);
        self::assertSame(0, $message['seats']);
    }

    public function testProbeMarksPlentyWhenHigherTierSucceeds(): void
    {
        $responses = [
            369 => [
                '2024-08-02' => [
                    2 => true,
                    5 => true,
                ],
            ],
        ];

        $client = new AvailabilityProbeSoapClient($responses);
        $factory = new AvailabilityProbeSoapClientFactory($client);

        $service = new AvailabilityMessagingService($factory);
        $result = $service->probeTimeslots(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            [
                ['activityId' => 369, 'date' => '2024-08-02'],
            ],
            ['345' => 2]
        );

        self::assertSame(2, $result['requestedSeats']);
        self::assertCount(2, $client->calls);

        $message = $result['messages'][0];
        self::assertSame('plenty', $message['tier']);
        self::assertSame(5, $message['seats']);
    }

    public function testProbeFallsBackToLimitedWhenOnlyOneExtraSeat(): void
    {
        $responses = [
            369 => [
                '2024-08-03' => [
                    2 => true,
                    5 => false,
                    4 => false,
                    3 => true,
                ],
            ],
        ];

        $client = new AvailabilityProbeSoapClient($responses);
        $factory = new AvailabilityProbeSoapClientFactory($client);

        $service = new AvailabilityMessagingService($factory);
        $result = $service->probeTimeslots(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            [
                ['activityId' => 369, 'date' => '2024-08-03'],
            ],
            ['345' => 2]
        );

        self::assertSame(2, $result['requestedSeats']);
        self::assertCount(4, $client->calls);

        $message = $result['messages'][0];
        self::assertSame('limited', $message['tier']);
        self::assertSame(3, $message['seats']);
    }

    public function testProbeKeepsBaselineSeatsWhenNoAdditionalCapacity(): void
    {
        $responses = [
            369 => [
                '2024-08-04' => [
                    2 => true,
                    5 => false,
                    4 => false,
                    3 => false,
                ],
            ],
        ];

        $client = new AvailabilityProbeSoapClient($responses);
        $factory = new AvailabilityProbeSoapClientFactory($client);

        $service = new AvailabilityMessagingService($factory);
        $result = $service->probeTimeslots(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            [
                ['activityId' => 369, 'date' => '2024-08-04'],
            ],
            ['345' => 2]
        );

        self::assertSame(2, $result['requestedSeats']);
        self::assertCount(4, $client->calls);

        $message = $result['messages'][0];
        self::assertSame('limited', $message['tier']);
        self::assertSame(2, $message['seats']);
    }

    public function testDuplicateTimeslotsAreProbedOnce(): void
    {
        $responses = [
            369 => [
                '2024-08-05' => [
                    2 => true,
                    5 => false,
                    4 => false,
                    3 => true,
                ],
            ],
        ];

        $client = new AvailabilityProbeSoapClient($responses);
        $factory = new AvailabilityProbeSoapClientFactory($client);

        $service = new AvailabilityMessagingService($factory);
        $result = $service->probeTimeslots(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            [
                ['activityId' => 369, 'date' => '2024-08-05'],
                ['activityId' => '369', 'date' => '2024-08-05'],
                ['activityId' => 999, 'date' => '2024-08-05'],
            ],
            ['345' => 2]
        );

        // Baseline + three probes should be executed once.
        self::assertCount(4, $client->calls);
        self::assertCount(1, $result['messages']);

        $message = $result['messages'][0];
        self::assertSame('369', $message['activityId']);
        self::assertSame('limited', $message['tier']);
        self::assertSame(3, $message['seats']);
    }
}

final class AvailabilityProbeSoapClientFactory implements SoapClientFactory
{
    public function __construct(private SoapClient $client)
    {
    }

    public function build(): SoapClient
    {
        return $this->client;
    }
}

final class AvailabilityProbeSoapClient extends SoapClient
{
    /** @var array<int, array<string, array<int, bool>>> */
    private array $responses;

    /** @var list<array{0:string,1:array}> */
    public array $calls = [];

    /**
     * @param array<int, array<string, array<int, bool>>> $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
        // Skip parent constructor; the stub does not need WSDL handling.
    }

    public function __soapCall(string $name, array $arguments, ?array $options = null, mixed $inputHeaders = null, mixed &$outputHeaders = null): mixed
    {
        $this->calls[] = [$name, $arguments];

        if ($name !== 'checkActivityAvailability') {
            throw new RuntimeException(sprintf('Unexpected SOAP call to "%s".', $name));
        }

        $payload = $arguments[0] ?? [];
        $activityId = isset($payload['activityId']) ? (int) $payload['activityId'] : 0;
        $dateKey = $this->normaliseDateKey((string) ($payload['date'] ?? ''));
        $requested = isset($payload['requestedAvailability']) ? (int) $payload['requestedAvailability'] : 0;

        return $this->responses[$activityId][$dateKey][$requested] ?? false;
    }

    private function normaliseDateKey(string $date): string
    {
        if ($date === '') {
            return $date;
        }

        try {
            return (new \DateTimeImmutable($date))->format('Y-m-d');
        } catch (\Exception) {
            return $date;
        }
    }
}
