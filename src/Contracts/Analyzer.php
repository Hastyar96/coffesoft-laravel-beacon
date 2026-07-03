<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Contracts;

use Coffessoft\LaravelBeacon\Context\Context;

/**
 * Contract for analyzing scanned context data.
 */
interface Analyzer
{
    /**
     * Analyze the given context and return the enriched context.
     */
    public function analyze(Context $context): Context;
}