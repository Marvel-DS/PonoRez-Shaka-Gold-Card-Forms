<?php

declare(strict_types=1);

namespace PonoRez\SGCForms\Support;

use InvalidArgumentException;
use Throwable;

final class ErrorHandler
{
    private function __construct()
    {
    }

    public static function handle(Throwable $exception): void
    {
        if ($exception instanceof InvalidArgumentException) {
            ResponseFormatter::error($exception->getMessage(), 400);
            return;
        }

        ResponseFormatter::error($exception->getMessage() ?: 'Internal server error', 500);
    }
}
