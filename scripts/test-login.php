<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

use PonoRez\SGCForms\Services\SoapClientBuilder;
use PonoRez\SGCForms\UtilityService;

if ($argc === 2 && str_starts_with($argv[1], '--config=')) {
    $slug = substr($argv[1], strlen('--config='));
    $supplierConfig = UtilityService::loadSupplierConfig($slug);
    $creds = $supplierConfig['soapCredentials'] ?? null;
    if (!is_array($creds) || empty($creds['username']) || empty($creds['password'])) {
        fwrite(STDERR, "No soapCredentials configured for supplier {$slug}.\n");
        exit(1);
    }
    $username = (string) $creds['username'];
    $password = (string) $creds['password'];
    echo "Testing credentials for supplier {$slug} (username: {$username})\n";
} elseif ($argc >= 3) {
    [$script, $username, $password] = $argv;
} else {
    fwrite(STDERR, "Usage: php scripts/test-login.php <username> <password>\n");
    fwrite(STDERR, "   or: php scripts/test-login.php --config=<supplier-slug>\n");
    exit(1);
}

try {
    $client = (new SoapClientBuilder())->build();
    $response = $client->__soapCall('testLogin', [[
        'serviceLogin' => [
            'username' => $username,
            'password' => $password,
        ],
    ]]);

    if (isset($response->out_status)) {
        echo 'Service status: ' . $response->out_status . "\n";
    } else {
        echo "Service returned unknown status\n";
    }

    echo "Login successful\n";
    print_r($response);
} catch (Throwable $exception) {
    echo "Login failed: " . $exception->getMessage() . "\n";
}
