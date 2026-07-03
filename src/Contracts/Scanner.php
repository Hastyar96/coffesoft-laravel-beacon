<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Contracts;

/**
 * Contract for scanning Laravel project information.
 */
interface Scanner
{
    /**
     * Scan the Laravel project and return structured data.
     *
     * @return array<string, mixed>
     */
    public function scan(): array;
}