<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner stub for Mailables.
 *
 * TODO: Scan mailables and return metadata.
 */
class MailScanner implements Scanner
{
    /**
     * Stub scan — no implementation yet.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        // TODO: Scan mailables directory and return metadata.

        return [
            'mailables' => [
                'count' => 0,
                'paths' => [],
            ],
        ];
    }
}
