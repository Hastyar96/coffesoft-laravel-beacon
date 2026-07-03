<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Form_requests.
 *
 * TODO: Scan form_requests and return metadata.
 */
class RequestScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan form_requests directory and return metadata.

        return [
            'form_requests' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
