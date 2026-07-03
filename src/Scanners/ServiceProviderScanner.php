<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Service_providerss.
 *
 * TODO: Scan service_providers and return metadata.
 */
class ServiceProviderScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan service_providers directory and return metadata.

        return [
            'service_providers' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
