<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Blade_componentss.
 *
 * TODO: Scan blade_components and return metadata.
 */
class BladeComponentScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan blade_components directory and return metadata.

        return [
            'blade_components' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
