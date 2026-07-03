<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Middleware.
 *
 * TODO: Scan middleware and return metadata.
 */
class MiddlewareScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan middleware directory and return metadata.

        return [
            'middleware' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
