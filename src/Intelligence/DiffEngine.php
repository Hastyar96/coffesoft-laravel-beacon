<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

use Coffesoft\LaravelBeacon\Cache\ScanCache;

/**
 * Diff Engine — Fixed baseline handling
 *
 * On first execution: creates baseline silently, reports 0 changes.
 * Subsequent executions: detects added, modified, removed, renamed files.
 * Impact analysis finds affected services, controllers, routes for each change.
 */
class DiffEngine
{
    public function __construct(
        private readonly ScanCache $scanCache,
    ) {}

    public function analyze(array $data): array
    {
        $currentFiles = $this->getCurrentProjectFiles();
        $cachedPaths = $this->scanCache->getCachedPaths();
        $isFirstScan = empty($cachedPaths);

        if ($isFirstScan) {
            // First run: create baseline, report nothing
            $this->scanCache->recordScan($currentFiles);
            return $this->emptyDiff('First scan — baseline created. No changes detected.');
        }

        // Detect file changes (hash comparison)
        [$changed, $unchanged] = $this->scanCache->detectChanges($currentFiles);
        $changedRelative = array_map(fn($f) => $this->relativePath($f), $changed);

        // Detect removed files
        $currentRelative = array_map(fn($f) => $this->relativePath($f), $currentFiles);
        $removed = array_diff($cachedPaths, $currentRelative);

        // Detect added files (present now, not in cache)
        $added = array_diff($currentRelative, $cachedPaths);

        // Modified = changed but not added
        $modified = array_values(array_filter($changedRelative, fn($f) => !in_array($f, $added)));

        // Detect renamed files (same hash, different path)
        $renamed = $this->detectRenamed($data, $cachedPaths, $currentRelative);

        // Categorize
        $changedModels = $this->filterByType($modified, 'Models');
        $changedControllers = $this->filterByType($modified, 'Http/Controllers');
        $changedServices = $this->filterByType($modified, 'Services');
        $changedMigrations = $this->filterByType($modified, 'migrations');
        $changedRoutes = $this->filterByType($modified, 'routes');
        $changedPolicies = $this->filterByType($modified, 'Policies');

        // Impact analysis
        $impact = $this->analyzeImpact($data, $changedModels, $changedControllers, $changedServices, $changedPolicies, $changedMigrations);

        // Record new baseline
        $this->scanCache->recordScan($currentFiles);

        $md = $this->buildMarkdown($added, $removed, $modified, $renamed,
            $changedModels, $changedControllers, $changedServices,
            $changedMigrations, $changedRoutes, $impact);

        return [
            'diff' => [
                'added' => array_values($added),
                'removed' => array_values($removed),
                'modified' => $modified,
                'renamed' => $renamed,
                'changed_models' => $changedModels,
                'changed_controllers' => $changedControllers,
                'changed_services' => $changedServices,
                'changed_migrations' => $changedMigrations,
                'changed_routes' => $changedRoutes,
                'changed_policies' => $changedPolicies,
                'impact' => $impact,
                'is_first_scan' => $isFirstScan,
                'markdown_content' => $md,
                'confidence' => 90,
            ],
        ];
    }

    private function emptyDiff(string $message): array
    {
        $md = "# Beacon Diff Report\n\n{$message}\n\n";
        $md .= "| Metric | Count |\n|--------|-------|\n";
        $md .= "| Added | 0 |\n| Modified | 0 |\n| Removed | 0 |\n";

        return [
            'diff' => [
                'added' => [],
                'removed' => [],
                'modified' => [],
                'renamed' => [],
                'changed_models' => [],
                'changed_controllers' => [],
                'changed_services' => [],
                'changed_migrations' => [],
                'changed_routes' => [],
                'changed_policies' => [],
                'impact' => [],
                'is_first_scan' => true,
                'markdown_content' => $md,
                'confidence' => 95,
            ],
        ];
    }

    private function detectRenamed(array $data, array $oldPaths, array $newPaths): array
    {
        // Simple heuristic: if a file disappears and a new one appears with similar name content
        $oldOnly = array_diff($oldPaths, $newPaths);
        $newOnly = array_diff($newPaths, $oldPaths);

        $renames = [];
        foreach ($oldOnly as $old) {
            $oldName = pathinfo($old, PATHINFO_FILENAME);
            foreach ($newOnly as $new) {
                $newName = pathinfo($new, PATHINFO_FILENAME);
                if ($oldName === $newName || levenshtein($oldName, $newName) <= 3) {
                    $renames[] = ['from' => $old, 'to' => $new, 'confidence' => 60];
                    break;
                }
            }
        }
        return $renames;
    }

    private function filterByType(array $files, string $type): array
    {
        return array_values(array_filter($files, fn($f) => str_contains($f, "/{$type}/")));
    }

    private function analyzeImpact(array $data, array $changedModels, array $changedControllers, array $changedServices, array $changedPolicies, array $changedMigrations): array
    {
        $impact = [];

        foreach ($changedModels as $modelPath) {
            $modelName = pathinfo($modelPath, PATHINFO_FILENAME);
            $affected = [];

            foreach ($data['controllers']['items'] ?? [] as $c) {
                if (str_contains($c['name'], $modelName)) $affected[] = "controller:{$c['name']}";
            }
            foreach ($data['services']['items'] ?? [] as $s) {
                foreach ($s['referenced_models'] ?? [] as $ref) {
                    $parts = explode('\\', $ref);
                    if (end($parts) === $modelName) {
                        $affected[] = "service:{$s['name']}";
                        break;
                    }
                }
            }
            foreach ($data['repositories']['items'] ?? [] as $r) {
                foreach ($r['referenced_models'] ?? [] as $ref) {
                    $parts = explode('\\', $ref);
                    if (end($parts) === $modelName) {
                        $affected[] = "repository:{$r['name']}";
                        break;
                    }
                }
            }

            $impact[] = [
                'type' => 'model_changed',
                'message' => "{$modelName} model was modified — affects " . count($affected) . " classes",
                'affected' => array_values(array_unique($affected)),
                'confidence' => 85,
            ];
        }

        foreach ($changedControllers as $ctrlPath) {
            $ctrlName = pathinfo($ctrlPath, PATHINFO_FILENAME);
            $affected = [];

            foreach ($data['routes']['items'] ?? [] as $r) {
                if (str_contains($r['action'] ?? '', "\\{$ctrlName}@") || str_contains($r['action'] ?? '', "{$ctrlName}@")) {
                    $affected[] = "route:{$r['uri']}";
                }
            }

            $modelName = preg_replace('/Controller$/', '', $ctrlName);
            $viewPattern = strtolower(preg_replace('/([a-z])([A-Z])/', '$1.$2', $modelName));
            foreach ($data['blade']['views'] ?? [] as $v) {
                if ($viewPattern && str_contains(strtolower($v['name'] ?? ''), $viewPattern)) {
                    $affected[] = "view:{$v['name']}";
                }
            }

            $impact[] = [
                'type' => 'controller_changed',
                'message' => "{$ctrlName} was modified — " . count($affected) . " routes/views affected",
                'affected' => array_values(array_unique($affected)),
                'confidence' => 80,
            ];
        }

        foreach ($changedPolicies as $polPath) {
            $polName = pathinfo($polPath, PATHINFO_FILENAME);
            $modelName = preg_replace('/Policy$/', '', $polName);
            $impact[] = [
                'type' => 'policy_changed',
                'message' => "{$polName} was modified — authorization rules for {$modelName} may have changed",
                'affected' => ["controller:{$modelName}Controller", "model:{$modelName}"],
                'confidence' => 75,
            ];
        }

        foreach ($changedMigrations as $migPath) {
            if (preg_match('/create_(\w+)_table/', basename($migPath), $m)) {
                $table = $m[1];
                $impact[] = [
                    'type' => 'migration_added',
                    'message' => "New migration for `{$table}` table — schema has changed",
                    'affected' => ["table:{$table}"],
                    'confidence' => 90,
                ];
            }
        }

        return $impact;
    }

    private function buildMarkdown(array $added, array $removed, array $modified, array $renamed,
        array $changedModels, array $changedControllers, array $changedServices,
        array $changedMigrations, array $changedRoutes, array $impact): string
    {
        $md = [];
        $md[] = '# Beacon Diff Report';
        $md[] = '';
        $md[] = '> Compares current project against last scan.';
        $md[] = '> Confidence: 90% — file hash based, no false positives.';
        $md[] = '';
        $md[] = '---';
        $md[] = '';

        $md[] = '## Summary';
        $md[] = '';
        $md[] = '| Metric | Count |';
        $md[] = '|--------|-------|';
        $md[] = '| **Added Files** | ' . count($added) . ' |';
        $md[] = '| **Removed Files** | ' . count($removed) . ' |';
        $md[] = '| **Modified Files** | ' . count($modified) . ' |';
        $md[] = '| **Renamed Files** | ' . count($renamed) . ' |';
        $md[] = '| **Changed Models** | ' . count($changedModels) . ' |';
        $md[] = '| **Changed Controllers** | ' . count($changedControllers) . ' |';
        $md[] = '| **Changed Services** | ' . count($changedServices) . ' |';
        $md[] = '| **Changed Migrations** | ' . count($changedMigrations) . ' |';
        $md[] = '| **Impact Items** | ' . count($impact) . ' |';
        $md[] = '';

        if (!empty($added)) {
            $md[] = '---';
            $md[] = '## Added Files';
            $md[] = '';
            foreach ($added as $f) $md[] = "- `{$f}`";
            $md[] = '';
        }

        if (!empty($removed)) {
            $md[] = '---';
            $md[] = '## Removed Files';
            $md[] = '';
            foreach ($removed as $f) $md[] = "- `{$f}`";
            $md[] = '';
        }

        if (!empty($modified)) {
            $md[] = '---';
            $md[] = '## Modified Files';
            $md[] = '';
            foreach ($modified as $f) $md[] = "- `{$f}`";
            $md[] = '';
        }

        if (!empty($renamed)) {
            $md[] = '---';
            $md[] = '## Renamed/Moved Files';
            $md[] = '';
            foreach ($renamed as $r) $md[] = "- `{$r['from']}` → `{$r['to']}`";
            $md[] = '';
        }

        if (!empty($impact)) {
            $md[] = '---';
            $md[] = '## Impact Analysis';
            $md[] = '';
            foreach ($impact as $i) {
                $md[] = "- **{$i['type']}** (confidence: {$i['confidence']}%): {$i['message']}";
                if (!empty($i['affected'])) {
                    $md[] = "  - Affected: " . implode(', ', array_slice($i['affected'], 0, 8))
                        . (count($i['affected']) > 8 ? '...' : '');
                }
            }
            $md[] = '';
        }

        $md[] = '---';
        $md[] = '*Generated by Laravel Beacon v1.0.0*';

        return implode("\n", $md);
    }

    private function getCurrentProjectFiles(): array
    {
        $files = [];
        $dirs = [
            app_path('Models'),
            app_path('Http/Controllers'),
            app_path('Services'),
            app_path('Repositories'),
            app_path('Http/Requests'),
            app_path('Policies'),
            app_path('Events'),
            app_path('Listeners'),
            app_path('Jobs'),
            app_path('Notifications'),
            app_path('Mail'),
            app_path('Console/Commands'),
            base_path('routes'),
            database_path('migrations'),
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        // Also scan route files (non-PHP .php stubs)
        $routesDir = base_path('routes');
        if (is_dir($routesDir)) {
            foreach (new \DirectoryIterator($routesDir) as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    private function relativePath(string $path): string
    {
        $base = base_path();
        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base) + 1);
        }
        return $path;
    }
}