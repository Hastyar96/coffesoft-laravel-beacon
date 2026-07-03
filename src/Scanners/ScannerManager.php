<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Context\Context;
use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Orchestrates multiple scanners and merges their results into a Context.
 *
 * Accepts an array of Scanner implementations and executes
 * all of them, merging each result into a Context object.
 */
class ScannerManager
{
    /**
     * @param array<int, Scanner> $scanners
     */
    public function __construct(
        private readonly array $scanners,
    ) {
    }

    /**
     * Execute all scanners and return a populated Context.
     */
    public function scan(): Context
    {
        $context = new Context();

        foreach ($this->scanners as $scanner) {
            $context->merge($scanner->scan());
        }

        return $context;
    }
}
