<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Analyzers;

use Coffessoft\LaravelBeacon\Context\Context;
use Coffessoft\LaravelBeacon\Contracts\Analyzer;

/**
 * Orchestrates multiple analyzers to enrich a Context object.
 *
 * Each analyzer receives the same Context and returns
 * the enriched version, which is passed to the next analyzer.
 */
class AnalyzerManager
{
    /**
     * @param array<int, Analyzer> $analyzers
     */
    public function __construct(
        private readonly array $analyzers,
    ) {
    }

    /**
     * Run all analyzers against the given context.
     */
    public function analyze(Context $context): Context
    {
        foreach ($this->analyzers as $analyzer) {
            $context = $analyzer->analyze($context);
        }

        return $context;
    }
}