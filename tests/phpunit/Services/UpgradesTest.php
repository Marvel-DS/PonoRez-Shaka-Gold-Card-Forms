<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\DTO\Upgrade;
use PonoRez\SGCForms\DTO\UpgradeCollection;
use PonoRez\SGCForms\Services\SoapClientFactory;
use PonoRez\SGCForms\Services\UpgradeService;
use RuntimeException;
use SoapClient;
use SoapFault;
use stdClass;
use Throwable;

final class UpgradesTest extends TestCase
{
    private const SUPPLIER_SLUG = 'supplier-slug';
    private const ACTIVITY_SLUG = 'activity-slug';

    public function testFetchReturnsCachedData(): void
    {
        $cacheKey = 'upgrades:supplier-slug:activity-slug';
        $cached = [
            [
                'id' => 'upgrade-cached',
                'label' => 'Cached Upgrade',
                'description' => 'Cached description',
                'price' => 12.5,
                'maxQuantity' => 3,
            ],
        ];

        $cache = new UpgradeCacheSpy([$cacheKey => $cached]);
        $factory = new UpgradeStubSoapClientFactory();

        $service = new UpgradeService($cache, $factory);
        $collection = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG);

        self::assertSame(0, $factory->buildCount);
        self::assertInstanceOf(UpgradeCollection::class, $collection);
        self::assertCount(1, $collection);
        $upgrade = $collection->get('upgrade-cached');
        self::assertInstanceOf(Upgrade::class, $upgrade);
        self::assertSame('Cached Upgrade', $upgrade->getLabel());
        self::assertSame(12.5, $upgrade->getPrice());
        self::assertSame(3, $upgrade->getMaxQuantity());
    }

    public function testFetchCachesSoapResponse(): void
    {
        $primary = new stdClass();
        $primary->upgradeId = 'upgrade-photos';
        $primary->name = 'Photo Package Updated';
        $primary->price = 59.25;
        $primary->description = 'Updated description';
        $primary->maxQuantity = 1;

        $extra = new stdClass();
        $extra->id = 'upgrade-snacks';
        $extra->label = 'Snack Pack';
        $extra->price = '15.00';
        $extra->maxQuantity = 5;

        $response = new stdClass();
        $response->return = [$primary, $extra];

        $client = new UpgradeRecordingSoapClient($response);
        $factory = new UpgradeStubSoapClientFactory($client);
        $cache = new UpgradeCacheSpy();

        $service = new UpgradeService($cache, $factory);
        $collection = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG);

        self::assertSame(1, $factory->buildCount);
        self::assertCount(1, $client->calls);

        [$method, $arguments] = $client->calls[0];
        self::assertSame('getActivityUpgrades', $method);
        $payload = $arguments[0];
        self::assertSame([
            'username' => 'apiUsername',
            'password' => 'apiPassword',
        ], $payload['serviceLogin']);
        self::assertSame(123, $payload['supplierId']);
        self::assertSame(369, $payload['activityId']);
        self::assertArrayHasKey('date', $payload);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', (string) $payload['date']);

        self::assertCount(2, $collection);
        $photos = $collection->get('upgrade-photos');
        self::assertInstanceOf(Upgrade::class, $photos);
        self::assertSame('Photo Package Updated', $photos->getLabel());
        self::assertSame(59.25, $photos->getPrice());

        $snacks = $collection->get('upgrade-snacks');
        self::assertInstanceOf(Upgrade::class, $snacks);
        self::assertSame('Snack Pack', $snacks->getLabel());
        self::assertSame(15.0, $snacks->getPrice());
        self::assertSame(5, $snacks->getMaxQuantity());

        self::assertNotEmpty($cache->setCalls);
        $write = $cache->setCalls[0];
        self::assertSame('upgrades:supplier-slug:activity-slug', $write['key']);
        self::assertSame(600, $write['ttl']);
    }

    public function testFetchSkipsDisabledConfigAndFallsBackOnSoapFault(): void
    {
        $fault = new SoapFault('Server', 'Issue');
        $client = new UpgradeRecordingSoapClient(null, $fault);
        $factory = new UpgradeStubSoapClientFactory($client);
        $cache = new UpgradeCacheSpy();

        $service = new UpgradeService($cache, $factory);
        $collection = $service->fetch(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG);

        self::assertSame(1, $factory->buildCount);
        self::assertSame([], $cache->setCalls);
        self::assertCount(1, $collection);
        self::assertNull($collection->get('upgrade-lunch'));
        self::assertNotNull($collection->get('upgrade-photos'));
    }
}

final class UpgradeCacheSpy implements CacheInterface
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

final class UpgradeStubSoapClientFactory implements SoapClientFactory
{
    public int $buildCount = 0;

    public function __construct(private ?SoapClient $client = null)
    {
    }

    public function build(): SoapClient
    {
        $this->buildCount++;

        if ($this->client === null) {
            throw new RuntimeException('Missing Upgrade soap client.');
        }

        return $this->client;
    }
}

final class UpgradeRecordingSoapClient extends SoapClient
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
