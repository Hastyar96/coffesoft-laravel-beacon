<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans the entire project for reusable traits and their usage.
 */
class TraitScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $traits = $this->findTraitDefinitions();
        $usage = $this->findTraitUsage();

        return [
            'traits' => [
                'count' => count($traits),
                'definitions' => $traits,
                'usage_map' => $usage,
            ],
        ];
    }

    private function findTraitDefinitions(): array
    {
        $traitDirs = [
            app_path('Traits'),
            app_path('Concerns'),
        ];

        $traits = [];

        foreach ($traitDirs as $dir) {
            if (!is_dir($dir)) continue;
            foreach ($this->reader->getPhpFiles($dir) as $file) {
                $contents = $file->getContents();
                $parsed = $this->parser->parse($contents);

                if ($parsed['class_name'] === null) continue;
                if (!$parsed['is_abstract'] && !str_contains($contents, 'trait ')) continue;

                $isTrait = preg_match('/^trait\s+(\w+)/m', $contents);

                $traits[] = [
                    'name' => $parsed['class_name'],
                    'namespace' => $parsed['namespace'] ?? '',
                    'path' => $file->getRelativePathname(),
                    'is_trait' => (bool)$isTrait,
                    'methods' => array_map(fn($m) => $m['name'], $parsed['methods']),
                    'properties' => array_map(fn($p) => $p['name'], $parsed['properties']),
                ];
            }
        }

        // Also scan for trait keyword in app/Models (HasFactory, SoftDeletes, etc)
        $modelPath = app_path('Models');
        if (is_dir($modelPath)) {
            // Traits are already being tracked as imports in ModelScanner
        }

        return $traits;
    }

    private function findTraitUsage(): array
    {
        $usage = [];
        $scanDirs = [
            app_path('Models'),
            app_path('Http/Controllers'),
            app_path('Services'),
            app_path('Repositories'),
            app_path('Traits'),
            app_path('Concerns'),
        ];

        foreach ($scanDirs as $dir) {
            if (!is_dir($dir)) continue;
            foreach ($this->reader->getPhpFiles($dir) as $file) {
                $contents = $file->getContents();
                $parsed = $this->parser->parse($contents);

                if ($parsed['class_name'] === null) continue;

                if (!empty($parsed['traits'])) {
                    foreach ($parsed['traits'] as $trait) {
                        $shortName = $trait;
                        if (str_contains($trait, '\\')) {
                            $parts = explode('\\', $trait);
                            $shortName = end($parts);
                        }
                        if (!isset($usage[$shortName])) {
                            $usage[$shortName] = [];
                        }
                        $usage[$shortName][] = [
                            'class' => $parsed['class_name'],
                            'namespace' => $parsed['namespace'],
                            'path' => $file->getRelativePathname(),
                        ];
                    }
                }
            }
        }

        return $usage;
    }
}