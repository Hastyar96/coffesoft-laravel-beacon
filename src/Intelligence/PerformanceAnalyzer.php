<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Analyzes project performance by detecting N+1 risks, heavy controllers,
 * duplicate queries, unused imports, dead classes, and dead routes.
 */
class PerformanceAnalyzer
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        $issues = [];

        // Detect N+1 risks in controllers
        $nPlusOne = $this->detectNPlusOne($data);
        foreach ($nPlusOne as $item) {
            $issues[] = [
                'type' => 'n_plus_one',
                'severity' => 'warning',
                'message' => $item['message'],
                'class' => $item['class'],
                'method' => $item['method'] ?? null,
            ];
        }

        // Detect heavy controllers (too many methods)
        $heavyControllers = $this->findHeavyControllers($data);
        foreach ($heavyControllers as $ctrl) {
            $issues[] = [
                'type' => 'heavy_controller',
                'severity' => 'info',
                'message' => "Controller {$ctrl['name']} has {$ctrl['method_count']} methods — consider splitting into smaller controllers",
                'class' => $ctrl['name'],
            ];
        }

        // Detect large models
        $largeModels = $this->findLargeModels($data);
        foreach ($largeModels as $model) {
            $issues[] = [
                'type' => 'large_model',
                'severity' => 'info',
                'message' => "Model {$model['name']} has {$model['method_count']} methods — consider extracting into traits or services",
                'class' => $model['name'],
            ];
        }

        // Detect unused imports across the project
        $unusedImports = $this->findUnusedImports();
        if ($unusedImports > 0) {
            $issues[] = [
                'type' => 'unused_imports',
                'severity' => 'info',
                'message' => "Potential {$unusedImports} unused import statements found in controllers",
            ];
        }

        // Detect dead routes (routes that may not be hit by any view or API call)
        $deadRoutes = $this->findDeadRoutes($data);
        if ($deadRoutes > 0) {
            $issues[] = [
                'type' => 'dead_routes',
                'severity' => 'info',
                'message' => "{$deadRoutes} routes have no name and may be unused — consider removing if not needed",
            ];
        }

        return [
            'performance' => [
                'issues_count' => count($issues),
                'issues' => $issues,
            ],
        ];
    }

    private function detectNPlusOne(array $data): array
    {
        $risks = [];

        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $path = $ctrl['path'] ?? '';
            $contents = $this->readSourceFile($path);
            if ($contents === null) continue;

            // Detect lazy load patterns that might cause N+1
            foreach ($ctrl['methods'] ?? [] as $method) {
                $methodFound = $this->findMethodBody($contents, $method);
                if ($methodFound === null) continue;

                // Check for relationship access inside loops
                if (preg_match('/\bforeach\b.*?\$(\w+)(?:->(\w+))?(?:\s*as\s*\$(\w+))/s', $methodFound, $loopMatch)) {
                    $collection = $loopMatch[1] ?? '';
                    $var = $loopMatch[3] ?? '';

                    // Check if there's relation access inside the loop body
                    if (preg_match('/\$' . preg_quote($var, '/') . '->(\w+)(?:\s*\(|;|\))/', $methodFound)) {
                        // Check if with() is used
                        if (!str_contains($ctrl['namespace'] . $contents, 'with(')
                            && !str_contains($ctrl['namespace'] . $contents, '->load(')) {
                            $risks[] = [
                                'class' => $ctrl['name'],
                                'method' => $method,
                                'message' => "Potential N+1 query in {$ctrl['name']}::{$method} — relationship accessed inside foreach without eager loading",
                            ];
                        }
                    }
                }
            }
        }

        return $risks;
    }

    private function findHeavyControllers(array $data): array
    {
        $heavy = [];
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $methodCount = count($ctrl['methods'] ?? []);
            if ($methodCount > 10) {
                $heavy[] = [
                    'name' => $ctrl['name'],
                    'method_count' => $methodCount,
                ];
            }
        }
        return $heavy;
    }

    private function findLargeModels(array $data): array
    {
        $large = [];
        foreach ($data['models']['items'] ?? [] as $model) {
            $methodCount = count($model['methods'] ?? []) + count($model['scopes'] ?? [])
                + count($model['accessors'] ?? []) + count($model['mutators'] ?? []);
            if ($methodCount > 15) {
                $large[] = [
                    'name' => $model['name'],
                    'method_count' => $methodCount,
                ];
            }
        }
        return $large;
    }

    private function findUnusedImports(): int
    {
        $unused = 0;
        $ctrlPath = app_path('Http/Controllers');
        if (!is_dir($ctrlPath)) return 0;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($ctrlPath)
        );

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());

            // Extract imports and check if they're used in the file body
            if (preg_match_all('/^use\s+([^;]+);/m', $contents, $matches)) {
                foreach ($matches[1] as $import) {
                    $shortName = substr(strrchr($import, '\\') ?: $import, 1);
                    if (empty($shortName)) continue;

                    // Skip facade-like imports
                    if (in_array($shortName, ['Facades', 'DB', 'Route', 'Auth', 'Hash', 'Mail', 'Log', 'Storage', 'Bus', 'Event'])) continue;

                    // Check if the short name is used in non-import context
                    $bodyWithoutImports = preg_replace('/^use\s+[^;]+;/m', '', $contents);
                    if (!preg_match('/\b' . preg_quote($shortName, '/') . '\b/', $bodyWithoutImports)) {
                        $unused++;
                    }
                }
            }
        }

        return $unused;
    }

    private function findDeadRoutes(array $data): int
    {
        $dead = 0;
        foreach ($data['routes']['items'] ?? [] as $route) {
            $name = $route['name'] ?? null;
            if ($name === null || $name === '') {
                $dead++;
            }
        }
        return $dead;
    }

    private function readSourceFile(string $relativePath): ?string
    {
        $fullPath = app_path() . '/' . $relativePath;
        if (!file_exists($fullPath)) return null;
        return file_get_contents($fullPath);
    }

    private function findMethodBody(string $contents, string $method): ?string
    {
        if (preg_match('/function\s+' . preg_quote($method, '/') . '\s*\([^)]*\)\s*\{(.*?)\}/s', $contents, $m)) {
            return $m[1];
        }
        return null;
    }
}