<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Console;

use Coffesoft\LaravelBeacon\Context\Context;
use Coffesoft\LaravelBeacon\Exporter\JsonExporter;
use Illuminate\Console\Command;

class BeaconExportCommand extends Command
{
    protected $signature = 'beacon:export
                            {--input= : Input JSON file path (default: storage/app/beacon/context.json)}
                            {--output= : Output directory (default: storage/app/beacon)}
                            {--format=all : Export formats: all, json, md}';

    protected $description = 'Export previously scanned beacon data to different formats';

    public function __construct(
        private readonly JsonExporter $jsonExporter,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $inputPath = $this->option('input') ?? storage_path('app/beacon/context.json');
        $outputDir = $this->option('output') ?? storage_path('app/beacon');

        if (!file_exists($inputPath)) {
            $this->error(" Input file not found: {$inputPath}");
            $this->line(" Run `php artisan beacon:scan` first to generate the data.");
            return self::FAILURE;
        }

        $this->info(" 📤 Exporting from: {$inputPath}");

        $json = file_get_contents($inputPath);
        $data = json_decode($json, true);

        if (!$data) {
            $this->error(" Invalid JSON in input file.");
            return self::FAILURE;
        }

        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }

        $context = new Context();
        $context->merge($data);

        $files = $this->jsonExporter->exportAll($context, $outputDir);

        $this->info(" ✅ Export complete! Generated files:");
        foreach ($files as $file) {
            $size = filesize($file);
            $this->line("   ✓ " . basename($file) . " (" . $this->formatBytes($size) . ")");
        }

        return self::SUCCESS;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}