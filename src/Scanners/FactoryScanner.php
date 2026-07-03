<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Factories.
 *
 * TODO: Scan factories and return metadata.
 */
class FactoryScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan factories directory and return metadata.

        return [
            'factories' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
