<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Events.
 *
 * TODO: Scan events and return metadata.
 */
class EventScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan events directory and return metadata.

        return [
            'events' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
