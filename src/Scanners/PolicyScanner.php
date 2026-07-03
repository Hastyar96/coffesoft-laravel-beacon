<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Policies.
 *
 * TODO: Scan policies and return metadata.
 */
class PolicyScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan policies directory and return metadata.

        return [
            'policies' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
