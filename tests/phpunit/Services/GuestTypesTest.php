<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\DTO\GuestType;
use PonoRez\SGCForms\DTO\GuestTypeCollection;
use PonoRez\SGCForms\Services\GuestTypeService;
use PonoRez\SGCForms\Services\SoapClientFactory;
use PonoRez\SGCForms\UtilityService;
use RuntimeException;
use SoapClient;
use SoapFault;
use stdClass;
use Throwable;

final class GuestTypesTest extends TestCase
{
    private const SUPPLIER_SLUG = 'supplier-slug';
    private const ACTIVITY_SLUG = 'activity-slug';
    private const TRAVEL_DATE = '2024-01-01';

    public function testFetchReturnsCachedDataWhenAvailable(): void
    {
        $cacheKey = 'guest-types:supplier-slug:activity-slug:2024-01-01';
        $cachedPayload = [[
            'guestTypeId' => 345,
            'name' => 'Adult from cache',
            'description' => 'Cached description',
            'price' => '123.45',
        ]];

        $cache = new CacheSpy([$cacheKey => $cachedPayload]);
        $builder = new StubSoapClientFactory();

        $service = new GuestTypeService($cache, $builder);
        $collection = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG, self::TRAVEL_DATE);

        self::assertSame(0, $builder->buildCount, 'SOAP client should not be built when cache hits.');
        self::assertInstanceOf(GuestTypeCollection::class, $collection);
        self::assertCount(2, $collection);

        $adult = $collection->get('345');
        self::assertInstanceOf(GuestType::class, $adult);
        self::assertSame('Adult from cache', $adult->getLabel());
        self::assertSame('Cached description', $adult->getDescription());
        self::assertSame(123.45, $adult->getPrice());
        self::assertSame(1, $adult->getMin());
        self::assertSame(10, $adult->getMax());

        $child = $collection->get('456');
        self::assertInstanceOf(GuestType::class, $child);
        self::assertSame('Child', $child->getLabel());
        self::assertSame('Ages 2-16', $child->getDescription());
        self::assertSame(75.0, $child->getPrice());
    }

    public function testFetchCachesSoapResponseOnMiss(): void
    {
        $responseRow = new stdClass();
        $responseRow->guestTypeId = 345;
        $responseRow->name = 'Adult from SOAP';
        $responseRow->description = 'SOAP description';
        $responseRow->price = '150.55';

        $responseExtra = new stdClass();
        $responseExtra->guestTypeId = 789;
        $responseExtra->name = 'Infant';
        $responseExtra->description = null;
        $responseExtra->price = 0;

        $soapResponse = new stdClass();
        $soapResponse->return = [$responseRow, $responseExtra];

        $client = new RecordingSoapClient($soapResponse);
        $builder = new StubSoapClientFactory($client);
        $cache = new CacheSpy();

        $service = new GuestTypeService($cache, $builder);
        $collection = $service->fetch(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            self::TRAVEL_DATE,
            ['345' => 2]
        );

        self::assertSame(1, $builder->buildCount);
        self::assertCount(1, $client->calls);

        [$method, $arguments] = $client->calls[0];
        self::assertSame('getActivityGuestTypes', $method);
        self::assertCount(1, $arguments);
        $payload = $arguments[0];

        self::assertSame([
            'serviceLogin' => [
                'username' => 'apiUsername',
                'password' => 'apiPassword',
            ],
            'supplierId' => 123,
            'activityId' => 369,
            'date' => self::TRAVEL_DATE,
            'guestCounts' => [[
                'guestTypeId' => 345,
                'guestCount' => 2,
            ]],
        ], $payload);

        self::assertCount(3, $collection);
        $adult = $collection->get('345');
        self::assertInstanceOf(GuestType::class, $adult);
        self::assertSame('Adult from SOAP', $adult->getLabel());
        self::assertSame('SOAP description', $adult->getDescription());
        self::assertSame(150.55, $adult->getPrice());

        $infant = $collection->get('789');
        self::assertInstanceOf(GuestType::class, $infant);
        self::assertSame('Infant', $infant->getLabel());

        $child = $collection->get('456');
        self::assertInstanceOf(GuestType::class, $child);
        self::assertSame('Child', $child->getLabel());

        self::assertNotEmpty($cache->setCalls);
        self::assertSame('guest-types:supplier-slug:activity-slug:2024-01-01', $cache->setCalls[0]['key']);
        self::assertSame(600, $cache->setCalls[0]['ttl']);
        self::assertSame('guest-types:supplier-slug:activity-slug:baseline', $cache->setCalls[1]['key']);
        self::assertSame(3600, $cache->setCalls[1]['ttl']);
        self::assertEquals([
            [
                'guestTypeId' => 345,
                'name' => 'Adult from SOAP',
                'description' => 'SOAP description',
                'price' => '150.55',
            ],
            [
                'guestTypeId' => 789,
                'name' => 'Infant',
                'description' => null,
                'price' => 0,
            ],
        ], $cache->setCalls[0]['value']);
        self::assertSame($cache->setCalls[0]['value'], $cache->setCalls[1]['value']);
    }

    public function testFetchFallsBackToBaselineCacheWhenDateCacheMisses(): void
    {
        $baselineKey = 'guest-types:supplier-slug:activity-slug:baseline';
        $cachedPayload = [[
            'guestTypeId' => 345,
            'name' => 'Adult from baseline',
            'description' => 'Baseline description',
            'price' => '99.99',
        ]];

        $cache = new CacheSpy([$baselineKey => $cachedPayload]);
        $builder = new StubSoapClientFactory();

        $service = new GuestTypeService($cache, $builder);
        $collection = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG, self::TRAVEL_DATE);

        self::assertSame(0, $builder->buildCount, 'SOAP client should not be built when baseline cache hits.');
        $adult = $collection->get('345');
        self::assertInstanceOf(GuestType::class, $adult);
        self::assertSame('Adult from baseline', $adult->getLabel());
        self::assertSame('Baseline description', $adult->getDescription());
        self::assertSame(99.99, $adult->getPrice());
    }

    public function testFetchFallsBackToSupplierCacheWhenSoapFails(): void
    {
        $supplierDir = UtilityService::supplierDirectory(self::SUPPLIER_SLUG);
        $cacheDir = $supplierDir . '/cache/guest-types';
        if (!is_dir($cacheDir) && !mkdir($cacheDir, 0775, true) && !is_dir($cacheDir)) {
            self::fail('Unable to create supplier guest type cache directory for test.');
        }

        $cacheFile = $cacheDir . '/' . self::ACTIVITY_SLUG . '.json';
        $payload = [[
            'id' => 345,
            'name' => 'Adult from supplier cache',
            'description' => 'Supplier cached description',
            'price' => 88.0,
        ]];

        file_put_contents($cacheFile, json_encode($payload, JSON_THROW_ON_ERROR));

        try {
            $cache = new CacheSpy();
            $builder = new StubSoapClientFactory(); // will throw when build() is called

            $service = new GuestTypeService($cache, $builder);
            $collection = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG, self::TRAVEL_DATE);

            $guest = $collection->get('345');
            self::assertInstanceOf(GuestType::class, $guest);
            self::assertSame('Adult from supplier cache', $guest->getLabel());
            self::assertSame('Supplier cached description', $guest->getDescription());
            self::assertSame(88.0, $guest->getPrice());
        } finally {
            @unlink($cacheFile);
        }
    }

    public function testFetchNormalizesSoapObjectResponse(): void
    {
        $soapRow = new stdClass();
        $soapRow->id = 456;
        $soapRow->name = 'Child from SOAP';
        $soapRow->description = 'Child desc';
        $soapRow->price = 50;

        $client = new RecordingSoapClient($soapRow);
        $builder = new StubSoapClientFactory($client);
        $cache = new CacheSpy();

        $service = new GuestTypeService($cache, $builder);
        $collection = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG, self::TRAVEL_DATE);

        $child = $collection->get('456');
        self::assertInstanceOf(GuestType::class, $child);
        self::assertSame('Child from SOAP', $child->getLabel());
        self::assertSame(50.0, $child->getPrice());
    }

    public function testFetchUsesAlternateLabelFieldsWhenNameMissing(): void
    {
        $soapRow = new stdClass();
        $soapRow->guestTypeId = 999;
        $soapRow->label = 'General Admission';
        $soapRow->guestTypeDescription = 'Label-provided description';

        $client = new RecordingSoapClient($soapRow);
        $builder = new StubSoapClientFactory($client);
        $cache = new CacheSpy();

        $service = new GuestTypeService($cache, $builder);
        $collection = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG, self::TRAVEL_DATE);

        $entry = $collection->get('999');
        self::assertInstanceOf(GuestType::class, $entry);
        self::assertSame('General Admission', $entry->getLabel());
        self::assertSame('Label-provided description', $entry->getDescription());
    }

    public function testFetchReturnsConfigWhenSoapThrows(): void
    {
        $fault = new SoapFault('Server', 'Failure');
        $client = new RecordingSoapClient(null, $fault);
        $builder = new StubSoapClientFactory($client);
        $cache = new CacheSpy();

        $service = new GuestTypeService($cache, $builder);
        $collection = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG, self::TRAVEL_DATE);

        self::assertCount(2, $collection);
        self::assertSame([], $cache->setCalls, 'Cache should not be written when SOAP fails.');
    }
}

final class CacheSpy implements CacheInterface
{
    /** @var array<string, mixed> */
    private array $primedValues;

    /** @var array<string, mixed> */
    private array $store = [];

    /** @var list<array{key:string,value:mixed,ttl:int}> */
    public array $setCalls = [];

    /** @var list<string> */
    public array $deleteCalls = [];

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
        $this->deleteCalls[] = $key;
    }
}

final class StubSoapClientFactory implements SoapClientFactory
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

final class RecordingSoapClient extends SoapClient
{
    /** @var list<array{0:string,1:array}> */
    public array $calls = [];

    public function __construct(private mixed $response, private ?Throwable $throwable = null)
    {
        // Intentionally bypass parent constructor.
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
