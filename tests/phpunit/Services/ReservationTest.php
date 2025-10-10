<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Tests\Services;

use PHPUnit\Framework\TestCase;
use PonoRez\SGCForms\DTO\CheckoutInitRequest;
use PonoRez\SGCForms\DTO\CheckoutInitResponse;
use PonoRez\SGCForms\Services\CheckoutInitService;
use PonoRez\SGCForms\Services\SoapClientFactory;
use RuntimeException;
use SoapClient;
use SoapFault;
use stdClass;
use Throwable;

final class ReservationTest extends TestCase
{
    private const SUPPLIER_SLUG = 'supplier-slug';
    private const ACTIVITY_SLUG = 'activity-slug';

    public function testInitiateCheckoutBuildsPayloadAndNormalizesResponse(): void
    {
        $request = new CheckoutInitRequest(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-01-01',
            'timeslot-101',
            ['345' => 2, '456' => 0],
            ['upgrade-photos' => 1, 'upgrade-lunch' => 0],
            [
                'firstName' => 'Kai',
                'lastName' => 'Jordan',
                'email' => 'kai@example.com',
                'phone' => '800-555-0000',
            ],
            'route-shuttle',
            [
                ['id' => 10, 'value' => 'Yes'],
            ],
            ['notes' => 'Vegetarian meal']
        );

        $responses = [
            'calculatePriceAndPaymentAndTransactionFee' => (object) [
                'return' => (object) [
                    'out_price' => 199.5,
                    'out_requiredPaymentWithoutTransactionFee' => 75.25,
                ],
            ],
            'createReservation' => (object) [
                'return' => (object) [
                    'id' => 'RES-123',
                ],
            ],
        ];

        $client = new CheckoutRecordingSoapClient($responses);
        $factory = new CheckoutStubSoapClientFactory($client);

        $service = new CheckoutInitService($factory);
        $response = $service->initiate($request);

        self::assertInstanceOf(CheckoutInitResponse::class, $response);
        self::assertSame(199.5, $response->getTotalPrice());
        self::assertSame(75.25, $response->getSupplierPaymentAmount());
        self::assertSame('RES-123', $response->getReservationId());

        $responseArray = $response->toArray();
        self::assertSame(199.5, $responseArray['totalPrice']);
        self::assertSame('RES-123', $responseArray['reservation']['id']);

        self::assertCount(2, $client->calls);

        [$calcMethod, $calcArgs] = $client->calls[0];
        self::assertSame('calculatePriceAndPaymentAndTransactionFee', $calcMethod);
        $calcPayload = $calcArgs[0];
        self::assertSame('apiUsername', $calcPayload['serviceLogin']['username']);
        self::assertSame(123, $calcPayload['supplierId']);
        self::assertSame(369, $calcPayload['activityId']);
        self::assertSame('2024-01-01', $calcPayload['reservationOrder']['date']);
        self::assertSame('timeslot-101', $calcPayload['reservationOrder']['timeslotId']);
        self::assertSame('route-shuttle', $calcPayload['reservationOrder']['transportationRouteId']);
        self::assertSame([
            [
                'guestTypeId' => 345,
                'id' => 345,
                'guestCount' => 2,
                'count' => 2,
            ],
        ], $calcPayload['reservationOrder']['guestCounts']);
        self::assertSame([
            [
                'upgradeId' => 'upgrade-photos',
                'id' => 'upgrade-photos',
                'quantity' => 1,
            ],
        ], $calcPayload['reservationOrder']['upgrades']);
        self::assertSame([
            [
                'checklistItemId' => 10,
                'value' => 'Yes',
            ],
        ], $calcPayload['reservationOrder']['checklistValues']);

        [$createMethod, $createArgs] = $client->calls[1];
        self::assertSame('createReservation', $createMethod);
        $createPayload = $createArgs[0];
        self::assertSame($calcPayload['reservationOrder'], $createPayload['reservationOrder']);
        self::assertSame(75.25, $createPayload['supplierPaymentAmount']);
    }

    public function testInitiateCheckoutPropagatesSoapFault(): void
    {
        $request = new CheckoutInitRequest(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-01-01',
            'timeslot-101',
            [],
            [],
            [],
            null,
            [],
            []
        );

        $fault = new SoapFault('Server', 'Checkout failed');
        $client = new CheckoutRecordingSoapClient([
            'calculatePriceAndPaymentAndTransactionFee' => null,
        ], [
            'calculatePriceAndPaymentAndTransactionFee' => $fault,
        ]);
        $factory = new CheckoutStubSoapClientFactory($client);

        $service = new CheckoutInitService($factory);

        $this->expectException(SoapFault::class);
        $service->initiate($request);
    }

    public function testInitiateCheckoutFallsBackToLegacyCalculationMethod(): void
    {
        $request = new CheckoutInitRequest(
            self::SUPPLIER_SLUG,
            self::ACTIVITY_SLUG,
            '2024-01-01',
            'timeslot-101',
            ['345' => 2],
            [],
            [],
            null,
            [],
            []
        );

        $responses = [
            'calculatePricesAndPayment' => (object) [
                'return' => (object) [
                    'out_price' => 150.0,
                    'out_requiredSupplierPayment' => 60.0,
                ],
            ],
            'createReservation' => (object) [
                'return' => (object) [
                    'id' => 'RES-LEGACY',
                ],
            ],
        ];

        $fault = new SoapFault('Client', 'Function ("calculatePriceAndPaymentAndTransactionFee") is not a valid method for this service');

        $client = new CheckoutRecordingSoapClient(
            $responses,
            ['calculatePriceAndPaymentAndTransactionFee' => $fault]
        );
        $factory = new CheckoutStubSoapClientFactory($client);

        $service = new CheckoutInitService($factory);
        $response = $service->initiate($request);

        self::assertSame(150.0, $response->getTotalPrice());
        self::assertSame(60.0, $response->getSupplierPaymentAmount());
        self::assertSame('RES-LEGACY', $response->getReservationId());

        self::assertCount(3, $client->calls);

        [$primaryMethod] = $client->calls[0];
        self::assertSame('calculatePriceAndPaymentAndTransactionFee', $primaryMethod);

        [$fallbackMethod] = $client->calls[1];
        self::assertSame('calculatePricesAndPayment', $fallbackMethod);
    }
}

final class CheckoutStubSoapClientFactory implements SoapClientFactory
{
    public int $buildCount = 0;

    public function __construct(private ?SoapClient $client = null)
    {
    }

    public function build(): SoapClient
    {
        $this->buildCount++;

        if ($this->client === null) {
            throw new RuntimeException('Missing Checkout soap client.');
        }

        return $this->client;
    }
}

final class CheckoutRecordingSoapClient extends SoapClient
{
    /** @var list<array{0:string,1:array}> */
    public array $calls = [];

    /**
     * @param array<string, mixed> $responses
     * @param array<string, Throwable> $throwables
     */
    public function __construct(
        private array $responses = [],
        private array $throwables = []
    ) {
        // Skip parent constructor.
    }

    public function __soapCall(string $name, array $arguments, ?array $options = null, mixed $inputHeaders = null, mixed &$outputHeaders = null): mixed
    {
        $this->calls[] = [$name, $arguments];

        if (isset($this->throwables[$name])) {
            throw $this->throwables[$name];
        }

        return $this->responses[$name] ?? null;
    }
}
