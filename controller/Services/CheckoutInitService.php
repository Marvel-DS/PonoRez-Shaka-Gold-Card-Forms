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
        $contact = $this->mapContact($request->getContact());

        $order = [
            'date' => $request->getDate(),
            'firstName' => $contact['firstName'],
            'lastName' => $contact['lastName'],
            'address' => $contact['address'],
            'contactPhone' => $contact['contactPhone'],
            'email' => $contact['email'],
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
            $order['upgradeCounts'] = $this->mapUpgrades($request->getUpgrades());
        }

        if ($contact['stayingAtHotelId'] !== null) {
            $order['stayingAtHotelId'] = $contact['stayingAtHotelId'];
        }

        if ($contact['room'] !== '') {
            $order['room'] = $contact['room'];
        }

        if ($contact['transportationComments'] !== '') {
            $order['transportationComments'] = $contact['transportationComments'];
        }

        if ($contact['arrivalDate'] !== null && $contact['arrivalDate'] !== '') {
            $order['arrivalDate'] = $contact['arrivalDate'];
        }

        if ($contact['comments'] !== '') {
            $order['comments'] = $contact['comments'];
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
                'id' => $id,
                'count' => (int) $quantity,
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
     * @param array<string, mixed> $contact
     *
     * @return array{
     *     firstName: string,
     *     lastName: string,
     *     address: array{streetAddress: string, city: string, state: string, zipCode: string},
     *     contactPhone: string,
     *     email: string,
     *     stayingAtHotelId: ?int,
     *     room: string,
     *     transportationComments: string,
     *     arrivalDate: ?string,
     *     comments: string,
     * }
     */
    private function mapContact(array $contact): array
    {
        $address = $this->mapAddress($contact);

        return [
            'firstName' => $this->stringValue($contact, ['firstName', 'first_name', 'firstname']),
            'lastName' => $this->stringValue($contact, ['lastName', 'last_name', 'lastname']),
            'address' => $address,
            'contactPhone' => $this->stringValue($contact, ['contactPhone', 'phone', 'phoneNumber', 'phone_number']),
            'email' => $this->stringValue($contact, ['email', 'emailAddress', 'email_address']),
            'stayingAtHotelId' => $this->intValue($contact, ['stayingAtHotelId', 'hotelId', 'hotel_id']),
            'room' => $this->stringValue($contact, ['room', 'roomNumber', 'room_number']),
            'transportationComments' => $this->stringValue($contact, ['transportationComments', 'transportation_comments']),
            'arrivalDate' => $this->nullableStringValue($contact, ['arrivalDate', 'arrival_date']),
            'comments' => $this->stringValue($contact, ['comments', 'notes', 'additionalComments', 'additional_comments']),
        ];
    }

    /**
     * @param array<string, mixed> $contact
     *
     * @return array{streetAddress: string, city: string, state: string, zipCode: string}
     */
    private function mapAddress(array $contact): array
    {
        $addressSource = [];
        if (isset($contact['address']) && is_array($contact['address'])) {
            $addressSource = $contact['address'];
        }

        $street = $this->stringValue($addressSource, ['streetAddress', 'street', 'line1', 'address1']);
        if ($street === '') {
            $street = $this->stringValue($contact, ['streetAddress', 'street', 'line1', 'address1', 'address']);
        }

        $city = $this->stringValue($addressSource, ['city', 'town']);
        if ($city === '') {
            $city = $this->stringValue($contact, ['city', 'town']);
        }

        $state = $this->stringValue($addressSource, ['state', 'stateCode', 'region', 'province']);
        if ($state === '') {
            $state = $this->stringValue($contact, ['state', 'stateCode', 'region', 'province']);
        }

        $zip = $this->stringValue($addressSource, ['zipCode', 'postalCode', 'zip', 'postcode']);
        if ($zip === '') {
            $zip = $this->stringValue($contact, ['zipCode', 'postalCode', 'zip', 'postcode']);
        }

        return [
            'streetAddress' => $street,
            'city' => $city,
            'state' => $state,
            'zipCode' => $zip,
        ];
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int, string> $keys
     */
    private function stringValue(array $source, array $keys): string
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $value = $source[$key];
                if (is_scalar($value) || $value === null) {
                    return trim((string) $value);
                }
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int, string> $keys
     */
    private function nullableStringValue(array $source, array $keys): ?string
    {
        $value = $this->stringValue($source, $keys);

        return $value === '' ? null : $value;
    }

    /**
     * @param array<string, mixed> $source
     * @param array<int, string> $keys
     */
    private function intValue(array $source, array $keys): ?int
    {
        foreach ($keys as $key) {
            if (array_key_exists($key, $source)) {
                $value = $source[$key];
                if ($value === null || $value === '') {
                    return null;
                }

                if (is_numeric($value)) {
                    $intValue = (int) $value;

                    return $intValue > 0 ? $intValue : null;
                }
            }
        }

        return null;
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
