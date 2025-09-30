<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Cache;

use RuntimeException;

final class FileCache implements CacheInterface
{
    private string $directory;

    public function __construct(string $directory)
    {
        $this->directory = rtrim($directory, DIRECTORY_SEPARATOR);
        if (!is_dir($this->directory) && !mkdir($this->directory, 0775, true) && !is_dir($this->directory)) {
            throw new RuntimeException(sprintf('Unable to create cache directory: %s', $directory));
        }
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $path = $this->pathForKey($key);
        if (!file_exists($path)) {
            return $default;
        }

        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) {
            return $default;
        }

        $expires = $payload['expires_at'] ?? null;
        if ($expires !== null && time() >= (int) $expires) {
            @unlink($path);
            return $default;
        }

        return $payload['value'] ?? $default;
    }

    public function set(string $key, mixed $value, int $ttl = 0): void
    {
        $payload = [
            'expires_at' => $ttl > 0 ? time() + $ttl : null,
            'value' => $value,
        ];

        file_put_contents($this->pathForKey($key), json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function delete(string $key): void
    {
        $path = $this->pathForKey($key);
        if (file_exists($path)) {
            @unlink($path);
        }
    }

    private function pathForKey(string $key): string
    {
        return $this->directory . DIRECTORY_SEPARATOR . md5($key) . '.json';
    }
}
