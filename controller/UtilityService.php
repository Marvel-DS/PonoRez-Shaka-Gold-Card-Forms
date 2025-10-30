<?php

declare(strict_types=1);

namespace PonoRez\SGCForms;

use PonoRez\SGCForms\Cache\CacheInterface;
use PonoRez\SGCForms\Cache\FileCache;
use PonoRez\SGCForms\Cache\NullCache;
use RuntimeException;
use Throwable;

final class UtilityService
{
    private const ENV_CONFIG_PATH = '/config/env.config';
    private const SUPPLIERS_PATH = '/suppliers';

    private static ?array $envConfig = null;

    private function __construct()
    {
    }

    /** @var array<string,string|null> */
    private static array $iconCache = [];

    public static function projectRoot(): string
    {
        return defined('PONO_SGC_ROOT') ? PONO_SGC_ROOT : dirname(__DIR__);
    }

    public static function createCache(string $relativePath): CacheInterface
    {
        $normalized = trim(str_replace('\\', '/', $relativePath));
        if ($normalized === '') {
            return new NullCache();
        }

        $normalized = ltrim($normalized, '/');
        $directory = self::projectRoot() . '/' . $normalized;

        try {
            return new FileCache($directory);
        } catch (Throwable $exception) {
            error_log(sprintf(
                '[SGC Forms] Cache disabled for %s: %s',
                $normalized,
                $exception->getMessage()
            ));
            return new NullCache();
        }
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

    public static function resolvePonorezBaseUrl(?array $activityConfig = null, ?array $supplierConfig = null): string
    {
        $candidates = [];

        if (is_array($activityConfig)) {
            $candidates[] = $activityConfig['ponorezBaseUrl'] ?? null;
        }

        if (is_array($supplierConfig)) {
            $candidates[] = $supplierConfig['ponorezBaseUrl'] ?? null;
        }

        $candidates[] = self::getEnvironmentSetting('ponorezBaseUrl', null);
        $candidates[] = 'https://ponorez.online/reservation/';

        foreach ($candidates as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $trimmed = trim($candidate);
            if ($trimmed === '') {
                continue;
            }

            return rtrim($trimmed, '/') . '/';
        }

        return 'https://ponorez.online/reservation/';
    }

    public static function shouldDisableSoapCertificateVerification(): bool
    {
        foreach ([
            getenv('PONO_SGC_DISABLE_SOAP_SSL_VERIFY') ?: null,
            self::getEnvironmentSetting('disableSoapSslVerification', null),
        ] as $source) {
            $bool = self::boolOrNull($source);
            if ($bool !== null) {
                return $bool;
            }
        }

        return false;
    }

    public static function shouldDisableAvailabilityCertificateVerification(): bool
    {
        foreach ([
            getenv('PONO_SGC_DISABLE_AVAILABILITY_SSL_VERIFY') ?: null,
            self::getEnvironmentSetting('disableAvailabilitySslVerification', null),
        ] as $source) {
            $bool = self::boolOrNull($source);
            if ($bool !== null) {
                return $bool;
            }
        }

        return false;
    }

    public static function getTrustedCaBundlePath(): ?string
    {
        foreach ([
            getenv('PONO_SGC_CA_BUNDLE') ?: null,
            self::getEnvironmentSetting('trustedCaBundle', null),
        ] as $candidate) {
            if (!is_string($candidate)) {
                continue;
            }

            $path = trim($candidate);
            if ($path === '') {
                continue;
            }

            if ($path[0] === '~') {
                $home = getenv('HOME');
                if ($home !== false && $home !== '') {
                    $path = rtrim($home, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim(substr($path, 1), DIRECTORY_SEPARATOR);
                }
            }

            if (is_file($path)) {
                return $path;
            }
        }

        return null;
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

        if (!isset($config['slug']) || !is_string($config['slug']) || trim($config['slug']) === '') {
            $config['slug'] = $activitySlug;
        }

        return self::normalizeActivityConfig($config, $supplierSlug, $activitySlug);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    private static function normalizeActivityConfig(array $config, string $supplierSlug, string $activitySlug): array
    {
        $guestTypes = self::normaliseGuestTypes($config['guestTypes'] ?? []);
        $config['guestTypes'] = [
            'collection' => $guestTypes['collection'],
            'byId' => $guestTypes['byId'],
        ];

        $activities = self::normaliseActivities($config['activities'] ?? [], $config, $supplierSlug, $activitySlug);
        $config['activities'] = [
            'collection' => $activities['collection'],
            'byId' => $activities['byId'],
        ];
        $config['primaryActivityId'] = $activities['primaryId'];
        $config['activityIds'] = $activities['ids'];
        $config['activityId'] = $activities['primaryId'];
        $config['departureLabels'] = $activities['departureLabels'];

        $config['timezone'] = self::resolveConfigTimezone($config);

        $showInfoColumn = $config['infoBlocks']['showInfoColumn'] ?? null;
        $config['showInfoColumn'] = $showInfoColumn === null ? true : (bool) $showInfoColumn;
        $config['infoBlocks']['showInfoColumn'] = $config['showInfoColumn'];

        if (array_key_exists('shakaGoldCardNumber', $config)) {
            $config['shakaGoldCardNumber'] = self::stringOrNull($config['shakaGoldCardNumber']);
        } else {
            $config['shakaGoldCardNumber'] = null;
        }

        $config['disableUpgrades'] = filter_var(
            $config['disableUpgrades'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        return $config;
    }

    private static function boolOrNull(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) $value;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                return null;
            }

            if (in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
                return false;
            }
        }

        return null;
    }

    /**
     * @param array<string, mixed> $activityConfig
     * @return array<int, array{
     *     id: string,
     *     label: string,
     *     labelSource: string,
     *     description: ?string,
     *     descriptionSource: string,
     *     price: ?float,
     *     minQuantity: int,
     *     maxQuantity: ?int
     * }>
     */
    public static function getGuestTypes(array $activityConfig): array
    {
        $guestTypes = $activityConfig['guestTypes']['collection'] ?? [];

        if (!is_array($guestTypes)) {
            return [];
        }

        $normalized = [];
        foreach ($guestTypes as $guestType) {
            if (!is_array($guestType) || !isset($guestType['id'])) {
                continue;
            }

            $id = (string) $guestType['id'];
            if ($id === '') {
                continue;
            }

            $rawLabel = self::stringOrNull($guestType['label'] ?? null);
            $label = $rawLabel ?? $id;
            $labelSource = isset($guestType['labelSource']) && is_string($guestType['labelSource'])
                ? $guestType['labelSource']
                : ($rawLabel === null ? 'fallback' : 'config');

            $rawDescription = self::stringOrNull($guestType['description'] ?? null);
            $description = $rawDescription;
            $descriptionSource = isset($guestType['descriptionSource']) && is_string($guestType['descriptionSource'])
                ? $guestType['descriptionSource']
                : ($rawDescription === null ? 'fallback' : 'config');

            $price = null;
            if (isset($guestType['price']) && $guestType['price'] !== null && $guestType['price'] !== '') {
                $price = is_numeric($guestType['price']) ? (float) $guestType['price'] : null;
            }

            $minQuantity = isset($guestType['minQuantity']) ? max(0, (int) $guestType['minQuantity']) : 0;
            $maxQuantity = null;
            if (array_key_exists('maxQuantity', $guestType) && $guestType['maxQuantity'] !== null && $guestType['maxQuantity'] !== '') {
                $maxQuantity = max($minQuantity, (int) $guestType['maxQuantity']);
            }

            $normalized[] = [
                'id' => $id,
                'label' => $label,
                'labelSource' => $labelSource,
                'description' => $description,
                'descriptionSource' => $descriptionSource,
                'price' => $price,
                'minQuantity' => $minQuantity,
                'maxQuantity' => $maxQuantity,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $activityConfig
     * @return array<string, array{
     *     id: string,
     *     label: string,
     *     labelSource: string,
     *     description: ?string,
     *     descriptionSource: string,
     *     price: ?float,
     *     minQuantity: int,
     *     maxQuantity: ?int
     * }>
     */
    public static function getGuestTypesById(array $activityConfig): array
    {
        $guestTypes = $activityConfig['guestTypes']['byId'] ?? [];

        if (!is_array($guestTypes)) {
            return [];
        }

        $normalized = [];
        foreach ($guestTypes as $key => $guestType) {
            if (!is_array($guestType) || !isset($guestType['id'])) {
                continue;
            }

            $id = (string) $guestType['id'];
            if ($id === '') {
                continue;
            }

            $rawLabel = self::stringOrNull($guestType['label'] ?? null);
            $labelSource = isset($guestType['labelSource']) && is_string($guestType['labelSource'])
                ? $guestType['labelSource']
                : ($rawLabel === null ? 'fallback' : 'config');

            $rawDescription = self::stringOrNull($guestType['description'] ?? null);
            $descriptionSource = isset($guestType['descriptionSource']) && is_string($guestType['descriptionSource'])
                ? $guestType['descriptionSource']
                : ($rawDescription === null ? 'fallback' : 'config');

            $normalized[$id] = [
                'id' => $id,
                'label' => $rawLabel ?? $id,
                'labelSource' => $labelSource,
                'description' => $rawDescription,
                'descriptionSource' => $descriptionSource,
                'price' => isset($guestType['price']) && is_numeric($guestType['price']) ? (float) $guestType['price'] : null,
                'minQuantity' => isset($guestType['minQuantity']) ? max(0, (int) $guestType['minQuantity']) : 0,
                'maxQuantity' => isset($guestType['maxQuantity']) && $guestType['maxQuantity'] !== null && $guestType['maxQuantity'] !== ''
                    ? max(0, (int) $guestType['maxQuantity'])
                    : null,
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $activityConfig
     * @return array<int, array{id:int|string,label:?string,primary:bool,source:string}>
     */
    public static function getActivities(array $activityConfig): array
    {
        $activities = $activityConfig['activities']['collection']
            ?? $activityConfig['activities']
            ?? [];

        if (!is_array($activities)) {
            return [];
        }

        $normalized = [];
        foreach ($activities as $activity) {
            if (!is_array($activity) || !isset($activity['id'])) {
                continue;
            }

            $id = self::normaliseActivityId($activity['id']);
            if ($id === null) {
                continue;
            }

            $normalized[] = [
                'id' => $id,
                'label' => self::stringOrNull($activity['label'] ?? null),
                'primary' => (bool) ($activity['primary'] ?? false),
                'source' => isset($activity['source']) && is_string($activity['source']) ? $activity['source'] : 'unknown',
            ];
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $activityConfig
     * @return array<int, int|string>
     */
    public static function getActivityIds(array $activityConfig): array
    {
        $ids = $activityConfig['activityIds'] ?? null;
        if (!is_array($ids)) {
            $ids = [];
        }

        $normalized = [];
        foreach ($ids as $id) {
            $normalised = self::normaliseActivityId($id);
            if ($normalised !== null) {
                $normalized[] = $normalised;
            }
        }

        return $normalized;
    }

    public static function getPrimaryActivityId(array $activityConfig): int|string|null
    {
        $primary = $activityConfig['primaryActivityId'] ?? $activityConfig['activityId'] ?? null;
        return self::normaliseActivityId($primary);
    }

    /**
     * @param array<string, mixed> $activityConfig
     * @return array<string, string>
     */
    public static function getDepartureLabels(array $activityConfig): array
    {
        $labels = $activityConfig['departureLabels'] ?? [];
        if (!is_array($labels)) {
            return [];
        }

        $normalized = [];
        foreach ($labels as $key => $label) {
            $stringKey = (string) $key;
            $stringLabel = self::stringOrNull($label);
            if ($stringKey === '' || $stringLabel === null) {
                continue;
            }
            $normalized[$stringKey] = $stringLabel;
        }

        return $normalized;
    }

    public static function getActivityTimezone(array $activityConfig): string
    {
        $timezone = $activityConfig['timezone'] ?? $activityConfig['calendar']['timezone'] ?? null;
        $timezone = self::stringOrNull($timezone);

        if ($timezone !== null && self::isValidTimezone($timezone)) {
            return $timezone;
        }

        if ($timezone !== null) {
            $alias = self::timezoneAliasMap()[$timezone] ?? null;
            if ($alias !== null && self::isValidTimezone($alias)) {
                return $alias;
            }
        }

        $default = date_default_timezone_get();
        return self::isValidTimezone($default) ? $default : 'UTC';
    }

    public static function shouldShowInfoColumn(array $activityConfig): bool
    {
        if (isset($activityConfig['showInfoColumn'])) {
            return (bool) $activityConfig['showInfoColumn'];
        }

        if (isset($activityConfig['infoBlocks']['showInfoColumn'])) {
            return (bool) $activityConfig['infoBlocks']['showInfoColumn'];
        }

        return true;
    }

    public static function getShakaGoldCardNumber(array $activityConfig): ?string
    {
        if (!array_key_exists('shakaGoldCardNumber', $activityConfig)) {
            return null;
        }

        return self::stringOrNull($activityConfig['shakaGoldCardNumber']);
    }

    /**
     * @param array<int|string, mixed> $value
     * @return array{
     *     collection: array<int, array{
     *         id: string,
     *         label: string,
     *         labelSource: string,
     *         description: ?string,
     *         descriptionSource: string,
     *         price: ?float,
     *         minQuantity: int,
     *         maxQuantity: ?int
     *     }>,
     *     byId: array<string, array{
     *         id: string,
     *         label: string,
     *         labelSource: string,
     *         description: ?string,
     *         descriptionSource: string,
     *         price: ?float,
     *         minQuantity: int,
     *         maxQuantity: ?int
     *     }>
     * }
     */
    private static function normaliseGuestTypes(mixed $value): array
    {
        $collection = [];
        $byId = [];

        if (!is_array($value)) {
            return [
                'collection' => $collection,
                'byId' => $byId,
            ];
        }

        foreach ($value as $guestType) {
            if (!is_array($guestType)) {
                continue;
            }

            $id = self::stringOrNull($guestType['id'] ?? null);
            if ($id === null || $id === '') {
                continue;
            }

            $rawLabel = self::stringOrNull($guestType['label'] ?? null);
            $label = $rawLabel ?? $id;
            $labelSource = $rawLabel === null ? 'fallback' : 'config';

            $rawDescription = self::stringOrNull($guestType['description'] ?? null);
            $descriptionSource = $rawDescription === null ? 'fallback' : 'config';

            $price = null;
            if (isset($guestType['price']) && $guestType['price'] !== '') {
                $price = is_numeric($guestType['price']) ? (float) $guestType['price'] : null;
            }

            $minCandidate = $guestType['minQuantity'] ?? $guestType['min'] ?? 0;
            $maxCandidate = $guestType['maxQuantity'] ?? $guestType['max'] ?? null;

            $minQuantity = max(0, (int) $minCandidate);
            $maxQuantity = null;
            if ($maxCandidate !== null && $maxCandidate !== '') {
                $maxQuantity = max($minQuantity, (int) $maxCandidate);
            }

            $entry = [
                'id' => $id,
                'label' => $label,
                'labelSource' => $labelSource,
                'description' => $rawDescription,
                'descriptionSource' => $descriptionSource,
                'price' => $price,
                'minQuantity' => $minQuantity,
                'maxQuantity' => $maxQuantity,
            ];

            $collection[] = $entry;
            $byId[$id] = $entry;
        }

        return [
            'collection' => $collection,
            'byId' => $byId,
        ];
    }

    /**
     * @param array<int|string, mixed> $value
     * @param array<string, mixed> $config
     * @return array{
     *     collection: array<int, array{id:int|string,label:?string,primary:bool,source:string}>,
     *     byId: array<string, array{id:int|string,label:?string,primary:bool,source:string}>,
     *     ids: array<int, int|string>,
     *     primaryId: int|string,
     *     departureLabels: array<string, string>
     * }
     */
    private static function normaliseActivities(
        mixed $value,
        array $config,
        string $supplierSlug,
        string $activitySlug
    ): array {
        $collection = [];
        $byId = [];
        $ids = [];
        $departureLabels = [];

        $legacyPrimary = self::normaliseActivityId($config['activityId'] ?? null);
        $legacyIds = [];
        if (isset($config['activityIds']) && is_array($config['activityIds'])) {
            foreach ($config['activityIds'] as $legacyId) {
                $normalised = self::normaliseActivityId($legacyId);
                if ($normalised !== null) {
                    $legacyIds[] = $normalised;
                }
            }
        }

        if (is_array($value)) {
            foreach ($value as $activity) {
                if (!is_array($activity)) {
                    continue;
                }

                $id = self::normaliseActivityId($activity['id'] ?? null);
                if ($id === null) {
                    continue;
                }

                $label = self::stringOrNull($activity['label'] ?? null);
                $isPrimary = ($activity['primary'] ?? false) ? true : false;

                if ($isPrimary) {
                    $legacyPrimary = $id;
                }

                $entry = [
                    'id' => $id,
                    'label' => $label,
                    'primary' => $isPrimary,
                    'source' => 'config',
                ];

                $collection[] = $entry;
                $byId[(string) $id] = $entry;
                $ids[] = $id;

                if ($label !== null) {
                    $departureLabels[(string) $id] = $label;
                }
            }
        }

        foreach ($legacyIds as $legacyId) {
            if (!in_array($legacyId, $ids, true)) {
                $ids[] = $legacyId;
                if (!isset($byId[(string) $legacyId])) {
                    $byId[(string) $legacyId] = [
                        'id' => $legacyId,
                        'label' => null,
                        'primary' => false,
                        'source' => 'legacy',
                    ];
                }
            }
        }

        $ids = array_values(array_unique($ids, SORT_REGULAR));

        if ($legacyPrimary === null && $ids !== []) {
            $legacyPrimary = $ids[0];
        }

        if ($legacyPrimary === null) {
            throw new RuntimeException(sprintf(
                'Activity config missing activity identifier: %s/%s',
                $supplierSlug,
                $activitySlug
            ));
        }

        $primaryKey = (string) $legacyPrimary;
        if (isset($byId[$primaryKey])) {
            $byId[$primaryKey]['primary'] = true;
        } else {
            $byId[$primaryKey] = [
                'id' => $legacyPrimary,
                'label' => null,
                'primary' => true,
                'source' => 'derived',
            ];
        }

        $collection = array_values(array_map(static function (array $entry) use ($primaryKey) {
            $entry['primary'] = (string) $entry['id'] === $primaryKey;
            return $entry;
        }, $byId));

        return [
            'collection' => $collection,
            'byId' => $byId,
            'ids' => $ids,
            'primaryId' => $legacyPrimary,
            'departureLabels' => $departureLabels,
        ];
    }

    private static function resolveConfigTimezone(array $config): string
    {
        $timezone = $config['timezone'] ?? $config['calendar']['timezone'] ?? null;
        $timezone = self::stringOrNull($timezone);

        if ($timezone !== null && self::isValidTimezone($timezone)) {
            return $timezone;
        }

        if ($timezone !== null) {
            $alias = self::timezoneAliasMap()[$timezone] ?? null;
            if ($alias !== null && self::isValidTimezone($alias)) {
                return $alias;
            }
        }

        $default = date_default_timezone_get();
        return self::isValidTimezone($default) ? $default : 'UTC';
    }

    private static function isValidTimezone(?string $timezone): bool
    {
        if ($timezone === null || $timezone === '') {
            return false;
        }

        return in_array($timezone, timezone_identifiers_list(), true);
    }

    /**
     * @return array<string, string>
     */
    private static function timezoneAliasMap(): array
    {
        return [
            'Hawaii' => 'Pacific/Honolulu',
            'HST' => 'Pacific/Honolulu',
            'Pacific/Hawaii' => 'Pacific/Honolulu',
        ];
    }

    private static function normaliseActivityId(mixed $value): int|string|null
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            $trimmed = trim($value);
            if ($trimmed === '') {
                return null;
            }

            if (ctype_digit($trimmed)) {
                $int = (int) $trimmed;
                return $int >= 0 ? $int : null;
            }

            return $trimmed;
        }

        if (is_float($value)) {
            $int = (int) $value;
            return $int >= 0 ? $int : null;
        }

        return null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);
            return $trimmed === '' ? null : $trimmed;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    public static function formatSupplierContent(?string $content, array $supplier = []): string
    {
        $text = self::stringOrNull($content);
        if ($text === null) {
            return '';
        }

        $replacements = [
            '[p]' => '<p>',
            '[/p]' => '</p>',
            '[b]' => '<strong>',
            '[/b]' => '</strong>',
        ];

        $text = str_replace(array_keys($replacements), array_values($replacements), $text);

        $links = $supplier['links'] ?? [];
        $faqUrl = self::stringOrNull($links['faq'] ?? null);
        $termsUrl = self::stringOrNull($links['terms'] ?? null);

        $faqReplacement = $faqUrl !== null
            ? sprintf(
                '<a href="%s" target="_blank" rel="noopener">FAQ</a>',
                htmlspecialchars($faqUrl, ENT_QUOTES, 'UTF-8')
            )
            : 'FAQ';

        $termsReplacement = $termsUrl !== null
            ? sprintf(
                '<a href="%s" target="_blank" rel="noopener">Terms &amp; Conditions</a>',
                htmlspecialchars($termsUrl, ENT_QUOTES, 'UTF-8')
            )
            : 'Terms &amp; Conditions';

        $text = str_replace('[FAQ]', $faqReplacement, $text);
        $text = str_replace('[TERMS]', $termsReplacement, $text);

        $allowedTags = '<p><br><strong><em><ul><ol><li><a>';
        $sanitized = strip_tags($text, $allowedTags);
        $sanitized = preg_replace('/javascript\s*:/i', '', $sanitized ?? '') ?? '';
        $sanitized = preg_replace('#<p>\s*</p>#', '', $sanitized) ?? '';

        return trim($sanitized);
    }

    public static function getPublicBasePath(): string
    {
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '';

        if (!is_string($scriptName) || $scriptName === '') {
            return '/';
        }

        $scriptName = str_replace('\\', '/', $scriptName);

        if ($scriptName === '') {
            return '/';
        }

        $directory = str_replace('\\', '/', dirname($scriptName));
        $directory = rtrim($directory, '/');

        if ($directory === '' || $directory === '.' || $directory === DIRECTORY_SEPARATOR) {
            return '/';
        }

        if ($directory[0] !== '/') {
            $directory = '/' . $directory;
        }

        return rtrim($directory, '/') . '/';
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

    /**
     * Load an SVG icon and optionally override class and stroke width attributes.
     *
     * @param string $path        Relative path to the SVG (e.g. "assets/icons/chevron-left.svg").
     * @param string $class       Classes to apply to the <svg> element.
     * @param string $strokeWidth Stroke width override applied when provided.
     */
    public static function renderSvgIcon(string $path, string $class = '', string $strokeWidth = ''): string
    {
        $relative = ltrim($path, '/');

        $searchPaths = [
            self::projectRoot() . '/' . $relative,
            self::projectRoot() . '/assets/icons/' . $relative,
            self::projectRoot() . '/public/assets/icons/' . $relative,
        ];

        // Support deployments that move the application into an "app/public" folder.
        $appPublic = self::projectRoot() . '/app/public';
        if (is_dir($appPublic)) {
            $searchPaths[] = $appPublic . '/' . ltrim($relative, '/');
            $searchPaths[] = $appPublic . '/assets/icons/' . $relative;
        }

        $fullPath = null;
        foreach ($searchPaths as $candidate) {
            if (is_file($candidate)) {
                $fullPath = $candidate;
                break;
            }
        }

        if ($fullPath === null) {
            return '';
        }

        if (!array_key_exists($fullPath, self::$iconCache)) {
            self::$iconCache[$fullPath] = file_get_contents($fullPath) ?: null;
        }

        $svg = self::$iconCache[$fullPath];
        if ($svg === null) {
            return '';
        }

        if ($class !== '') {
            $escapedClass = htmlspecialchars($class, ENT_QUOTES, 'UTF-8');
            if (preg_match('/class="[^"]*"/', $svg)) {
                $svg = preg_replace('/class="[^"]*"/', 'class="' . $escapedClass . '"', $svg, 1);
            } else {
                $svg = preg_replace('/<svg\b/', '<svg class="' . $escapedClass . '"', $svg, 1);
            }
        }

        if ($strokeWidth !== '') {
            $escapedStroke = htmlspecialchars($strokeWidth, ENT_QUOTES, 'UTF-8');
            if (preg_match('/stroke-width="[^"]*"/', $svg)) {
                $svg = preg_replace('/stroke-width="[^"]*"/', 'stroke-width="' . $escapedStroke . '"', $svg, 1);
            } else {
                $svg = preg_replace('/<svg\b/', '<svg stroke-width="' . $escapedStroke . '"', $svg, 1);
            }
        }

        return $svg;
    }

    /**
     * Return a list of gallery images for the requested supplier/activity.
     *
     * The method prefers an explicit `gallery` configuration array (string paths or
     * arrays with `src`/`alt`) but falls back to scanning the supplier's `images`
     * directory. File names containing the activity slug or display name are
     * prioritised; if none are found the entire directory is used. Results are
     * returned as web paths rooted at `/suppliers/...`.
     *
     * @param string $supplierSlug
     * @param array<string,mixed> $activityConfig
     * @return array<int,array{src:string,alt:string}>
     */
    public static function getActivityGalleryImages(string $supplierSlug, array $activityConfig): array
    {
        $supplierDir = self::supplierDirectory($supplierSlug);
        $imagesDir = $supplierDir . DIRECTORY_SEPARATOR . 'images';

        $displayName = (string) ($activityConfig['displayName'] ?? '');
        $activitySlug = (string) ($activityConfig['slug'] ?? '');

        $results = [];

        $configured = $activityConfig['gallery'] ?? null;
        if (is_array($configured) && $configured !== []) {
            foreach ($configured as $entry) {
                if (is_string($entry) && $entry !== '') {
                    $results[] = [
                        'src' => self::normalizeSupplierAssetPath($supplierSlug, $entry),
                        'alt' => $displayName !== '' ? $displayName : basename($entry),
                    ];
                    continue;
                }

                if (is_array($entry) && isset($entry['src']) && is_string($entry['src']) && $entry['src'] !== '') {
                    $results[] = [
                        'src' => self::normalizeSupplierAssetPath($supplierSlug, $entry['src']),
                        'alt' => isset($entry['alt']) && is_string($entry['alt']) && $entry['alt'] !== ''
                            ? $entry['alt']
                            : ($displayName !== '' ? $displayName : basename($entry['src'])),
                    ];
                }
            }
        }

        if ($results !== []) {
            return $results;
        }

        if (!is_dir($imagesDir)) {
            return [];
        }

        $files = glob($imagesDir . DIRECTORY_SEPARATOR . '*.{jpg,jpeg,png,webp}', GLOB_BRACE);
        if ($files === false || $files === []) {
            return [];
        }

        $normalizedSlug = self::normalizeGalleryKey($activitySlug);
        $normalizedDisplay = self::normalizeGalleryKey($displayName);

        $matched = [];
        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $filename = pathinfo($file, PATHINFO_FILENAME) ?? '';
            $normalizedFilename = self::normalizeGalleryKey((string) $filename);

            $isMatch = false;
            if ($normalizedSlug !== '' && str_contains($normalizedFilename, $normalizedSlug)) {
                $isMatch = true;
            }

            if (!$isMatch && $normalizedDisplay !== '' && str_contains($normalizedFilename, $normalizedDisplay)) {
                $isMatch = true;
            }

            if ($isMatch) {
                $matched[] = $file;
            }
        }

        if ($matched === []) {
            return [];
        }

        natsort($matched);

        foreach ($matched as $file) {
            $basename = basename($file);
            $results[] = [
                'src' => self::resolveSupplierAssetUrl($supplierSlug, 'images/' . $basename),
                'alt' => $displayName !== '' ? $displayName : $basename,
            ];
        }

        return array_values($results);
    }

    public static function resolveSupplierAssetUrl(string $supplierSlug, string $path): string
    {
        return self::normalizeSupplierAssetPath($supplierSlug, $path);
    }

    private static function normalizeSupplierAssetPath(string $supplierSlug, string $path): string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $trimmed = ltrim($path, '/');
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, 'suppliers/')) {
            return self::getPublicBasePath() . $trimmed;
        }

        return self::getPublicBasePath() . 'suppliers/' . $supplierSlug . '/' . $trimmed;
    }

    private static function normalizeGalleryKey(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $normalized = strtolower($value);
        $normalized = preg_replace('/[^a-z0-9]+/i', '-', $normalized) ?? '';

        return trim($normalized, '-');
    }
}
