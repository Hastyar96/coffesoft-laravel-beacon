<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Api_resources.
 *
 * TODO: Scan api_resources and return metadata.
 */
class ResourceScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan api_resources directory and return metadata.

        return [
            'api_resources' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
