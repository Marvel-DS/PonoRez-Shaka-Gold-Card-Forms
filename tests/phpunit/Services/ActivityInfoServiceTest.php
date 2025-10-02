<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\Services\ActivityInfoService;
use PonoRez\SGCForms\Services\SoapClientFactory;
use PonoRez\SGCForms\UtilityService;
use RuntimeException;
use SoapClient;

final class ActivityInfoServiceTest extends TestCase
{
    private const SUPPLIER_SLUG = 'supplier-slug';
    private const ACTIVITY_SLUG = 'activity-info-test';

    private string $configPath;

    protected function setUp(): void
    {
        parent::setUp();

        $supplierDir = UtilityService::supplierDirectory(self::SUPPLIER_SLUG);
        if (!is_dir($supplierDir)) {
            $this->markTestSkipped('Supplier fixtures not available.');
        }

        $this->configPath = $supplierDir . '/' . self::ACTIVITY_SLUG . '.config';

        $config = [
            'activityId' => 369,
            'activityIds' => [369, 555],
        ];

        file_put_contents($this->configPath, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    protected function tearDown(): void
    {
        if (isset($this->configPath) && file_exists($this->configPath)) {
            @unlink($this->configPath);
        }

        parent::tearDown();
    }

    public function testFetchesAllActivitiesAndCachesResult(): void
    {
        $responses = [
            369 => [
                'name' => 'Morning Tour',
                'island' => 'Kauai',
                'times' => '8:00 AM Departure',
                'description' => "Line one\nLine two",
                'notes' => 'Remember sunscreen',
                'directions' => 'Harbor entrance',
            ],
            555 => [
                'name' => 'Afternoon Tour',
                'times' => '1:00 PM Departure',
            ],
        ];

        $cache = new InMemoryCache();
        $client = new ActivityInfoRecordingSoapClient($responses);
        $factory = new ActivityInfoStubSoapClientFactory($client);

        $service = new ActivityInfoService($cache, $factory);
        $result = $service->getActivityInfo(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG);

        self::assertSame(2, count($client->calls));
        self::assertArrayHasKey('369', $result['activities']);
        self::assertArrayHasKey('555', $result['activities']);
        self::assertSame('Kauai', $result['activities']['369']['island']);
        self::assertSame('8:00 AM Departure', $result['activities']['369']['times']);
        self::assertSame('Remember sunscreen', $result['activities']['369']['notes']);
        self::assertNotNull($result['checkedAt']);
        self::assertIsString($result['hash']);

        $cachePayload = $cache->get('activity-info:supplier-slug:activity-info-test');
        self::assertIsArray($cachePayload);
        self::assertArrayHasKey('activities', $cachePayload);

        $client->calls = [];
        $again = $service->getActivityInfo(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG);
        self::assertSame([], $client->calls, 'Expected subsequent call to use cached payload.');
        self::assertSame($result['hash'], $again['hash']);
    }

    public function testRefreshesCacheWhenHashChanges(): void
    {
        $responses = [
            369 => [
                'name' => 'Morning Tour',
                'description' => 'Initial description',
            ],
            555 => [
                'name' => 'Afternoon Tour',
            ],
        ];

        $cache = new InMemoryCache();
        $client = new ActivityInfoRecordingSoapClient($responses);
        $factory = new ActivityInfoStubSoapClientFactory($client);

        $service = new ActivityInfoService($cache, $factory, 0);
        $initial = $service->getActivityInfo(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG);
        self::assertSame('Initial description', $initial['activities']['369']['description']);
        self::assertSame(2, count($client->calls));

        $client->responses[369]['description'] = 'Updated description';
        $client->calls = [];

        $updated = $service->getActivityInfo(self::SUPPLIER_SLUG, self::ACTIVITY_SLUG);
        self::assertSame('Updated description', $updated['activities']['369']['description']);
        self::assertNotSame($initial['hash'], $updated['hash']);
        self::assertGreaterThan(0, count($client->calls));
    }
}

final class InMemoryCache implements CacheInterface
{
    /** @var array<string, mixed> */
    public array $storage = [];

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->storage[$key] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $this->storage[$key] = $value;
    }

    public function delete(string $key): void
    {
        unset($this->storage[$key]);
    }
}

final class ActivityInfoStubSoapClientFactory implements SoapClientFactory
{
    public function __construct(private SoapClient $client)
    {
    }

    public function build(): SoapClient
    {
        return $this->client;
    }
}

final class ActivityInfoRecordingSoapClient extends SoapClient
{
    /** @var array<int, array<string, mixed>> */
    public array $responses;

    /** @var list<array{0:string,1:array}> */
    public array $calls = [];

    /**
     * @param array<int, array<string, mixed>> $responses
     */
    public function __construct(array $responses)
    {
        $this->responses = $responses;
        // Skip parent constructor; responses are pre-baked for tests.
    }

    public function __soapCall(string $name, array $arguments, ?array $options = null, mixed $inputHeaders = null, mixed &$outputHeaders = null): mixed
    {
        $this->calls[] = [$name, $arguments];

        if ($name !== 'getActivity') {
            throw new RuntimeException(sprintf('Unexpected SOAP call to "%s".', $name));
        }

        $payload = $arguments[0] ?? [];
        $activityId = $payload['activityId'] ?? null;
        if (!is_int($activityId)) {
            throw new RuntimeException('Missing activityId in payload.');
        }

        if (!isset($this->responses[$activityId])) {
            throw new RuntimeException(sprintf('No fixture for activity %d.', $activityId));
        }

        return (object) ['return' => (object) $this->responses[$activityId]];
    }
}
