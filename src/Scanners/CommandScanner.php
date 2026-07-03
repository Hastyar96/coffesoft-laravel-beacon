<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Artisan_commands.
 *
 * TODO: Scan artisan_commands and return metadata.
 */
class CommandScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan artisan_commands directory and return metadata.

        return [
            'artisan_commands' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
