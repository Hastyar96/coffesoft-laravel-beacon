<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Exporters;

use Coffessoft\LaravelBeacon\Context\Context;
use Coffessoft\LaravelBeacon\Contracts\Exporter;

/**
 * Orchestrates multiple exporters.
 *
 * Each exporter receives the same Context and
 * exports it in its respective format.
 */
class ExporterManager
{
    /**
     * @param array<int, Exporter> $exporters
     */
    public function __construct(
        private readonly array $exporters,
    ) {
    }

    /**
     * Run all exporters against the given context.
     */
    public function export(Context $context): void
    {
        foreach ($this->exporters as $exporter) {
            $exporter->export($context);
        }
    }
}