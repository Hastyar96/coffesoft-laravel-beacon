<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Auto Controller Splitter
 *
 * Detects controllers with >10 methods OR >300 lines.
 * Suggests exact split structure by mapping methods to logical sub-controllers.
 * Uses model names, method naming patterns, and responsibility grouping.
 *
 * Output: split-suggestions.json — NEVER auto-applies changes.
 */
class AutoControllerSplitter
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        $suggestions = [];
        $modelNames = array_map(fn($m) => $m['name'], $data['models']['items'] ?? []);

        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $methods = $ctrl['methods'] ?? [];
            $methodCount = count($methods);

            // Estimate lines from path if available
            $lineCount = $this->estimateLines($ctrl['path'] ?? '');
            $isLarge = $methodCount > 10 || $lineCount > 300;

            if (!$isLarge) continue;

            $split = $this->buildSplitPlan($ctrl['name'], $methods, $modelNames, $lineCount);

            if (count($split['new_controllers']) > 0) {
                $suggestions[] = $split;
            }
        }

        return [
            'controller_splits' => [
                'count' => count($suggestions),
                'suggestions' => $suggestions,
                'confidence' => 80,
            ],
        ];
    }

    private function buildSplitPlan(string $ctrlName, array $methods, array $modelNames, int $lineCount): array
    {
        $groups = [];
        $used = [];
        $ctrlBase = preg_replace('/Controller$/', '', $ctrlName);

        // Group methods by model/domain name match (these get domain-specific controller names)
        foreach ($methods as $m) {
            foreach ($modelNames as $model) {
                $modelLower = strtolower($model);
                if (str_contains(strtolower($m), $modelLower) && !in_array($m, $used)) {
                    $groups[$model][] = $m;
                    $used[] = $m;
                    break;
                }
            }
        }

        // Group remaining methods by extracting the domain from method names
        // e.g. "deleteWorkout" → domain="Workout", "exportReport" → domain="Report"
        foreach ($methods as $m) {
            if (in_array($m, $used)) continue;
            // Extract domain from CamelCase: "deleteWorkout" → "Workout", "showAllReports" → "Reports"
            if (preg_match('/^(?:get|set|create|store|update|delete|destroy|find|search|list|show|export|import|process|handle|calculate|validate|notify|send|generate|view|edit)([A-Z]\w*)/i', $m, $parts)) {
                $domain = $parts[1]; // e.g. "Workout" from "deleteWorkout"
                $groups[$domain][] = $m;
                $used[] = $m;
            }
        }

        // Remaining methods: group by the original controller's base name as context
        $remaining = array_diff($methods, $used);
        foreach ($remaining as $m) {
            // Try to extract any noun from the method name
            if (preg_match('/[A-Z]\w+/', $m, $parts)) {
                $groups[$parts[0]][] = $m;
            } else {
                $groups[$ctrlBase][] = $m;
            }
        }

        // Build new controller definitions with domain-specific names
        $newControllers = [];
        $actionPrefixes = ['Get', 'Set', 'Create', 'Store', 'Update', 'Delete', 'Destroy', 'Find', 'Search',
                           'List', 'Show', 'Export', 'Import', 'Process', 'Handle', 'Calculate',
                           'Validate', 'Notify', 'Send', 'Generate', 'View', 'Edit'];

        foreach ($groups as $name => $groupMethods) {
            if (count($groupMethods) < 1) continue;

            // NEVER generate names like "DeleteController" or "ShowController"
            // Always use domain-specific names
            if (in_array($name, $actionPrefixes)) {
                // Use the original base name for context: e.g. "Admin" + "Report" = "AdminReportController"
                $newName = "{$ctrlBase}{$name}Controller";
            } else {
                $newName = "{$name}Controller";
            }

            $newControllers[] = [
                'name' => $newName,
                'methods' => $groupMethods,
                'method_count' => count($groupMethods),
            ];
        }

        return [
            'original_controller' => $ctrlName,
            'original_methods' => count($methods),
            'original_lines' => $lineCount,
            'recommendation' => count($newControllers) >= 2 ? 'split' : (count($methods) > 10 ? 'split' : 'refactor'),
            'new_controllers' => $newControllers,
            'total_split_controllers' => count($newControllers),
            'avg_methods_per_controller' => count($methods) > 0 ? round(count($methods) / max(count($newControllers), 1), 1) : 0,
            'confidence' => 80,
        ];
    }

    private function estimateLines(string $path): int
    {
        $paths = [
            app_path('Http/Controllers/' . ltrim($path, '/')),
            base_path(ltrim($path, '/')),
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) {
                return count(file($p));
            }
        }
        return 0;
    }
}