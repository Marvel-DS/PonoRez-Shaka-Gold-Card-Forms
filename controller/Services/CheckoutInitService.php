<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Services;

use PonoRez\SGCForms\DTO\CheckoutInitRequest;
use PonoRez\SGCForms\DTO\CheckoutInitResponse;
use PonoRez\SGCForms\UtilityService;
use RuntimeException;
use SoapFault;

final class CheckoutInitService
{
    public function __construct(private readonly SoapClientFactory $soapClientBuilder)
    {
    }

    public function initiate(CheckoutInitRequest $request): CheckoutInitResponse
    {
        $supplierConfig = UtilityService::loadSupplierConfig($request->getSupplierSlug());
        $activityConfig = UtilityService::loadActivityConfig($request->getSupplierSlug(), $request->getActivitySlug());

        $primaryActivityId = UtilityService::getPrimaryActivityId($activityConfig);
        if ($primaryActivityId === null) {
            throw new RuntimeException('Unable to determine primary activity ID for checkout.');
        }

        $client = $this->soapClientBuilder->build();
        $login = $this->buildLoginPayload($supplierConfig);
        $reservationOrder = $this->buildReservationOrder($request);

        $calculationPayload = array_merge($login, [
            'supplierId' => $supplierConfig['supplierId'],
            'activityId' => $primaryActivityId,
            'reservationOrder' => $reservationOrder,
        ]);

        $calcArray = $this->callPriceCalculation($client, $calculationPayload);
        $totalPrice = $this->extractTotalPrice($calcArray);
        $supplierPayment = $this->extractSupplierPaymentAmount($calcArray);

        $reservationPayload = array_merge($login, [
            'supplierId' => $supplierConfig['supplierId'],
            'activityId' => $primaryActivityId,
            'reservationOrder' => $reservationOrder,
            'agent' => 'WEB',
            'supplierPaymentAmount' => $supplierPayment,
        ]);

        $reservation = $this->callSoap($client, 'createReservation', $reservationPayload);
        $reservationArray = $this->normalizeResult($reservation);

        $reservationId = $this->extractReservationId($reservationArray);

        return new CheckoutInitResponse(
            $totalPrice,
            $supplierPayment,
            $reservationId,
            [
                'calculation' => $calcArray,
                'reservation' => $reservationArray,
            ]
        );
    }

    private function buildLoginPayload(array $supplierConfig): array
    {
        return [
            'serviceLogin' => [
                'username' => $supplierConfig['soapCredentials']['username'],
                'password' => $supplierConfig['soapCredentials']['password'],
            ],
        ];
    }

    private function buildReservationOrder(CheckoutInitRequest $request): array
    {
        $metadata = $request->getMetadata();

        $order = [
            'date' => $request->getDate(),
            'guestCounts' => $this->mapGuestCounts($request->getGuestCounts()),
        ];

        if ($request->getTimeslotId() !== '') {
            $order['timeslotId'] = $request->getTimeslotId();
        }

        if ($request->getTransportationRouteId() !== null) {
            $order['transportationRouteId'] = $request->getTransportationRouteId();
        }

        if ($request->getChecklist() !== []) {
            $order['checklistValues'] = $this->mapChecklist($request->getChecklist());
        }

        if (array_key_exists('voucherId', $metadata)) {
            $order['voucherId'] = $metadata['voucherId'];
        }

        if ($request->getUpgrades() !== []) {
            $order['upgrades'] = $this->mapUpgrades($request->getUpgrades());
        }

        return $order;
    }

    /**
     * @param array<string, int> $guestCounts
     * @return array<int, array<string, int>>
     */
    private function mapGuestCounts(array $guestCounts): array
    {
        $items = [];
        foreach ($guestCounts as $guestTypeId => $count) {
            if ($count <= 0) {
                continue;
            }
            $id = is_numeric($guestTypeId) ? (int) $guestTypeId : $guestTypeId;
            $items[] = [
                'guestTypeId' => $id,
                'id' => $id,
                'guestCount' => (int) $count,
                'count' => (int) $count,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, int> $upgrades
     * @return array<int, array<string, int>>
     */
    private function mapUpgrades(array $upgrades): array
    {
        $items = [];
        foreach ($upgrades as $upgradeId => $quantity) {
            if ($quantity <= 0) {
                continue;
            }
            $id = is_numeric($upgradeId) ? (int) $upgradeId : (string) $upgradeId;
            $items[] = [
                'upgradeId' => $id,
                'id' => $id,
                'quantity' => (int) $quantity,
            ];
        }

        return $items;
    }

    /**
     * @param array<int, array<string, mixed>> $checklist
     * @return array<int, array<string, mixed>>
     */
    private function mapChecklist(array $checklist): array
    {
        $items = [];
        foreach ($checklist as $item) {
            if (!isset($item['id'])) {
                continue;
            }

            $items[] = [
                'checklistItemId' => (int) $item['id'],
                'value' => $item['value'] ?? null,
            ];
        }

        return $items;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function callPriceCalculation(\SoapClient $client, array $payload): array
    {
        try {
            $calculation = $this->callSoap($client, 'calculatePriceAndPaymentAndTransactionFee', $payload);
        } catch (SoapFault $exception) {
            if (!$this->isInvalidMethodFault($exception)) {
                throw $exception;
            }

            $calculation = $this->callSoap($client, 'calculatePricesAndPayment', $payload);
        }

        return $this->normalizeResult($calculation);
    }

    private function callSoap(\SoapClient $client, string $method, array $payload): mixed
    {
        try {
            return $client->__soapCall($method, [$payload]);
        } catch (SoapFault $exception) {
            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $calculation
     */
    private function extractTotalPrice(array $calculation): float
    {
        if (isset($calculation['out_price'])) {
            return (float) $calculation['out_price'];
        }

        if (isset($calculation['out_newPrice'])) {
            return (float) $calculation['out_newPrice'];
        }

        return 0.0;
    }

    /**
     * @param array<string, mixed> $calculation
     */
    private function extractSupplierPaymentAmount(array $calculation): float
    {
        if (isset($calculation['out_requiredSupplierPayment'])) {
            return (float) $calculation['out_requiredSupplierPayment'];
        }

        if (isset($calculation['out_requiredPaymentWithoutTransactionFee'])) {
            return (float) $calculation['out_requiredPaymentWithoutTransactionFee'];
        }

        if (isset($calculation['out_requiredPayment'])) {
            return (float) $calculation['out_requiredPayment'];
        }

        if (isset($calculation['out_requiredPaymentWithTransactionFee'])) {
            return (float) $calculation['out_requiredPaymentWithTransactionFee'];
        }

        return 0.0;
    }

    private function isInvalidMethodFault(SoapFault $exception): bool
    {
        $message = $exception->getMessage();

        return is_string($message) && stripos($message, 'not a valid method') !== false;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeResult(mixed $response): array
    {
        if (is_object($response) && isset($response->return)) {
            $response = $response->return;
        }

        if (is_object($response)) {
            $response = json_decode(json_encode($response), true) ?: [];
        }

        return is_array($response) ? $response : [];
    }

    private function extractReservationId(array $reservation): ?string
    {
        if (isset($reservation['id'])) {
            return (string) $reservation['id'];
        }

        if (isset($reservation['reservationId'])) {
            return (string) $reservation['reservationId'];
        }

        if (isset($reservation['reservationNumber'])) {
            return (string) $reservation['reservationNumber'];
        }

        return null;
    }
}
