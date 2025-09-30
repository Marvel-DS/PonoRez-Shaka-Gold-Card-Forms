<?php

declare(strict_types=1);

namespace PonoRez\SGCForms;

use RuntimeException;

final class UtilityService
{
    private const ENV_CONFIG_PATH = '/config/env.config';
    private const SUPPLIERS_PATH = '/suppliers';

    private static ?array $envConfig = null;

    private function __construct()
    {
    }

    public static function projectRoot(): string
    {
        return defined('PONO_SGC_ROOT') ? PONO_SGC_ROOT : dirname(__DIR__);
    }

    public static function loadJsonConfig(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException(sprintf('Configuration file not found: %s', $path));
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException(sprintf('Unable to read configuration file: %s', $path));
        }

        $data = json_decode($contents, true);
        if (!is_array($data)) {
            throw new RuntimeException(sprintf('Invalid JSON config: %s', $path));
        }

        return $data;
    }

    public static function getEnvConfig(): array
    {
        if (self::$envConfig !== null) {
            return self::$envConfig;
        }

        $path = self::projectRoot() . self::ENV_CONFIG_PATH;
        $config = self::loadJsonConfig($path);

        if (!isset($config['environment'])) {
            throw new RuntimeException('Environment configuration missing "environment" key.');
        }

        self::$envConfig = $config;
        return $config;
    }

    public static function getCurrentEnvironment(): string
    {
        $envConfig = self::getEnvConfig();
        return (string) ($envConfig['environment'] ?? 'production');
    }

    public static function getEnvironmentSetting(string $key, mixed $default = null): mixed
    {
        $config = self::getEnvConfig();
        $environment = self::getCurrentEnvironment();
        $environmentConfig = $config[$environment] ?? [];
        return $environmentConfig[$key] ?? $default;
    }

    public static function getSoapWsdl(): string
    {
        $wsdl = self::getEnvironmentSetting('soapWsdl');
        if (!is_string($wsdl) || $wsdl === '') {
            throw new RuntimeException('soapWsdl not configured for the current environment.');
        }
        return $wsdl;
    }

    public static function loadSupplierConfig(string $supplierSlug): array
    {
        $path = self::supplierDirectory($supplierSlug) . '/supplier.config';
        $config = self::loadJsonConfig($path);

        $rawCredentials = $config['soapCredentials'] ?? $config['soapCredetials'] ?? null;

        if (!is_array($rawCredentials) || !isset($rawCredentials['username'], $rawCredentials['password'])) {
            throw new RuntimeException(sprintf('Supplier config missing SOAP credentials: %s', $supplierSlug));
        }

        $config['soapCredentials'] = [
            'username' => (string) $rawCredentials['username'],
            'password' => (string) $rawCredentials['password'],
        ];

        unset($config['soapCredetials']);

        return $config;
    }

    public static function loadActivityConfig(string $supplierSlug, string $activitySlug): array
    {
        $path = self::supplierDirectory($supplierSlug) . '/' . $activitySlug . '.config';
        $config = self::loadJsonConfig($path);

        if (!isset($config['activityId'])) {
            throw new RuntimeException(sprintf('Activity config missing activityId: %s/%s', $supplierSlug, $activitySlug));
        }

        return $config;
    }

    public static function getReservationBaseUrl(): string
    {
        $wsdl = self::getSoapWsdl();
        $parts = parse_url($wsdl);

        if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
            throw new RuntimeException('Unable to derive reservation base URL from soapWsdl.');
        }

        $host = $parts['host'];
        if (isset($parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        $path = $parts['path'] ?? '/';
        $path = preg_replace('#/services/.*$#', '/', $path) ?? '/';
        $path = rtrim($path, '/') . '/';

        return sprintf('%s://%s%s', $parts['scheme'], $host, $path);
    }

    public static function supplierDirectory(string $supplierSlug): string
    {
        return rtrim(self::projectRoot() . self::SUPPLIERS_PATH, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $supplierSlug;
    }
}
