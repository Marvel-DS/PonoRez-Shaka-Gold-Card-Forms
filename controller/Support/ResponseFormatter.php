<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Support;

final class ResponseFormatter
{
    private function __construct()
    {
    }

    public static function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    public static function success(array $data = [], int $status = 200): void
    {
        self::json(['status' => 'ok'] + $data, $status);
    }

    public static function error(string $message, int $status = 500): void
    {
        self::json(['status' => 'error', 'message' => $message], $status);
    }
}
