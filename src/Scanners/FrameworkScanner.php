<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner that detects basic framework information.
 *
 * Returns the framework name, Laravel version, PHP version,
 * and the base path of the project.
 */
class FrameworkScanner implements Scanner
{
    /**
     * Scan the framework and return basic metadata.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        return [
            'framework' => 'laravel',
            'laravel_version' => app()->version(),
            'php_version' => PHP_VERSION,
            'base_path' => base_path(),
        ];
    }
}