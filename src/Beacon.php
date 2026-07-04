<?php

namespace Coffesoft\LaravelBeacon;

class Beacon
{
    /**
     * Package version.
     */
    public const VERSION = '1.0.0';

    /**
     * Get the package version.
     */
    public function version(): string
    {
        return self::VERSION;
    }
}