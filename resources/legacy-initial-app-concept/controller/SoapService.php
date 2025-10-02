<?php
/**
 * SoapService
 *
 * Thin wrapper around the PonoRez SOAP API.
 * Exposes raw SOAP methods in a safe, reusable way.
 *
 */

namespace PonoRez\SGCForms;

use SoapClient;
use Exception;
use PonoRez\SGCForms\UtilityService;

class SoapService
{
    private SoapClient $client;
    private array $login;

    public function __construct(string $username, string $password, bool $production = false)
    {
        $wsdl = $production
            ? 'https://www.ponorez.online/reservation/services/2012-05-10/SupplierService?wsdl'
            : 'https://www.ponorez.online/reservation_test/services/2012-05-10/SupplierService?wsdl';

        $this->client = new SoapClient($wsdl, [
            'trace'      => true,
            'exceptions' => true,
            'cache_wsdl' => WSDL_CACHE_NONE,
        ]);

        $this->login = [
            'username' => $username,
            'password' => $password,
        ];
    }

    /**
     * Get activities for a supplier.
     *
     * @return mixed stdClass|array SOAP may return stdClass, array, or bool depending on the call.
     */
    public function getSupplierActivities(int $supplierId): mixed
    {
        try {
            return $this->client->getSupplierActivities([
                'serviceLogin' => $this->login,
                'supplierId'   => $supplierId,
            ]);
        } catch (Exception $e) {
            UtilityService::log("getSupplierActivities failed: " . $e->getMessage(), "error");
            return [];
        }
    }

    /**
     * Get available dates for an activity.
     *
     * @return mixed stdClass|array SOAP may return stdClass, array, or bool depending on the call.
     */
    public function getActivityAvailableDates(int $supplierId, int $activityId): mixed
    {
        try {
            return $this->client->getActivityAvailableDates([
                'serviceLogin' => $this->login,
                'supplierId'   => $supplierId,
                'activityId'   => $activityId,
            ]);
        } catch (Exception $e) {
            UtilityService::log("getActivityAvailableDates failed: " . $e->getMessage(), "error");
            return [];
        }
    }

    /**
     * Get guest types (with pricing & availability) for an activity on a date.
     *
     * @return mixed stdClass|array SOAP may return stdClass, array, or bool depending on the call.
     */
    public function getActivityGuestTypes(int $supplierId, int $activityId, string $date, array $guestCounts = []): mixed
    {
        try {
            return $this->client->getActivityGuestTypes([
                'serviceLogin' => $this->login,
                'supplierId'   => $supplierId,
                'activityId'   => $activityId,
                'date'         => $date,
                'guestCounts'  => $guestCounts,
            ]);
        } catch (Exception $e) {
            UtilityService::log("getActivityGuestTypes failed: " . $e->getMessage(), "error");
            return [];
        }
    }

    /**
     * Check if requested seats are available.
     *
     * @return mixed bool|stdClass SOAP may return bool or stdClass depending on the call.
     */
    public function checkActivityAvailability(int $supplierId, int $activityId, string $date, int $requestedSeats): mixed
    {
        try {
            return $this->client->checkActivityAvailability([
                'serviceLogin'        => $this->login,
                'supplierId'          => $supplierId,
                'activityId'          => $activityId,
                'date'                => $date,
                'requestedAvailability' => $requestedSeats,
            ]);
        } catch (Exception $e) {
            UtilityService::log("checkActivityAvailability failed: " . $e->getMessage(), "error");
            return false;
        }
    }

    /**
     * Get min/max/available spots for an activity.
     *
     * @param int $supplierId
     * @param int $activityId
     * @return array ['minSpots' => int|null, 'maxSpots' => int|null, 'availableSpots' => int|null]
     */
    public function getActivityDetails(int $supplierId, int $activityId): array
    {
        try {
            $response = $this->client->getActivityDetails([
                'serviceLogin' => $this->login,
                'supplierId'   => $supplierId,
                'activityId'   => $activityId,
            ]);
            // Defensive extraction: stdClass or array, use null if not set
            $minSpots = null;
            $maxSpots = null;
            $availableSpots = null;
            if (is_object($response)) {
                $minSpots = property_exists($response, 'minSpots') ? $response->minSpots : null;
                $maxSpots = property_exists($response, 'maxSpots') ? $response->maxSpots : null;
                $availableSpots = property_exists($response, 'availableSpots') ? $response->availableSpots : null;
            } elseif (is_array($response)) {
                $minSpots = $response['minSpots'] ?? null;
                $maxSpots = $response['maxSpots'] ?? null;
                $availableSpots = $response['availableSpots'] ?? null;
            }
            return [
                'minSpots' => $minSpots,
                'maxSpots' => $maxSpots,
                'availableSpots' => $availableSpots,
            ];
        } catch (Exception $e) {
            UtilityService::log("getActivityDetails failed: " . $e->getMessage(), "error");
            return [];
        }
    }
}