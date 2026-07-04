<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon;

class Beacon
{
    /**
     * Package version.
     */
    public const VERSION = '1.0.1';

    /**
     * Get the package version.
     */
    public function version(): string
    {
        return self::VERSION;
    }
}