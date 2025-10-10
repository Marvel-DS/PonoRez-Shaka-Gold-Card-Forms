<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\DTO\Upgrade;
use PonoRez\SGCForms\DTO\UpgradeCollection;
use PonoRez\SGCForms\Services\SoapClientFactory;
use PonoRez\SGCForms\Services\UpgradeService;
use PonoRez\SGCForms\UtilityService;
use ReflectionMethod;
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

        $video = new stdClass();
        $video->upgradeId = 'upgrade-video';
        $video->name = 'Video Package Deluxe';
        $video->description = 'High-def video package';
        $video->price = '89.99';
        $video->maxQuantity = 2;

        $response = new stdClass();
        $response->return = [$primary, $extra, $video];

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

        self::assertCount(3, $collection);
        $photos = $collection->get('upgrade-photos');
        self::assertInstanceOf(Upgrade::class, $photos);
        self::assertSame('Photo Package', $photos->getLabel());
        self::assertSame('Digital photo bundle', $photos->getDescription());
        self::assertSame(49.99, $photos->getPrice());

        $snacks = $collection->get('upgrade-snacks');
        self::assertInstanceOf(Upgrade::class, $snacks);
        self::assertSame('Snack Pack', $snacks->getLabel());
        self::assertSame(15.0, $snacks->getPrice());
        self::assertSame(5, $snacks->getMaxQuantity());

        $videoUpgrade = $collection->get('upgrade-video');
        self::assertInstanceOf(Upgrade::class, $videoUpgrade);
        self::assertSame('Video Package', $videoUpgrade->getLabel());
        self::assertSame('High-def video package', $videoUpgrade->getDescription());
        self::assertSame(89.99, $videoUpgrade->getPrice());
        self::assertSame(2, $videoUpgrade->getMaxQuantity());

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
        self::assertCount(2, $collection);
        self::assertNull($collection->get('upgrade-lunch'));
        self::assertNotNull($collection->get('upgrade-photos'));
        self::assertNotNull($collection->get('upgrade-video'));
    }

    public function testFetchReturnsEmptyCollectionWhenDisabled(): void
    {
        $factory = new UpgradeStubSoapClientFactory();
        $cache = new UpgradeCacheSpy();

        $service = new UpgradeService($cache, $factory);
        $projectRoot = UtilityService::projectRoot();
        $basePath = sprintf('%s/suppliers/%s/activity-slug.config', $projectRoot, self::SUPPLIER_SLUG);
        $fixturePath = sprintf('%s/suppliers/%s/activity-upgrades-disabled.config', $projectRoot, self::SUPPLIER_SLUG);

        $baseConfigContents = file_get_contents($basePath);
        self::assertNotFalse($baseConfigContents, 'Expected base activity config to be readable.');

        $config = json_decode($baseConfigContents, true, 512, JSON_THROW_ON_ERROR);
        $config['disableUpgrades'] = true;

        file_put_contents(
            $fixturePath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        try {
            $collection = $service->fetch(self::SUPPLIER_SLUG, 'activity-upgrades-disabled');

            self::assertSame(0, $factory->buildCount);
            self::assertCount(0, $collection);
        } finally {
            @unlink($fixturePath);
        }
    }

    public function testMergeCollectionPreservesConfiguredQuantitiesAndBackfillsMissingValues(): void
    {
        $collection = new UpgradeCollection();
        $configured = new Upgrade('upgrade-config', 'Configured Upgrade', [
            'maxQuantity' => 2,
            'minQuantity' => 1,
        ]);
        $collection->add($configured);

        $missingQuantities = new Upgrade('upgrade-missing', 'Missing Quantities');
        $collection->add($missingQuantities);

        $soapData = [
            [
                'upgradeId' => 'upgrade-config',
                'name' => 'Configured Upgrade',
                'maxQuantity' => 5,
                'minQuantity' => 0,
            ],
            [
                'id' => 'upgrade-missing',
                'label' => 'Missing Quantities',
                'maxQuantity' => 4,
                'minQuantity' => 3,
            ],
        ];

        $service = new UpgradeService(new UpgradeCacheSpy(), new UpgradeStubSoapClientFactory());
        $mergeCollection = new ReflectionMethod($service, 'mergeCollection');
        $mergeCollection->setAccessible(true);
        $mergeCollection->invoke($service, $collection, $soapData, []);

        $configuredUpgrade = $collection->get('upgrade-config');
        self::assertInstanceOf(Upgrade::class, $configuredUpgrade);
        self::assertSame(2, $configuredUpgrade->getMaxQuantity());
        self::assertSame(1, $configuredUpgrade->getMinQuantity());

        $backfilledUpgrade = $collection->get('upgrade-missing');
        self::assertInstanceOf(Upgrade::class, $backfilledUpgrade);
        self::assertSame(4, $backfilledUpgrade->getMaxQuantity());
        self::assertSame(3, $backfilledUpgrade->getMinQuantity());
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
