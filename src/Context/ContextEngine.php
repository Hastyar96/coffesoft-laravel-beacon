<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Context;

use Coffessoft\LaravelBeacon\Analyzers\AnalyzerManager;
use Coffessoft\LaravelBeacon\Exporters\ExporterManager;
use Coffessoft\LaravelBeacon\Scanners\ScannerManager;

/**
 * Core engine that orchestrates context generation.
 *
 * Runs scanners to collect data, analyzers to enrich it,
 * then exporters to produce output.
 */
class ContextEngine
{
    /**
     * Create a new ContextEngine instance.
     */
    public function __construct(
        private readonly ScannerManager $scannerManager,
        private readonly AnalyzerManager $analyzerManager,
        private readonly ExporterManager $exporterManager,
    ) {
    }

    /**
     * Generate, enrich, and export context data.
     */
    public function generate(): Context
    {
        $context = $this->scannerManager->scan();

        $context = $this->analyzerManager->analyze($context);

        $this->exporterManager->export($context);

        return $context;
    }
}
