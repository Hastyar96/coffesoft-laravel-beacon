<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Seeders.
 *
 * TODO: Scan seeders and return metadata.
 */
class SeederScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan seeders directory and return metadata.

        return [
            'seeders' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
