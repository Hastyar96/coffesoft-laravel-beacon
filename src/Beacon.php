<?php

namespace Coffessoft\LaravelBeacon;

class Beacon
{
    /**
     * Package version.
     */
    public const VERSION = '0.1.0';

    /**
     * Get the package version.
     */
    public function version(): string
    {
        return self::VERSION;
    }
}