<?php

declare(strict_types=1);

require __DIR__ . '/../controller/Setup.php';

http_response_code(501);
header('Content-Type: application/json');

echo json_encode([
    'status' => 'error',
    'message' => 'Endpoint not implemented yet.'
]);
