<?php
header('Content-Type: application/json');

// === CONFIG ===
$WSDL = "https://www.hawaiifun.org/reservation/services/2012-05-10/SupplierService?wsdl";
$USERNAME = "API-EZBook";
$PASSWORD = "4DAncuta2use!22";

// === Setup SOAP client ===
try {
    $client = new SoapClient($WSDL, [
        'trace' => true,
        'exceptions' => true,
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => 'SOAP client init failed', 'details' => $e->getMessage()]);
    exit;
}

// === Login payload ===
$login = [
    'serviceLogin' => [
        'username' => $USERNAME,
        'password' => $PASSWORD,
    ]
];

// === Router ===
$action = $_GET['action'] ?? null;

try {
    switch ($action) {
        /**
         * GET guest types + prices
         */
        case 'guest-types':
            $params = array_merge($login, [
                'supplierId' => (int) $_GET['supplierId'],
                'activityId' => (int) $_GET['activityId'],
                'date' => $_GET['date'],
            ]);
            $resp = $client->__soapCall('getActivityGuestTypes', [$params]);

            $respArray = json_decode(json_encode($resp), true);

            // Supplier API usually wraps under 'return'
            if (isset($respArray['return'])) {
                $respArray = $respArray['return'];
            } elseif (isset($respArray['GuestTypeInfo'])) {
                $respArray = $respArray['GuestTypeInfo'];
            }

            // Ensure array of rows
            if (!is_array($respArray) || isset($respArray['id'])) {
                $respArray = [$respArray];
            }

            $guestTypes = [];
            foreach ($respArray as $gt) {
                $guestTypes[] = [
                    'id' => $gt['id'] ?? null,
                    'name' => $gt['name'] ?? null,
                    'price' => $gt['price'] ?? null,
                    'availabilityPerGuest' => $gt['availabilityPerGuest'] ?? null,
                ];
            }

            echo json_encode($guestTypes);
            break;

        /**
         * GET available times
         */
        case 'times':
            $supplierId = (int) $_GET['supplierId'];
            $activityId = (int) $_GET['activityId'];
            $date = $_GET['date'];

            $params = array_merge($login, [
                'supplierId' => $supplierId,
                'activityId' => $activityId,
            ]);
            $resp = $client->__soapCall('getActivity', [$params]);

            $respArray = json_decode(json_encode($resp), true);
            $activityInfo = $respArray['return'] ?? $respArray;

            $times = [];
            if (!empty($activityInfo['times'])) {
                $slots = explode(',', $activityInfo['times']);
                foreach ($slots as $slot) {
                    $slotLabel = trim($slot);

                    $availParams = array_merge($login, [
                        'supplierId' => $supplierId,
                        'activityId' => $activityId,
                        'date' => $date,
                        'requestedAvailability' => 1,
                    ]);
                    $isAvailable = $client->__soapCall('checkActivityAvailability', [$availParams]);

                    $times[] = [
                        'id' => $activityInfo['id'] ?? null,
                        'label' => $slotLabel,
                        'available' => $isAvailable ? 'Yes' : 'No'
                    ];
                }
            }

            echo json_encode($times);
            break;

        /**
         * GET checklist items for an activity
         */
        case 'checklist':
            $supplierId = (int) $_GET['supplierId'];
            $activityId = (int) $_GET['activityId'];

            $params = array_merge($login, [
                'supplierId' => $supplierId,
                'activityId' => $activityId,
            ]);
            $resp = $client->__soapCall('getActivityChecklistItems', [$params]);

            $respArray = json_decode(json_encode($resp), true);
            $itemsList = $respArray['return'] ?? $respArray;

            if (!is_array($itemsList) || isset($itemsList['id'])) {
                $itemsList = [$itemsList];
            }

            $items = [];
            foreach ($itemsList as $item) {
                $items[] = [
                    'id' => $item['id'] ?? null,
                    'name' => $item['name'] ?? null,
                    'type' => $item['type'] ?? null,
                    'isPerSeat' => $item['isPerSeat'] ?? null,
                    'isMandatory' => $item['isMandatory'] ?? null,
                ];
            }

            echo json_encode($items);
            break;

        /**
         * POST book reservation
         */
        case 'book':
            $payload = json_decode(file_get_contents('php://input'), true);

            $reservationOrder = [
                'date' => $payload['date'],
                'guestCounts' => array_map(function ($g) {
                    return ['id' => (int)$g['id'], 'count' => (int)$g['count']];
                }, $payload['guestCounts']),
                'voucherId' => $payload['voucherId'] ?? null,
                'checklistValues' => []
            ];

            if (!empty($payload['checklist'])) {
                foreach ($payload['checklist'] as $cl) {
                    $reservationOrder['checklistValues'][] = [
                        'checklistItemId' => (int)$cl['id'],
                        'value' => $cl['value']
                    ];
                }
            }

            // Step 1: calculate prices
            $calcParams = array_merge($login, [
                'supplierId' => (int)$payload['supplierId'],
                'activityId' => (int)$payload['activityId'],
                'reservationOrder' => $reservationOrder
            ]);
            $calcResp = $client->__soapCall('calculatePricesAndPayment', [$calcParams]);

            $calcArray = json_decode(json_encode($calcResp), true);
            $price = $calcArray['out_price'] ?? 0;
            $supplierPayment = $calcArray['out_requiredSupplierPayment'] ?? 0;

            // Step 2: create reservation
            $createParams = array_merge($login, [
                'supplierId' => (int)$payload['supplierId'],
                'activityId' => (int)$payload['activityId'],
                'reservationOrder' => $reservationOrder,
                'agent' => 'API',
                'supplierPaymentAmount' => $supplierPayment,
                'creditCardInfo' => null,
            ]);
            $reservation = $client->__soapCall('createReservation', [$createParams]);

            $reservationArr = json_decode(json_encode($reservation), true);

            echo json_encode([
                'total' => $price,
                'reservationId' => $reservationArr['id'] ?? null,
            ]);
            break;

        default:
            echo json_encode(['error' => 'Unknown action']);
    }
} catch (Exception $e) {
    echo json_encode([
        'error' => 'SOAP call failed',
        'details' => $e->getMessage()
    ]);
}