<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Console;

use Coffesoft\LaravelBeacon\Builder\ContextBuilder;
use Coffesoft\LaravelBeacon\Exporter\JsonExporter;
use Coffesoft\LaravelBeacon\Exporter\MarkdownExporter;
use Illuminate\Console\Command;

class BeaconScanCommand extends Command
{
    protected $signature = 'beacon:scan
                            {--output= : Output directory (default: storage/app/beacon)}';

    protected $description = 'Scan Laravel project and generate comprehensive project intelligence';

    public function __construct(
        private readonly ContextBuilder $builder,
        private readonly JsonExporter $jsonExporter,
        private readonly MarkdownExporter $markdownExporter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->newLine();
        $this->info('  Beacon — Project Intelligence');
        $this->line('  ' . str_repeat('─', 40));
        $this->newLine();

        $laravelVersion = $this->getLaravelVersion();
        $phpVersion = PHP_VERSION;
        $this->line("  Laravel: v{$laravelVersion}");
        $this->line("  PHP:     v{$phpVersion}");
        $this->newLine();

        $this->line('  Running scanners...');
        $this->newLine();

        try {
            $context = $this->builder->build();
        } catch (\Throwable $e) {
            $this->error("  Scan failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->info('  ✓ Scan complete');
        $this->newLine();

        $this->line('  Generating output files...');

        $outputDir = $this->option('output') ?? config('beacon.output_directory', 'storage/app/beacon');
        $outputPath = storage_path(str_replace('storage/', '', $outputDir));

        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $context->set('output_directory', $outputPath);

        try {
            $mdPath = $outputPath . '/context.md';
            $this->markdownExporter->export($context, $mdPath);
            $this->line('    ✓ context.md');

            $jsonPath = $outputPath . '/context.json';
            $this->jsonExporter->export($context, $jsonPath);
            $this->line('    ✓ context.json');

            $graphPath = $outputPath . '/project-graph.json';
            $this->jsonExporter->export($context, $graphPath);
            $this->line('    ✓ project-graph.json');

            $archPath = $outputPath . '/architecture.json';
            $this->jsonExporter->export($context, $archPath);
            $this->line('    ✓ architecture.json');

        } catch (\Throwable $e) {
            $this->error("  Export failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        // Show validation warnings after successful scan
        if ($context->hasValidationWarnings()) {
            $this->newLine();
            $this->warn('  ⚠ Validation Warnings:');
            foreach ($context->getValidationWarnings() as $warning) {
                $component = $warning['component'] ?? '?';
                $field = $warning['field'] ?? '?';
                $expected = $warning['expected'] ?? '?';
                $received = $warning['received'] ?? '?';
                $this->line("    • [{$component}] Field '{$field}': expected {$expected}, got {$received}");
            }
            $this->warn('  Validation warnings saved to storage/app/beacon/validation-warnings.json');
            $this->newLine();
            
            // Save validation warnings to file
            $warningsDir = dirname($outputPath);
            file_put_contents(
                $warningsDir . '/validation-warnings.json',
                json_encode($context->getValidationWarnings(), JSON_PRETTY_PRINT)
            );
        }

        $this->newLine();

        $duration = round(microtime(true) - $startTime, 2);
        $peakMemory = memory_get_peak_usage(true);

        $this->info('  Scan complete!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Duration', "{$duration}s"],
                ['Peak Memory', $this->formatBytes($peakMemory)],
                ['Total Files', $context->get('statistics.total_php_files', 0)],
                ['Models', $context->get('models.count', 0)],
                ['Controllers', $context->get('controllers.count', 0)],
                ['Routes', $context->get('routes.count', 0)],
                ['Services', $context->get('services.count', 0)],
                ['Business Rules', $context->get('business_rules.count', 0)],
                ['Security Issues', $context->get('security.issues_count', 0)],
            ]
        );

        $this->newLine();
        $this->line("  Output: {$outputPath}");
        $this->newLine();

        return self::SUCCESS;
    }

    private function getLaravelVersion(): string
    {
        try {
            return app()->version();
        } catch (\Throwable) {
            return '(unknown)';
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
}