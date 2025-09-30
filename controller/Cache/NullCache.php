<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Cache;

final class NullCache implements CacheInterface
{
    public function get(string $key, mixed $default = null): mixed
    {
        return $default;
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        // no-op
    }

    public function delete(string $key): void
    {
        // no-op
    }
}
