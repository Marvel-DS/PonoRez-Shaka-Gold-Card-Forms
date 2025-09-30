<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Cache;

final class CacheKeyGenerator
{
    private const DELIMITER = ':';

    private function __construct()
    {
    }

    public static function fromParts(string $prefix, string ...$parts): string
    {
        $segments = array_map(static function (string $segment): string {
            $slug = preg_replace('/[^a-zA-Z0-9_-]/', '-', $segment);
            return strtolower(trim((string) $slug, '-_'));
        }, $parts);

        array_unshift($segments, strtolower($prefix));

        return implode(self::DELIMITER, $segments);
    }
}
