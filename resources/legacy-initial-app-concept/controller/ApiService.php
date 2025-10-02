<?php
/**
 * ApiService
 *
 * Business-level wrapper for SOAP availability calls that will provide data (activities, dates, guest types, availability).
 * This service:
 *  - Fetches supplier activities
 *  - Gets guest types for an activity/date
 *  - Gets available dates
 *
 * Reservation checkout is handled directly by PonoRez (JS side).
 */
namespace PonoRez\SGCForms;

use PonoRez\SGCForms\SoapService;
use PonoRez\SGCForms\UtilityService;

class ApiService
{
    private SoapService $soap;

    public function __construct(string $username, string $password, bool $production = false)
    {
        $this->soap = new SoapService($username, $password, $production);
    }

    /**
     * Validate API login.
     *
     * @return mixed Returns login test result, could be boolean or other SOAP response.
     */
    public function testLogin(): mixed
    {
        return $this->soap->testLogin();
    }

    /**
     * Get activities for a supplier.
     *
     * @param int $supplierId Supplier identifier.
     * @return mixed Returns activities data as array or stdClass depending on SOAP response.
     */
    public function getSupplierActivities(int $supplierId): mixed
    {
        try {
            return $this->soap->getSupplierActivities($supplierId);
        } catch (\Exception $e) {
            UtilityService::log("getSupplierActivities failed: " . $e->getMessage(), "error");
            return [];
        }
    }

    /**
     * Get available dates for an activity.
     *
     * @param int $supplierId Supplier identifier.
     * @param int $activityId Activity identifier.
     * @return mixed Returns available dates as array or stdClass depending on SOAP response.
     */
    public function getActivityAvailableDates(int $supplierId, int $activityId): mixed
    {
        try {
            return $this->soap->getActivityAvailableDates($supplierId, $activityId);
        } catch (\Exception $e) {
            UtilityService::log("getActivityAvailableDates failed: " . $e->getMessage(), "error");
            return [];
        }
    }

    /**
     * Get guest types (with pricing & availability) for an activity on a date.
     *
     * @param int $supplierId Supplier identifier.
     * @param int $activityId Activity identifier.
     * @param string $date Date string.
     * @param array $guestCounts Optional guest counts (e.g. [guestTypeId => qty]).
     * @return mixed Returns guest types data as array or stdClass depending on SOAP response.
     */
    public function getActivityGuestTypes(int $supplierId, int $activityId, string $date, array $guestCounts = []): mixed
    {
        try {
            return $this->soap->getActivityGuestTypes($supplierId, $activityId, $date, $guestCounts);
        } catch (\Exception $e) {
            UtilityService::log("getActivityGuestTypes failed: " . $e->getMessage(), "error");
            return [];
        }
    }

    /**
     * Get details for an activity on a specific date.
     *
     * @param int $supplierId Supplier identifier.
     * @param int $activityId Activity identifier.
     * @param string $date Date string.
     * @return mixed Returns activity details as array or stdClass depending on SOAP response.
     */
    public function getActivityDetails(int $supplierId, int $activityId, string $date): mixed
    {
        try {
            return $this->soap->getActivityDetails($supplierId, $activityId, $date);
        } catch (\Exception $e) {
            UtilityService::log("getActivityDetails failed: " . $e->getMessage(), "error");
            return [];
        }
    }

    /**
     * Check if requested seats are available for an activity on a date.
     *
     * @param int $supplierId Supplier identifier.
     * @param int $activityId Activity identifier.
     * @param string $date Date string.
     * @param int $requestedSeats Number of seats requested.
     * @return mixed Returns availability status, could be boolean or other SOAP response.
     */
    public function checkActivityAvailability(int $supplierId, int $activityId, string $date, int $requestedSeats): mixed
    {
        try {
            return $this->soap->checkActivityAvailability($supplierId, $activityId, $date, $requestedSeats);
        } catch (\Exception $e) {
            UtilityService::log("checkActivityAvailability failed: " . $e->getMessage(), "error");
            return false;
        }
    }
}