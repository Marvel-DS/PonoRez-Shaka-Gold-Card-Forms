<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Support;

final class RequestValidator
{
    private function __construct()
    {
    }

    public static function requireParams(array $params, array $required): array
    {
        $data = [];
        $missing = [];

        foreach ($required as $param) {
            $value = $params[$param] ?? null;
            if ($value === null || (is_string($value) && trim($value) === '')) {
                $missing[] = $param;
            } else {
                $data[$param] = is_string($value) ? trim($value) : $value;
            }
        }

        if (!empty($missing)) {
            throw new \InvalidArgumentException('Missing required parameters: ' . implode(', ', $missing));
        }

        return $data;
    }

    public static function optionalJson(string $value, string $context): array
    {
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException(sprintf('Invalid JSON payload for %s.', $context));
        }

        return $decoded;
    }
}
