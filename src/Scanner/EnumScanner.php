<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans the project for PHP 8.1+ enums, cases, backed values, and usage.
 */
class EnumScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $enums = $this->findEnumDefinitions();
        $usage = $this->findEnumUsage($enums);

        return [
            'enums' => [
                'count' => count($enums),
                'definitions' => $enums,
                'usage' => $usage,
            ],
        ];
    }

    private function findEnumDefinitions(): array
    {
        $paths = [
            app_path('Enums'),
            app_path('Enum'),
        ];

        $enums = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            foreach ($this->reader->getPhpFiles($path) as $file) {
                $contents = $file->getContents();
                $parsed = $this->parser->parse($contents);
                if ($parsed['class_name'] === null) continue;

                // Verify it's actually an enum declaration
                if (!preg_match('/^enum\s+' . preg_quote($parsed['class_name'], '/') . '/m', $contents)) {
                    continue;
                }

                // Extract cases
                $cases = $this->extractCases($contents);

                // Detect backed type
                $isBacked = preg_match('/:\s*(int|string)\s*\{/', $contents, $m);
                $backedBy = $isBacked ? $m[1] : null;

                $enums[] = [
                    'name' => $parsed['class_name'],
                    'namespace' => $parsed['namespace'] ?? '',
                    'path' => $file->getRelativePathname(),
                    'backed_type' => $backedBy,
                    'cases' => $cases,
                ];
            }
        }

        return $enums;
    }

    private function extractCases(string $contents): array
    {
        $cases = [];

        if (preg_match_all('/^case\s+(\w+)\s*=\s*([^;]+);/m', $contents, $matches)) {
            // Backed enum
            foreach ($matches[1] as $i => $name) {
                $cases[] = [
                    'name' => $name,
                    'value' => trim($matches[2][$i]),
                ];
            }
        } elseif (preg_match_all('/^case\s+(\w+)\s*;/m', $contents, $matches)) {
            // Pure enum
            foreach ($matches[1] as $name) {
                $cases[] = [
                    'name' => $name,
                    'value' => null,
                ];
            }
        }

        return $cases;
    }

    private function findEnumUsage(array $enums): array
    {
        $usage = [];
        $searchDirs = [
            app_path('Models'),
            app_path('Http/Controllers'),
            app_path('Services'),
            app_path('Repositories'),
            app_path('Http/Requests'),
            app_path('Policies'),
            app_path('Rules'),
            app_path('Http/Middleware'),
        ];

        $enumNames = array_map(fn($e) => $e['name'], $enums);

        foreach ($searchDirs as $dir) {
            if (!is_dir($dir)) continue;
            foreach ($this->reader->getPhpFiles($dir) as $file) {
                $contents = $file->getContents();
                $parsed = $this->parser->parse($contents);
                if ($parsed['class_name'] === null) continue;

                $used = [];
                foreach ($enumNames as $enumName) {
                    if (str_contains($contents, $enumName)) {
                        // Verify it's actually a usage, not the definition
                        $isDefinition = str_contains($file->getPathname(), "/Enums/$enumName.php") ||
                                        str_contains($file->getPathname(), "/Enum/$enumName.php");
                        if (!$isDefinition) {
                            $used[] = $enumName;
                        }
                    }
                }

                if (!empty($used)) {
                    $usage[] = [
                        'class' => $parsed['class_name'],
                        'namespace' => $parsed['namespace'],
                        'path' => $file->getRelativePathname(),
                        'uses_enums' => $used,
                    ];
                }
            }
        }

        return $usage;
    }
}