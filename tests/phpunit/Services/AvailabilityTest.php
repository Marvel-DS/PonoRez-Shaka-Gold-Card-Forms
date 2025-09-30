<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\DTO\AvailabilityCalendar;
use PonoRez\SGCForms\DTO\AvailabilityDay;
use PonoRez\SGCForms\DTO\Timeslot;
use PonoRez\SGCForms\Services\AvailabilityService;
use PonoRez\SGCForms\Services\SoapClientFactory;
use RuntimeException;
use SoapClient;
use SoapFault;
use stdClass;
use Throwable;

final class AvailabilityTest extends TestCase
{
    private const SUPPLIER_SLUG = 'supplier-slug';
    private const ACTIVITY_SLUG = 'activity-slug';
    private const START_DATE = '2024-01-01';

    public function testFetchCalendarReturnsHydratedCachedPayload(): void
    {
        $cacheKey = 'availability:supplier-slug:activity-slug:2024-01-01';
        $cachedPayload = [
            'calendar' => [
                ['date' => '2024-01-01', 'status' => 'available'],
                ['date' => '2024-01-02', 'status' => 'sold-out'],
            ],
            'timeslots' => [
                ['id' => 'am', 'label' => 'Morning', 'available' => 5],
                ['id' => 'pm', 'label' => 'Afternoon', 'available' => null],
            ],
        ];

        $cache = new AvailabilityCacheSpy([$cacheKey => $cachedPayload]);
        $builder = new AvailabilityStubSoapClientFactory();

        $service = new AvailabilityService($cache, $builder);
        $result = $service->fetchCalendar(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG, self::START_DATE);

        self::assertSame(0, $builder->buildCount, 'SOAP client should not be built on cache hit.');
        self::assertArrayHasKey('calendar', $result);
        self::assertArrayHasKey('timeslots', $result);
        self::assertInstanceOf(AvailabilityCalendar::class, $result['calendar']);
        self::assertCount(2, $result['calendar']);
        self::assertContainsOnlyInstancesOf(AvailabilityDay::class, $result['calendar']->all());

        self::assertIsArray($result['timeslots']);
        self::assertCount(2, $result['timeslots']);
        self::assertContainsOnlyInstancesOf(Timeslot::class, $result['timeslots']);

        $morning = $result['timeslots'][0];
        self::assertSame('am', $morning->getId());
        self::assertSame('Morning', $morning->getLabel());
        self::assertSame(5, $morning->getAvailable());

        $afternoon = $result['timeslots'][1];
        self::assertNull($afternoon->getAvailable());
    }

    public function testFetchCalendarCachesSoapResponse(): void
    {
        $primary = new stdClass();
        $primary->date = '2024-01-01';
        $primary->status = 'Available';

        $secondary = new stdClass();
        $secondary->date = '2024-01-02';
        $secondary->status = 'SoldOut';

        $response = new stdClass();
        $response->return = [$primary, $secondary];

        $client = new AvailabilityRecordingSoapClient($response);
        $factory = new AvailabilityStubSoapClientFactory($client);
        $cache = new AvailabilityCacheSpy();

        $service = new AvailabilityService($cache, $factory);
        $result = $service->fetchCalendar(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG, self::START_DATE);

        self::assertSame(1, $factory->buildCount);
        self::assertCount(1, $client->calls);

        [$method, $arguments] = $client->calls[0];
        self::assertSame('getActivityAvailableDates', $method);
        $this->assertPayloadMatches($arguments);

        self::assertArrayHasKey('calendar', $result);
        self::assertArrayHasKey('timeslots', $result);
        self::assertSame([
            'fallback' => false,
            'source' => 'soap',
        ], $result['metadata']);
        self::assertInstanceOf(AvailabilityCalendar::class, $result['calendar']);
        self::assertCount(2, $result['calendar']);
        self::assertSame('available', $result['calendar']->all()[0]->getStatus());
        self::assertSame('soldout', $result['calendar']->all()[1]->getStatus());
        self::assertSame([], $result['timeslots']);

        self::assertNotEmpty($cache->setCalls);
        $write = $cache->setCalls[0];
        self::assertSame('availability:supplier-slug:activity-slug:2024-01-01', $write['key']);
        self::assertSame(300, $write['ttl']);
        self::assertEquals([
            'calendar' => [
                ['date' => '2024-01-01', 'status' => 'available'],
                ['date' => '2024-01-02', 'status' => 'soldout'],
            ],
            'timeslots' => [],
            'metadata' => [
                'fallback' => false,
                'source' => 'soap',
            ],
        ], $write['value']);
    }

    public function testFetchCalendarSeedsFallbackOnSoapFault(): void
    {
        $fault = new SoapFault('Server', 'Failure');
        $client = new AvailabilityRecordingSoapClient(null, $fault);
        $factory = new AvailabilityStubSoapClientFactory($client);
        $cache = new AvailabilityCacheSpy();

        $service = new AvailabilityService($cache, $factory);
        $result = $service->fetchCalendar(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG, self::START_DATE);

        self::assertCount(15, $result['calendar']);
        foreach ($result['calendar']->all() as $day) {
            self::assertSame('unknown', $day->getStatus());
        }
        self::assertSame([], $result['timeslots']);
        self::assertSame([
            'fallback' => true,
            'source' => 'fallback',
            'error' => 'Failure',
        ], $result['metadata']);
        self::assertNotEmpty($cache->setCalls);
    }

    /**
     * @param list<array> $arguments
     */
    private function assertPayloadMatches(array $arguments): void
    {
        self::assertCount(1, $arguments);
        $payload = $arguments[0];
        self::assertSame([
            'serviceLogin' => [
                'username' => 'apiUsername',
                'password' => 'apiPassword',
            ],
            'supplierId' => 123,
            'activityId' => 369,
            'startDate' => self::START_DATE,
        ], $payload);
    }
}

final class AvailabilityCacheSpy implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $primedValues;

    /** @var array<string, mixed> */
    private array $store = [];

    /** @var list<array{key:string,value:mixed,ttl:int}> */
    public array $setCalls = [];

    /** @param array<string, mixed> $primedValues */
    public function __construct(array $primedValues = [])
    {
        $this->primedValues = $primedValues;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->store)) {
            return $this->store[$key];
        }

        return $this->primedValues[$key] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $this->store[$key] = $value;
        $this->setCalls[] = [
            'key' => $key,
            'value' => $value,
            'ttl' => $ttl,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->primedValues[$key], $this->store[$key]);
    }
}

final class AvailabilityStubSoapClientFactory implements SoapClientFactory
{
    public int $buildCount = 0;

    public function __construct(private ?SoapClient $client = null)
    {
    }

    public function build(): SoapClient
    {
        $this->buildCount++;

        if ($this->client === null) {
            throw new RuntimeException('No SoapClient stub provided.');
        }

        return $this->client;
    }
}

final class AvailabilityRecordingSoapClient extends SoapClient
{
    /** @var list<array{0:string,1:array}> */
    public array $calls = [];

    public function __construct(private mixed $response, private ?Throwable $throwable = null)
    {
        // Intentionally skip parent constructor call.
    }

    public function __soapCall(string $name, array $arguments, ?array $options = null, mixed $inputHeaders = null, mixed &$outputHeaders = null): mixed
    {
        $this->calls[] = [$name, $arguments];

        if ($this->throwable !== null) {
            throw $this->throwable;
        }

        return $this->response;
    }
}
