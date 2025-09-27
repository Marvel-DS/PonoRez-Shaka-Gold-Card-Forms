<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Support;

use Monolog\Logger;

final class LogManager
{
    private static ?Logger $defaultLogger = null;

    private function __construct()
    {
    }

    public static function setDefaultLogger(Logger $logger): void
    {
        self::$defaultLogger = $logger;
    }

    public static function getLogger(?string $channel = null): Logger
    {
        if (self::$defaultLogger === null) {
            self::$defaultLogger = new Logger($channel ?? 'app');
        }

        if ($channel === null || self::$defaultLogger->getName() === $channel) {
            return self::$defaultLogger;
        }

        return self::$defaultLogger->withName($channel);
    }
}
