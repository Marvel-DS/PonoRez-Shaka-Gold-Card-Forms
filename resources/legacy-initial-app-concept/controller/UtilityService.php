<?php
namespace PonoRez\SGCForms;

/**
 * UtilityService
 *
 * Provides utility functions for configuration management, logging, path resolution, and URL enforcement.
 */
class UtilityService
{
    /**
     * Load configuration from a config file.
     *
     * @param string $filePath Path to the config configuration file.
     * @return array Decoded configuration array.
     * @throws \RuntimeException If file cannot be read or config JSON is invalid.
     */
    public static function loadConfig(string $filePath): array
    {
        if (!file_exists($filePath) || !is_readable($filePath)) {
            throw new \RuntimeException("Configuration file not found or unreadable: {$filePath}");
        }
        $json = file_get_contents($filePath);
        if ($json === false) {
            throw new \RuntimeException("Failed to read configuration file: {$filePath}");
        }
        try {
            $config = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new \RuntimeException("Invalid config JSON in configuration file: {$filePath}. " . $e->getMessage());
        }
        return $config;
    }

    /**
     * Load and cache environment configuration from env.config.
     *
     * @return array The environment configuration.
     * @throws \RuntimeException If config is invalid or required keys are missing.
     */
    private static ?array $envCache = null;

    public static function loadEnvConfig(): array
    {
        if (self::$envCache !== null) {
            return self::$envCache;
        }

        $filePath = self::resolvePath('../env.config', __DIR__);
        $config = self::loadConfig($filePath);

        // Determine environment
        $envName = $config['environment'] ?? 'production';
        if (!isset($config[$envName]) || !is_array($config[$envName])) {
            throw new \RuntimeException("Invalid environment configuration for '{$envName}' in env.config");
        }

        $envConfig = $config[$envName];
        $requiredKeys = ['username', 'password', 'apiUrl'];

        if (!self::validateConfig($envConfig, $requiredKeys)) {
            throw new \RuntimeException(
                "Missing required keys in env.config for '{$envName}' environment. Required: " . implode(', ', $requiredKeys)
            );
        }

        self::$envCache = $envConfig;
        return $envConfig;
    }

    /**
     * Validate configuration array for required keys.
     *
     * @param array $config The configuration array.
     * @param array $requiredKeys List of required keys.
     * @return bool True if all required keys are present, false otherwise.
     */
    public static function validateConfig(array $config, array $requiredKeys): bool
    {
        foreach ($requiredKeys as $key) {
            if (!array_key_exists($key, $config)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Resolve a relative path to an absolute path.
     *
     * @param string $relativePath The relative path.
     * @param string|null $baseDir The base directory. If null, uses current directory.
     * @return string The resolved absolute path.
     */
    public static function resolvePath(string $relativePath, ?string $baseDir = null): string
    {
        $base = $baseDir ?? __DIR__;
        $absolute = realpath($base . DIRECTORY_SEPARATOR . $relativePath);
        if ($absolute === false) {
            // If realpath fails (e.g., file does not exist), manually resolve
            $absolute = rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . ltrim($relativePath, DIRECTORY_SEPARATOR);
        }
        return $absolute;
    }

    /**
     * Get the absolute path to the /partials directory (no trailing slash).
     *
     * This assumes the project structure:
     *   /controller/UtilityService.php
     *   /partials/...
     * relative to the same project root folder.
     *
     * @return string
     * @throws \RuntimeException if the partials directory cannot be found.
     */
    public static function partialsBaseDir(): string
    {
        // Resolve ../partials from the /controller folder
        $dir = self::resolvePath('../partials', __DIR__);
        if (!is_dir($dir)) {
            throw new \RuntimeException("Partials directory not found: {$dir}");
        }
        return rtrim($dir, DIRECTORY_SEPARATOR);
    }

    /**
     * Returns the fully qualified URL to the /assets/ folder relative to the application base path.
     *
     * This method detects the scheme and host from the current request, determines the app root
     * by traversing up from the given $formScriptPath, and appends /assets/ to it.
     *
     * @param string $formScriptPath The path to the form script file.
     * @return string The fully qualified URL to the /assets/ folder.
     */
    public static function getAssetsBaseUrl(string $formScriptPath): string
    {
        // Detect scheme
        if (!empty($_SERVER['REQUEST_SCHEME'])) {
            $scheme = $_SERVER['REQUEST_SCHEME'];
        } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $scheme = 'https';
        } else {
            $scheme = 'http';
        }
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $baseUrl = $scheme . '://' . $host;

        $documentRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', DIRECTORY_SEPARATOR);
        $formScriptRealPath = realpath($formScriptPath);
        if ($formScriptRealPath === false) {
            // Fallback to original path if realpath fails
            $formScriptRealPath = $formScriptPath;
        }

        // Get the relative path from document root to the form script
        if (strpos($formScriptRealPath, $documentRoot) === 0) {
            $relativePath = substr($formScriptRealPath, strlen($documentRoot));
        } else {
            $relativePath = $formScriptRealPath;
        }

        // Normalize to use forward slashes for URL
        $relativePath = str_replace(DIRECTORY_SEPARATOR, '/', $relativePath);

        // Find the position of '/SCG' folder in the relative path
        $pos = strpos($relativePath, '/SCG');
        if ($pos === false) {
            // If not found, fallback to root
            $scgPath = '';
        } else {
            $scgPath = substr($relativePath, 0, $pos + 4); // include '/SCG'
        }

        return rtrim($baseUrl, '/') . $scgPath . '/assets/';
    }

    /**
     * Resolve supplier image path relative to the supplier folder.
     *
     * Returns a fully qualified web URL for the supplier image.
     *
     * @param string $imagePath The image path to resolve.
     * @param string $formScriptPath The path to the form script file.
     * @return string The resolved supplier image URL.
     */
    public static function resolveSupplierImagePath(string $imagePath, string $formScriptPath): string
    {
        if ($imagePath === '') {
            return '';
        }
        if (strpos($imagePath, 'http://') === 0 || strpos($imagePath, 'https://') === 0) {
            return $imagePath;
        }
        $scheme = $_SERVER['REQUEST_SCHEME'] ?? 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $baseUrl = $scheme . '://' . $host;

        $formFolder = dirname($formScriptPath);
        $supplierFolder = dirname($formFolder);
        // Ensure supplierFolder starts with a slash for URL path
        if (substr($supplierFolder, 0, 1) !== '/') {
            $supplierFolder = '/' . $supplierFolder;
        }
        return rtrim($baseUrl, '/') . $supplierFolder . '/' . ltrim($imagePath, '/');
    }

    /**
     * Enforce pretty URLs by redirecting if the URL contains .php or query strings.
     *
     * @return void
     */
    public static function enforcePrettyUrl(): void
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        if (strpos($requestUri, '.php') !== false || strpos($requestUri, '?') !== false) {
            // Remove .php and query string
            $prettyUri = preg_replace('/\.php(\?.*)?/', '', $requestUri);
            // Remove trailing slash except for root
            if ($prettyUri !== '/' && substr($prettyUri, -1) === '/') {
                $prettyUri = rtrim($prettyUri, '/');
            }
            header("Location: {$prettyUri}", true, 301);
            exit;
        }
    }

    /**
     * Log a message to the error log or a specified file.
     *
     * @param string $message The message to log.
     * @param string|null $file Optional file path to log to. If null, uses PHP error log.
     * @return void
     */
    public static function log(string $message, ?string $file = null): void
    {
        $logEntry = '[' . date('Y-m-d H:i:s') . '] ' . $message . PHP_EOL;
        if ($file !== null) {
            file_put_contents($file, $logEntry, FILE_APPEND | LOCK_EX);
        } else {
            error_log($logEntry);
        }
    }

    /**
     * Get the fully qualified URL to the app root folder relative to the application base path.
     *
     * This method detects the scheme and host from the current request, and derives the app root dynamically
     * by comparing the controller directory (__DIR__) against DOCUMENT_ROOT, normalizing paths,
     * and then deriving the relative URL path from there.
     *
     * @return string The fully qualified URL to the app root folder with a trailing slash.
     */
    public static function getAppRoot(): string
    {
        // Detect scheme
        $scheme = (!empty($_SERVER['REQUEST_SCHEME']))
            ? $_SERVER['REQUEST_SCHEME']
            : ((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http');

        $host = $_SERVER['HTTP_HOST'] ?? '';
        $baseUrl = $scheme . '://' . $host;

        // Get document root and normalize to use forward slashes
        $documentRoot = rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

        // Get current directory (__DIR__) and normalize to use forward slashes
        $currentDir = str_replace('\\', '/', __DIR__);

        // Ensure currentDir starts with documentRoot
        if (strpos($currentDir, $documentRoot) === 0) {
            // Derive relative path from document root to current directory
            $relativePath = substr($currentDir, strlen($documentRoot));
        } else {
            // If currentDir is not inside documentRoot, fallback to empty string
            $relativePath = '';
        }

        // Normalize relative path to start with a slash
        if ($relativePath === '' || $relativePath[0] !== '/') {
            $relativePath = '/' . ltrim($relativePath, '/');
        }

        // Find the position of '/SCG' folder in the relative path
        $pos = strpos($relativePath, '/SCG');
        if ($pos === false) {
            // If not found, fallback to root '/'
            $appRootPath = '/';
        } else {
            // Include '/SCG' folder in the app root path
            $appRootPath = substr($relativePath, 0, $pos + 4);
        }

        // Ensure trailing slash
        if (substr($appRootPath, -1) !== '/') {
            $appRootPath .= '/';
        }

        // Return fully qualified URL with trailing slash
        return rtrim($baseUrl, '/') . $appRootPath;
    }
}
