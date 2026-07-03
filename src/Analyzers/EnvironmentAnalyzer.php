<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Analyzers;

use Coffessoft\LaravelBeacon\Context\Context;
use Coffessoft\LaravelBeacon\Contracts\Analyzer;

/**
 * Analyzer that determines Laravel environment information.
 *
 * Reads environment details using Laravel helpers
 * and appends them to the given Context.
 */
class EnvironmentAnalyzer implements Analyzer
{
    /**
     * Analyze the context and append environment information.
     */
    public function analyze(Context $context): Context
    {
        $context->set('environment', [
            'app_env' => app()->environment(),
            'debug' => config('app.debug'),
            'timezone' => config('app.timezone'),
            'locale' => config('app.locale'),
        ]);

        return $context;
    }
}