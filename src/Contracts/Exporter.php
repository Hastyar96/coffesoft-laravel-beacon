<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Contracts;

use Coffessoft\LaravelBeacon\Context\Context;

/**
 * Contract for exporting context data to a specific format.
 */
interface Exporter
{
    /**
     * Export the given context.
     */
    public function export(Context $context): void;
}