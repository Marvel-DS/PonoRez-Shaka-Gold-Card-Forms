<?php
$startTime = microtime(true);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$wsdl = "https://ponorez.online/reservation/services/2012-05-10/SupplierService?wsdl";
$endpoint = "https://ponorez.online/reservation/services/2012-05-10/SupplierService";
$username = "API-EZBook";
$password = "4DAncuta2use!22";

$date = "2025-09-25"; // test date

$activityIds = [639, 5263, 5280, 5281]; // multiple activities

// Party breakdown example
$party = [
    ["id" => 214, "name" => "Adult", "price" => 232.43, "qty" => 3],
    ["id" => 215, "name" => "Child", "price" => 200.00, "qty" => 2],
    ["id" => 1882, "name" => "Youth", "price" => 216.22, "qty" => 1]
];
$totalSeats = array_sum(array_column($party, "qty"));

// Prepare date range for 30 days from $date
$daysRange = 30;

function buildSoapEnvelope($action, $params, $username, $password) {
    $serviceLogin = "
        <serviceLogin>
            <username>{$username}</username>
            <password>{$password}</password>
        </serviceLogin>";
    $paramsXml = '';
    foreach ($params as $key => $value) {
        if (is_array($value)) {
            $paramsXml .= "<{$key}>";
            foreach ($value as $k => $v) {
                $paramsXml .= "<{$k}>{$v}</{$k}>";
            }
            $paramsXml .= "</{$key}>";
        } else {
            $paramsXml .= "<{$key}>{$value}</{$key}>";
        }
    }
    $envelope = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:sup="http://supplier.ws.ponorez.online/">
   <soapenv:Header/>
   <soapenv:Body>
      <sup:{$action}>
         {$serviceLogin}
         {$paramsXml}
      </sup:{$action}>
   </soapenv:Body>
</soapenv:Envelope>
XML;
    return $envelope;
}

function sendCurlMultiRequests($requests, $endpoint) {
    $multiHandle = curl_multi_init();
    $curlHandles = [];
    $responses = [];

    foreach ($requests as $key => $req) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req['xml']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: text/xml; charset=utf-8',
            'SOAPAction: "' . $req['action'] . '"',
            'Content-Length: ' . strlen($req['xml']),
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_multi_add_handle($multiHandle, $ch);
        $curlHandles[$key] = $ch;
    }

    // Execute all requests
    $running = null;
    do {
        curl_multi_exec($multiHandle, $running);
        curl_multi_select($multiHandle);
    } while ($running > 0);

    // Collect responses
    foreach ($curlHandles as $key => $ch) {
        $response = curl_multi_getcontent($ch);
        $responses[$key] = $response;
        curl_multi_remove_handle($multiHandle, $ch);
        curl_close($ch);
    }
    curl_multi_close($multiHandle);

    return $responses;
}

function parseSoapResponse($responseXml, $responseTag) {
    if (!$responseXml) {
        return null;
    }
    libxml_use_internal_errors(true);
    $xml = simplexml_load_string($responseXml);
    if ($xml === false) {
        return null;
    }
    $namespaces = $xml->getNamespaces(true);
    $body = $xml->children($namespaces['soapenv'] ?? 'soapenv')->Body ?? null;
    if (!$body) {
        return null;
    }
    $response = $body->children($namespaces['sup'] ?? 'sup')->{$responseTag} ?? null;
    if (!$response) {
        // Try to find the first child if namespaced tag not found
        foreach ($body->children() as $child) {
            if (stripos($child->getName(), $responseTag) !== false) {
                $response = $child;
                break;
            }
        }
    }
    if (!$response) {
        return null;
    }
    return $response->return ?? null;
}

try {
    $results = [];
    $totalApiCalls = 0;

    foreach ($activityIds as $activityId) {
        $apiCalls = 0;

        // Prepare the 3 requests for this activity: getActivity, getActivityAvailableDates, checkActivityAvailability (baseline)
        $requests = [];

        // getActivity
        $requests["getActivity"] = [
            'action' => 'getActivity',
            'xml' => buildSoapEnvelope('getActivity', ['activityId' => $activityId], $username, $password)
        ];

        // getActivityAvailableDates
        $requests["getActivityAvailableDates"] = [
            'action' => 'getActivityAvailableDates',
            'xml' => buildSoapEnvelope('getActivityAvailableDates', ['activityId' => $activityId], $username, $password)
        ];

        // checkActivityAvailability baseline (exact totalSeats)
        $requests["checkActivityAvailability_baseline"] = [
            'action' => 'checkActivityAvailability',
            'xml' => buildSoapEnvelope('checkActivityAvailability', [
                'activityId' => $activityId,
                'date' => $date,
                'requestedAvailability' => $totalSeats
            ], $username, $password)
        ];

        // Send the 3 requests in parallel
        $responses = sendCurlMultiRequests($requests, $endpoint);
        $apiCalls += 3;

        // Parse responses
        $detailsResp = parseSoapResponse($responses["getActivity"], 'getActivityResponse');
        $availableDatesResp = parseSoapResponse($responses["getActivityAvailableDates"], 'getActivityAvailableDatesResponse');
        $baselineResp = parseSoapResponse($responses["checkActivityAvailability_baseline"], 'checkActivityAvailabilityResponse');

        $activityName = $detailsResp->name ?? "Unknown";

        $availableDates = [];
        if ($availableDatesResp && is_array($availableDatesResp)) {
            // In case return is an array of strings or a single string
            if (is_array($availableDatesResp)) {
                $availableDates = $availableDatesResp;
            } else {
                // Try to extract dates from XML children
                $availableDates = [];
                foreach ($availableDatesResp->children() as $child) {
                    $availableDates[] = (string)$child;
                }
            }
        } elseif ($availableDatesResp) {
            // If single string
            $availableDates = [(string)$availableDatesResp];
        }

        if (!in_array($date, $availableDates)) {
            // Date not available
            $results[] = [
                "activityId" => $activityId,
                "activityName" => $activityName,
                "date" => $date,
                "totalRequestedSeats" => $totalSeats,
                "maxSeatsEstimate" => 0,
                "available" => false,
                "message" => "Not available for {$totalSeats} seats",
                "details" => [
                    "island" => $detailsResp->island ?? null,
                    "times" => $detailsResp->times ?? null,
                    //"description" => $detailsResp->description ?? null,
                    //"notes" => $detailsResp->notes ?? null,
                    //"directions" => $detailsResp->directions ?? null,
                    "startTimeMinutes" => $detailsResp->startTimeMinutes ?? null,
                    "transportationMandatory" => $detailsResp->transportationMandatory ?? null,
                ],
                "apiCalls" => $apiCalls
            ];
            $totalApiCalls += $apiCalls;
            continue;
        }

        // Check baseline availability
        $baselineOK = false;
        if ($baselineResp !== null) {
            $baselineOK = filter_var($baselineResp, FILTER_VALIDATE_BOOLEAN);
        }

        if (!$baselineOK) {
            // Not even X seats are available → report and continue to next activity
            $results[] = [
                "activityId" => $activityId,
                "activityName" => $activityName,
                "date" => $date,
                "totalRequestedSeats" => $totalSeats,
                "maxSeatsEstimate" => 0,
                "available" => false,
                "message" => "Not available for {$totalSeats} seats",
                "details" => [
                    "island" => $detailsResp->island ?? null,
                    "times" => $detailsResp->times ?? null,
                    //"description" => $detailsResp->description ?? null,
                    //"notes" => $detailsResp->notes ?? null,
                    //"directions" => $detailsResp->directions ?? null,
                    "startTimeMinutes" => $detailsResp->startTimeMinutes ?? null,
                    "transportationMandatory" => $detailsResp->transportationMandatory ?? null,
                ],
                "apiCalls" => $apiCalls
            ];
            $totalApiCalls += $apiCalls;
            continue;
        }

        // 2) Tiered probes when baseline is OK in order: +3, +2, +1
        $tiers = [3, 2, 1];
        $maxAvailable = $totalSeats;
        $message = "Last {$totalSeats} seats available";

        foreach ($tiers as $add) {
            $probeSeats = $totalSeats + $add;

            $requestKey = "checkActivityAvailability_probe_{$add}";
            $requestXml = buildSoapEnvelope('checkActivityAvailability', [
                'activityId' => $activityId,
                'date' => $date,
                'requestedAvailability' => $probeSeats
            ], $username, $password);

            $multiRequests = [
                $requestKey => [
                    'action' => 'checkActivityAvailability',
                    'xml' => $requestXml
                ]
            ];

            $responsesProbe = sendCurlMultiRequests($multiRequests, $endpoint);
            $apiCalls++;

            $probeResp = parseSoapResponse($responsesProbe[$requestKey], 'checkActivityAvailabilityResponse');
            $ok = false;
            if ($probeResp !== null) {
                $ok = filter_var($probeResp, FILTER_VALIDATE_BOOLEAN);
            }

            if ($ok) {
                if ($add === 3) {
                    $message = "Enough seats available";
                } else {
                    $message = "Only {$probeSeats} seats available";
                }
                $maxAvailable = $probeSeats;
                break;
            }
        }

        $results[] = [
            "activityId" => $activityId,
            "activityName" => $activityName,
            "date" => $date,
            "totalRequestedSeats" => $totalSeats,
            "maxSeatsEstimate" => $maxAvailable,
            "available" => true,
            "message" => $message,
            "details" => [
                "island" => $detailsResp->island ?? null,
                "times" => $detailsResp->times ?? null,
                //"description" => $detailsResp->description ?? null,
                //"notes" => $detailsResp->notes ?? null,
                //"directions" => $detailsResp->directions ?? null,
                "startTimeMinutes" => $detailsResp->startTimeMinutes ?? null,
                "transportationMandatory" => $detailsResp->transportationMandatory ?? null,
            ],
            "apiCalls" => $apiCalls
        ];
        $totalApiCalls += $apiCalls;
    }

    header('Content-Type: application/json');
    echo json_encode([
        "status" => "ok",
        "activitiesChecked" => count($activityIds),
        "results" => $results,
        "totalApiCalls" => $totalApiCalls
    ], JSON_PRETTY_PRINT);

    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 3); // seconds with 3 decimals
    echo "\nExecution time: {$executionTime} seconds\n";

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 3); // seconds with 3 decimals
    echo "\nExecution time: {$executionTime} seconds\n";
}