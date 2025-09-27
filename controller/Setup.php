<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use PonoRez\SGCForms\Support\LogManager;

if (defined('PONO_SGC_SETUP_LOADED')) {
    return;
}

define('PONO_SGC_SETUP_LOADED', true);

$projectRoot = dirname(__DIR__);

if (!defined('PONO_SGC_ROOT')) {
    define('PONO_SGC_ROOT', $projectRoot);
}

$autoload = $projectRoot . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    throw new RuntimeException('Composer autoload file not found. Run composer install.');
}

require_once $autoload;

if (class_exists(Dotenv::class) && file_exists($projectRoot . '/.env')) {
    Dotenv::createImmutable($projectRoot)->safeLoad();
}

if (!ini_get('date.timezone')) {
    date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'UTC');
}

foreach (['cache', 'logs'] as $dirName) {
    $dirPath = $projectRoot . DIRECTORY_SEPARATOR . $dirName;
    if (!is_dir($dirPath) && !mkdir($dirPath, 0775, true) && !is_dir($dirPath)) {
        throw new RuntimeException(sprintf('Unable to create required directory: %s', $dirPath));
    }
}

$logFile = $projectRoot . '/logs/app.log';
$logLevelName = strtoupper($_ENV['APP_LOG_LEVEL'] ?? 'DEBUG');

try {
    $logLevel = Level::fromName($logLevelName);
} catch (Throwable $exception) {
    $logLevel = Level::Debug;
}

$logger = new Logger($_ENV['APP_LOG_CHANNEL'] ?? 'app');
$logger->pushHandler(new StreamHandler($logFile, $logLevel));

if (class_exists(LogManager::class)) {
    LogManager::setDefaultLogger($logger);
}
