<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\DTO\TransportationRoute;
use PonoRez\SGCForms\DTO\TransportationSet;
use PonoRez\SGCForms\Services\SoapClientFactory;
use PonoRez\SGCForms\Services\TransportationService;
use RuntimeException;
use SoapClient;
use SoapFault;
use stdClass;
use Throwable;

final class TransportationTest extends TestCase
{
    private const SUPPLIER_SLUG = 'supplier-slug';
    private const ACTIVITY_SLUG = 'activity-slug';

    public function testFetchReturnsCachedData(): void
    {
        $cacheKey = 'transportation:supplier-slug:activity-slug';
        $cached = [
            'mandatory' => true,
            'defaultRouteId' => 'route-cached',
            'routes' => [
                [
                    'id' => 'route-cached',
                    'label' => 'Cached Route',
                    'description' => 'Cached description',
                    'price' => 25.0,
                ],
            ],
        ];

        $cache = new TransportCacheSpy([$cacheKey => $cached]);
        $factory = new TransportStubSoapClientFactory();

        $service = new TransportationService($cache, $factory);
        $set = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG);

        self::assertSame(0, $factory->buildCount);
        self::assertTrue($set->isMandatory());
        self::assertSame('route-cached', $set->getDefaultRouteId());
        self::assertCount(1, $set);
        $route = $set->getRoute('route-cached');
        self::assertInstanceOf(TransportationRoute::class, $route);
        self::assertSame('Cached Route', $route->getLabel());
        self::assertSame(25.0, $route->getPrice());
    }

    public function testFetchCachesSoapResponse(): void
    {
        $soapRow = new stdClass();
        $soapRow->routeId = 'route-shuttle';
        $soapRow->name = 'Waikiki Shuttle Updated';
        $soapRow->description = 'Updated description';
        $soapRow->price = 38;
        $soapRow->capacity = 18;
        $soapRow->isDefault = true;

        $extraRow = new stdClass();
        $extraRow->id = 'route-airport';
        $extraRow->label = 'Airport Transfer';
        $extraRow->price = '50.00';

        $response = new stdClass();
        $response->return = [$soapRow, $extraRow];

        $client = new TransportRecordingSoapClient($response);
        $factory = new TransportStubSoapClientFactory($client);
        $cache = new TransportCacheSpy();

        $service = new TransportationService($cache, $factory);
        $set = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG);

        self::assertSame(1, $factory->buildCount);
        self::assertCount(1, $client->calls);

        [$method, $arguments] = $client->calls[0];
        self::assertSame('getActivityTransportationRoutes', $method);
        self::assertCount(1, $arguments);
        $payload = $arguments[0];
        self::assertSame([
            'serviceLogin' => [
                'username' => 'apiUsername',
                'password' => 'apiPassword',
            ],
            'supplierId' => 123,
            'activityId' => 369,
        ], $payload);

        self::assertSame('route-shuttle', $set->getDefaultRouteId());
        self::assertCount(3, $set);
        $shuttle = $set->getRoute('route-shuttle');
        self::assertInstanceOf(TransportationRoute::class, $shuttle);
        self::assertSame('Waikiki Shuttle Updated', $shuttle->getLabel());
        self::assertSame(38.0, $shuttle->getPrice());
        self::assertSame(18, $shuttle->getCapacity());

        $airport = $set->getRoute('route-airport');
        self::assertInstanceOf(TransportationRoute::class, $airport);
        self::assertSame('Airport Transfer', $airport->getLabel());
        self::assertSame(50.0, $airport->getPrice());

        self::assertNotEmpty($cache->setCalls);
        $write = $cache->setCalls[0];
        self::assertSame('transportation:supplier-slug:activity-slug', $write['key']);
        self::assertSame(600, $write['ttl']);
        self::assertIsArray($write['value']);
    }

    public function testFetchFallsBackToConfigWhenSoapFails(): void
    {
        $fault = new SoapFault('Server', 'Error');
        $client = new TransportRecordingSoapClient(null, $fault);
        $factory = new TransportStubSoapClientFactory($client);
        $cache = new TransportCacheSpy();

        $service = new TransportationService($cache, $factory);
        $set = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG);

        self::assertSame(1, $factory->buildCount);
        self::assertSame([], $cache->setCalls);
        self::assertSame('route-shuttle', $set->getDefaultRouteId());
        self::assertCount(2, $set);
    }
}

final class TransportCacheSpy implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $primed;

    /** @var array<string, mixed> */
    private array $store = [];

    /** @var list<array{key:string,value:mixed,ttl:int}> */
    public array $setCalls = [];

    /** @param array<string, mixed> $primed */
    public function __construct(array $primed = [])
    {
        $this->primed = $primed;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->store)) {
            return $this->store[$key];
        }

        return $this->primed[$key] ?? $default;
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
        unset($this->primed[$key], $this->store[$key]);
    }
}

final class TransportStubSoapClientFactory implements SoapClientFactory
{
    public int $buildCount = 0;

    public function __construct(private ?SoapClient $client = null)
    {
    }

    public function build(): SoapClient
    {
        $this->buildCount++;

        if ($this->client === null) {
            throw new RuntimeException('Missing Transport soap client.');
        }

        return $this->client;
    }
}

final class TransportRecordingSoapClient extends SoapClient
{
    /** @var list<array{0:string,1:array}> */
    public array $calls = [];

    public function __construct(private mixed $response, private ?Throwable $throwable = null)
    {
        // Skip parent constructor.
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
