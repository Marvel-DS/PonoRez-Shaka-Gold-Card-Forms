<?php
$startTime = microtime(true);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$wsdl = "https://ponorez.online/reservation/services/2012-05-10/SupplierService";
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

// Helper to build SOAP envelope
function buildEnvelope($action, $params) {
    global $username, $password;
    $body = "<ns1:$action><serviceLogin username=\"$username\" password=\"$password\"/>";
    foreach ($params as $k => $v) {
        $body .= "<$k>$v</$k>";
    }
    $body .= "</ns1:$action>";
    return "<?xml version=\"1.0\" encoding=\"UTF-8\"?>
    <SOAP-ENV:Envelope xmlns:SOAP-ENV=\"http://schemas.xmlsoap.org/soap/envelope/\" xmlns:ns1=\"http://hawaiifun.org/reservation/services/2012-05-10/SupplierService\">
      <SOAP-ENV:Body>$body</SOAP-ENV:Body>
    </SOAP-ENV:Envelope>";
}

// Curl multi request
function multiSoapRequest($requests) {
    $mh = curl_multi_init();
    $handles = [];
    foreach ($requests as $key => $req) {
        $ch = curl_init($req['url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Content-Type: text/xml; charset=utf-8",
            "SOAPAction: \"\""
        ]);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $req['xml']);
        $handles[$key] = $ch;
        curl_multi_add_handle($mh, $ch);
    }
    $running = null;
    do {
        curl_multi_exec($mh, $running);
        curl_multi_select($mh);
    } while ($running > 0);

    $responses = [];
    foreach ($handles as $key => $ch) {
        $responses[$key] = curl_multi_getcontent($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
    }
    curl_multi_close($mh);
    return $responses;
}

try {
    $results = [];
    $totalApiCalls = 0;
    $activityCache = [];

    // Prepare SOAP requests for all activities (getActivity and availability checks)
    $requests = [];
    foreach ($activityIds as $activityId) {
        // Cache activity details
        if (!isset($activityCache[$activityId])) {
            $xml = buildEnvelope("getActivity", ["activityId" => $activityId]);
            $requests["details_$activityId"] = [
                "url" => "$wsdl",
                "xml" => $xml
            ];
            $totalApiCalls++;
        }
        // Baseline availability
        $xml = buildEnvelope("checkActivityAvailability", [
            "activityId" => $activityId,
            "date" => $date,
            "requestedAvailability" => $totalSeats
        ]);
        $requests["base_$activityId"] = [
            "url" => "$wsdl",
            "xml" => $xml
        ];
        $totalApiCalls++;
        // +3 availability
        $xml = buildEnvelope("checkActivityAvailability", [
            "activityId" => $activityId,
            "date" => $date,
            "requestedAvailability" => $totalSeats + 3
        ]);
        $requests["plus3_$activityId"] = [
            "url" => "$wsdl",
            "xml" => $xml
        ];
        $totalApiCalls++;
    }

    // Execute all requests in parallel
    $responses = multiSoapRequest($requests);

    foreach ($activityIds as $activityId) {
        $apiCalls = 0;
        // Parse getActivity response
        if (isset($responses["details_$activityId"])) {
            $respXml = simplexml_load_string($responses["details_$activityId"]);
            $respXml->registerXPathNamespace('ns2', 'http://hawaiifun.org/reservation/services/2012-05-10/SupplierService');
            $activityNode = $respXml->xpath('//ns2:getActivityResponse//return');
            if ($activityNode && isset($activityNode[0])) {
                $activityName = (string)$activityNode[0]->name ?? "Unknown";
                $island = (string)$activityNode[0]->island ?? "";
                $times = (string)$activityNode[0]->times ?? "";
            } else {
                $activityName = "Unknown";
                $island = "";
                $times = "";
            }
            $activityCache[$activityId] = [
                "name" => $activityName,
                "island" => $island,
                "times" => $times
            ];
            $apiCalls++;
        } else {
            $activityName = $activityCache[$activityId]['name'] ?? "Unknown";
            $island = $activityCache[$activityId]['island'] ?? "";
            $times = $activityCache[$activityId]['times'] ?? "";
        }

        // Parse baseline response
        $baselineOK = false;
        if (isset($responses["base_$activityId"])) {
            $respXml = simplexml_load_string($responses["base_$activityId"]);
            $respXml->registerXPathNamespace('ns2', 'http://hawaiifun.org/reservation/services/2012-05-10/SupplierService');
            $baselineNodes = $respXml->xpath('//ns2:return');
            if (is_array($baselineNodes) && !empty($baselineNodes)) {
                $baselineOK = ((string)$baselineNodes[0] === 'true');
            }
            $apiCalls++;
        }

        if (!$baselineOK) {
            $results[] = [
                "activityId" => $activityId,
                "activityName" => $activityName,
                "date" => $date,
                "totalRequestedSeats" => $totalSeats,
                "maxSeatsEstimate" => 0,
                "available" => false,
                "message" => "Not available for {$totalSeats} seats",
                "details" => [
                    "island" => $island,
                    "times" => $times
                ],
                "apiCalls" => $apiCalls,
                "debugRawAvailability" => $responses["base_$activityId"]
            ];
            continue;
        }

        // Parse +3 response
        $plus3OK = false;
        if (isset($responses["plus3_$activityId"])) {
            $respXml = simplexml_load_string($responses["plus3_$activityId"]);
            $respXml->registerXPathNamespace('ns2', 'http://hawaiifun.org/reservation/services/2012-05-10/SupplierService');
            $plus3Nodes = $respXml->xpath('//ns2:return');
            if (is_array($plus3Nodes) && !empty($plus3Nodes)) {
                $plus3OK = ((string)$plus3Nodes[0] === 'true');
            }
            $apiCalls++;
        }

        if ($plus3OK) {
            $message = "Enough seats available";
            $maxAvailable = $totalSeats + 3;
        } else {
            $message = "Only {$totalSeats} seats available";
            $maxAvailable = $totalSeats;
        }

        $results[] = [
            "activityId" => $activityId,
            "activityName" => $activityName,
            "date" => $date,
            "totalRequestedSeats" => $totalSeats,
            "maxSeatsEstimate" => $maxAvailable,
            "available" => $baselineOK, // baseline determines availability
            "message" => $message,
            "details" => [
                "island" => $island,
                "times" => $times
            ],
            "apiCalls" => $apiCalls
        ];
    }

    header('Content-Type: application/json');
    echo json_encode([
        "status" => "ok",
        "activitiesChecked" => count($activityIds),
        "results" => $results,
        "totalApiCalls" => $totalApiCalls
    ], JSON_PRETTY_PRINT);

    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 3);
    echo "\nExecution time: {$executionTime} seconds\n";

} catch (Exception $e) {
    echo json_encode([
        "status" => "error",
        "message" => $e->getMessage()
    ], JSON_PRETTY_PRINT);
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 3);
    echo "\nExecution time: {$executionTime} seconds\n";
}