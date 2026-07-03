<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Console;

use Coffesoft\LaravelBeacon\Builder\ContextBuilder;
use Coffesoft\LaravelBeacon\Exporter\JsonExporter;
use Coffesoft\LaravelBeacon\Exporter\MarkdownExporter;
use Illuminate\Console\Command;

class BeaconScanCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'beacon:scan
                            {--output= : Output directory (default: storage/app/beacon)}
                            {--format=all : Output formats: all, json, md, both}
                            {--scanner=* : Specific scanners to run (e.g. --scanner=models --scanner=controllers)}
                            {--no-intelligence : Skip intelligence analysis}
                            {--memory-report : Show memory usage after scan}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan Laravel project and generate comprehensive AI project intelligence';

    /**
     * Create a new command instance.
     */
    public function __construct(
        private readonly ContextBuilder $builder,
        private readonly JsonExporter $jsonExporter,
        private readonly MarkdownExporter $markdownExporter,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage(true);

        $this->newLine();
        $this->info(' 🚀 Laravel Beacon v2 — AI Project Intelligence Engine');
        $this->line(' ─────────────────────────────────────────────────────');
        $this->newLine();

        // Phase 1: Check project type
        $this->warn(' Phase 1: Analyzing project structure...');

        $laravelVersion = app()->version();
        $phpVersion = PHP_VERSION;
        $this->line("   Laravel: v{$laravelVersion}");
        $this->line("   PHP:     v{$phpVersion}");
        $this->newLine();

        // Phase 2: Scan
        $this->warn(' Phase 2: Running scanners...');

        $progress = $this->output->createProgressBar(25);
        $progress->setFormat(' %current%/%max% [%bar%] %message%');
        $progress->setMessage('Scanning...');

        try {
            $context = $this->builder->build();
        } catch (\Throwable $e) {
            $this->error(" Scan failed: {$e->getMessage()}");
            $this->line($e->getTraceAsString());
            return self::FAILURE;
        }

        $progress->finish();
        $this->newLine(2);

        // Phase 3: Export
        $this->warn(' Phase 3: Generating output files...');

        $outputDir = $this->option('output') ?? config('beacon.output_directory', 'storage/app/beacon');
        $outputPath = storage_path(str_replace('storage/', '', $outputDir));

        if (!is_dir($outputPath)) {
            mkdir($outputPath, 0755, true);
        }

        $context->set('output_directory', $outputPath);

        try {
            // Write context.md
            $mdPath = $outputPath . '/context.md';
            $this->jsonExporter->export($context, $mdPath);
            $this->line("   ✓ context.md       -> {$this->shortPath($mdPath)}");

            // Write context.json
            $jsonPath = $outputPath . '/context.json';
            $this->jsonExporter->export($context, $jsonPath);
            $this->line("   ✓ context.json     -> {$this->shortPath($jsonPath)}");

            // Write project-graph.json
            $graphPath = $outputPath . '/project-graph.json';
            $this->markdownExporter->export($context, $graphPath);
            $this->line("   ✓ project-graph    -> {$this->shortPath($graphPath)}");

            // Write architecture.json
            $archPath = $outputPath . '/architecture.json';
            $this->jsonExporter->export($context, $archPath);
            $this->line("   ✓ architecture     -> {$this->shortPath($archPath)}");

        } catch (\Throwable $e) {
            $this->error(" Export failed: {$e->getMessage()}");
            return self::FAILURE;
        }

        $this->newLine();

        // Summary
        $duration = round(microtime(true) - $startTime, 2);
        $peakMemory = memory_get_peak_usage(true);
        $peakMemoryFormatted = $this->formatBytes($peakMemory);

        $this->info(' ✅ Scan complete!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Duration', "{$duration}s"],
                ['Peak Memory', $peakMemoryFormatted],
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
        $this->line(" Output directory: {$outputPath}");
        $this->newLine();

        return self::SUCCESS;
    }

    private function shortPath(string $path): string
    {
        $base = base_path();
        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base) + 1);
        }
        return $path;
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