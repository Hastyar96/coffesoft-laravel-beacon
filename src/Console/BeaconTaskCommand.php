<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Console;

use Coffesoft\LaravelBeacon\Builder\ContextBuilder;
use Coffesoft\LaravelBeacon\Intelligence\TaskContextEngine;
use Illuminate\Console\Command;

class BeaconTaskCommand extends Command
{
    protected $signature = 'beacon:task
                            {description : Task description (e.g., "Create attendance system")}
                            {--output= : Output directory (default: storage/app/beacon)}
                            {--scan-first : Run a full scan before generating task context}
                            {--format=md : Output format: md, json, both}';

    protected $description = 'Generate AI-optimized task context for a development task';

    public function __construct(
        private readonly ContextBuilder $builder,
        private readonly TaskContextEngine $taskEngine,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $task = $this->argument('description');

        $this->newLine();
        $this->info(" 🎯 Generating task context for: {$task}");
        $this->line(' ─────────────────────────────────────────────');
        $this->newLine();

        // Step 1: Scan if needed
        $scanData = [];
        if ($this->option('scan-first')) {
            $this->warn(' Step 1: Running full project scan...');
            $context = $this->builder->build();
            $scanData = $context->all();
            $this->info(' ✓ Project scanned successfully');
        } else {
            // Try loading from cache
            $cachePath = storage_path('app/beacon/context.json');
            if (file_exists($cachePath)) {
                $this->line(' Using cached scan from: ' . $cachePath);
                $scanData = json_decode(file_get_contents($cachePath), true) ?? [];
                $this->info(' ✓ Loaded ' . count($scanData) . ' data points');
            } else {
                $this->warn(' ⚠ No cached scan found. Running full scan...');
                $context = $this->builder->build();
                $scanData = $context->all();
                $this->info(' ✓ Project scanned');
            }
        }

        // Step 2: Analyze task
        $this->newLine();
        $this->warn(' Step 2: Analyzing task against project knowledge...');
        $result = $this->taskEngine->analyze($scanData, $task);
        $this->info(' ✓ Analysis complete');
        $this->newLine();

        // Step 3: Export
        $outputDir = $this->option('output') ?? storage_path('app/beacon');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $format = $this->option('format');

        if ($format === 'md' || $format === 'both') {
            $mdPath = $outputDir . '/task-context.md';
            file_put_contents($mdPath, $result['task_context']['markdown_content']);
            $this->line("   ✓ task-context.md     -> " . $this->shortPath($mdPath));
        }

        if ($format === 'json' || $format === 'both' || $format === 'md') {
            $jsonPath = $outputDir . '/task-context.json';
            file_put_contents(
                $jsonPath,
                json_encode($result['task_context']['json'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            $this->line("   ✓ task-context.json   -> " . $this->shortPath($jsonPath));
        }

        // Summary
        $this->newLine();
        $tc = $result['task_context'];
        $json = $tc['json'];
        $this->info(' ✅ Task context ready!');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Task', $task],
                ['Main Entity', $tc['main_entity'] ?? 'Not determined'],
                ['Keywords', implode(', ', $tc['keywords'])],
                ['Related Models', count($json['related_models'] ?? [])],
                ['Related Controllers', count($json['related_controllers'] ?? [])],
                ['Related Services', count($json['related_services'] ?? [])],
                ['New Files Needed', count($json['new_files_needed'] ?? [])],
                ['Similar Implementations', count($json['similar_implementations'] ?? [])],
                ['Risks', count($json['risks'] ?? [])],
            ]
        );
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
}