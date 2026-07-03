<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Views.
 *
 * TODO: Scan views and return metadata.
 */
class ViewScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan views directory and return metadata.

        return [
            'views' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
