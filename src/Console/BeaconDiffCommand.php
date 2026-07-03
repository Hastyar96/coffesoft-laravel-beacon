<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Console;

use Coffesoft\LaravelBeacon\Builder\ContextBuilder;
use Coffesoft\LaravelBeacon\Intelligence\DiffEngine;
use Illuminate\Console\Command;

class BeaconDiffCommand extends Command
{
    protected $signature = 'beacon:diff
                            {--output= : Output directory (default: storage/app/beacon)}
                            {--format=both : Output format: md, json, both}';

    protected $description = 'Compare current project against last scan and show changes';

    public function __construct(
        private readonly ContextBuilder $builder,
        private readonly DiffEngine $diffEngine,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->newLine();
        $this->info(' 📊 Beacon Diff — Comparing project changes');
        $this->line(' ──────────────────────────────────────');
        $this->newLine();

        // Run scan to get current state
        $this->warn(' Scanning current project state...');
        $context = $this->builder->build();
        $data = $context->all();
        $this->info(' ✓ Current project scanned');

        // Analyze diff
        $this->newLine();
        $this->warn(' Comparing against last scan...');
        $result = $this->diffEngine->analyze($data);
        $this->info(' ✓ Diff analysis complete');
        $this->newLine();

        $diff = $result['diff'];

        // Show summary
        $this->table(
            ['Metric', 'Count'],
            [
                ['Added Files', count($diff['added'] ?? [])],
                ['Removed Files', count($diff['removed'] ?? [])],
                ['Modified Files', count($diff['modified'] ?? [])],
                ['Changed Models', count($diff['changed_models'] ?? [])],
                ['Changed Controllers', count($diff['changed_controllers'] ?? [])],
                ['Changed Services', count($diff['changed_services'] ?? [])],
                ['Impact Items', count($diff['impact'] ?? [])],
            ]
        );
        $this->newLine();

        // Show details
        if (!empty($diff['added'])) {
            $this->line(' 📄 Added files:');
            foreach (array_slice($diff['added'], 0, 10) as $f) {
                $this->line("   + {$f}");
            }
            if (count($diff['added']) > 10) $this->line('   ... and ' . (count($diff['added']) - 10) . ' more');
            $this->newLine();
        }

        if (!empty($diff['modified'])) {
            $this->line(' 📝 Modified files:');
            foreach (array_slice($diff['modified'], 0, 10) as $f) {
                $this->line("   ~ {$f}");
            }
            if (count($diff['modified']) > 10) $this->line('   ... and ' . (count($diff['modified']) - 10) . ' more');
            $this->newLine();
        }

        if (!empty($diff['impact'])) {
            $this->line(' ⚡ Impact analysis:');
            foreach ($diff['impact'] as $i) {
                $this->line("   - {$i['message']}");
                if (!empty($i['affected'])) {
                    $this->line('     Affected: ' . implode(', ', array_slice($i['affected'], 0, 5))
                        . (count($i['affected']) > 5 ? '...' : ''));
                }
            }
            $this->newLine();
        }

        // Export
        $outputDir = $this->option('output') ?? storage_path('app/beacon');
        if (!is_dir($outputDir)) mkdir($outputDir, 0755, true);

        $format = $this->option('format');
        if ($format === 'md' || $format === 'both') {
            $mdPath = $outputDir . '/diff.md';
            file_put_contents($mdPath, $diff['markdown_content'] ?? '');
            $this->line("   ✓ diff.md -> " . $this->shortPath($mdPath));
        }
        if ($format === 'json' || $format === 'both') {
            $jsonPath = $outputDir . '/diff.json';
            file_put_contents(
                $jsonPath,
                json_encode($diff, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
            $this->line("   ✓ diff.json -> " . $this->shortPath($jsonPath));
        }

        $this->newLine();
        $this->info(' ✅ Diff complete!');

        return self::SUCCESS;
    }

    private function shortPath(string $path): string
    {
        $base = base_path();
        return str_starts_with($path, $base) ? substr($path, strlen($base) + 1) : $path;
    }
}