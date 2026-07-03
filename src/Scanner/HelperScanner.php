<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

/**
 * Scans the project for global helper functions and custom helper files.
 */
class HelperScanner
{
    public function __construct(
        private readonly FileReader $reader,
    ) {}

    public function scan(): array
    {
        $helpers = [];

        // Common helper file locations
        $helperFiles = $this->findHelperFiles();

        foreach ($helperFiles as $file) {
            $contents = $this->reader->read($file);
            $functions = $this->extractFunctions($contents);

            if (!empty($functions)) {
                $helpers[] = [
                    'path' => str_replace(base_path() . '/', '', $file),
                    'functions' => $functions,
                ];
            }
        }

        // Detect composer.json autoload files
        $autoloadHelpers = $this->detectAutoloadHelpers();

        return [
            'helpers' => [
                'count' => count($helpers),
                'files' => $helpers,
                'autoload_files' => $autoloadHelpers,
            ],
        ];
    }

    private function findHelperFiles(): array
    {
        $possiblePaths = [
            app_path('Helpers/helpers.php'),
            app_path('helpers.php'),
            base_path('app/helpers.php'),
            base_path('resources/helpers.php'),
            base_path('bootstrap/helpers.php'),
        ];

        // Scan for any PHP files in app/Helpers
        $helpersDir = app_path('Helpers');
        if (is_dir($helpersDir)) {
            foreach ($this->reader->getPhpFiles($helpersDir) as $file) {
                $possiblePaths[] = $file->getPathname();
            }
        }

        return array_values(array_filter($possiblePaths, 'file_exists'));
    }

    private function extractFunctions(string $contents): array
    {
        $functions = [];

        if (preg_match_all('/^function\s+(\w+)\s*\(/m', $contents, $matches)) {
            foreach ($matches[1] as $name) {
                // Skip class methods
                if (!str_contains($contents, "function $name(")) continue;

                // Get function signature
                if (preg_match('/function\s+' . preg_quote($name, '/') . '\s*\(([^)]*)\)/', $contents, $m)) {
                    $params = array_map('trim', explode(',', $m[1]));
                    $params = array_filter($params, fn($p) => !empty($p));
                } else {
                    $params = [];
                }

                $functions[] = [
                    'name' => $name,
                    'params' => array_values($params),
                ];
            }
        }

        return $functions;
    }

    private function detectAutoloadHelpers(): array
    {
        $files = [];
        $composerPath = base_path('composer.json');

        if (!file_exists($composerPath)) return $files;

        $composer = json_decode(file_get_contents($composerPath), true);
        if (!$composer) return $files;

        $autoload = $composer['autoload'] ?? [];
        $filesList = $autoload['files'] ?? [];

        return $filesList;
    }
}