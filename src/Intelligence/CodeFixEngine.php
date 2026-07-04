<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Code Fix Engine
 *
 * Reads ReviewEngine results and generates safe, actionable patch suggestions.
 * Outputs fix-suggestions.json and fix-patch.md (human-readable diff).
 * NEVER auto-applies changes — only suggests.
 */
class CodeFixEngine
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        // Run the review engine to get findings
        $reviewEngine = new ReviewEngine();
        $reviewResult = $reviewEngine->analyze($data);
        $findings = $reviewResult['review']['findings'] ?? [];

        // Run the auto splitter for controller refactoring
        $splitter = new AutoControllerSplitter();
        $splitResult = $splitter->analyze($data);

        $fixes = [];

        // Convert review findings into actionable fix suggestions
        foreach ($findings as $finding) {
            $fix = $this->convertToFix($finding);
            if ($fix) {
                $fixes[] = $fix;
            }
        }

        // Add controller split suggestions as fix items
        foreach ($splitResult['controller_splits']['suggestions'] ?? [] as $split) {
            $fixes[] = [
                'type' => 'controller_split',
                'severity' => 'warning',
                'title' => "Split {$split['original_controller']}",
                'description' => "{$split['original_controller']} has {$split['original_methods']} methods in {$split['original_lines']} lines. Split into {$split['total_split_controllers']} smaller controllers.",
                'affected_file' => "app/Http/Controllers/{$split['original_controller']}.php",
                'recommended_action' => 'split',
                'new_files_suggested' => array_map(fn($nc) => "app/Http/Controllers/{$nc['name']}.php", $split['new_controllers']),
                'method_mapping' => $this->buildMethodMapping($split),
                'estimated_effort' => count($split['new_controllers']) <= 2 ? 'small' : (count($split['new_controllers']) <= 5 ? 'medium' : 'large'),
                'confidence' => $split['confidence'],
            ];
        }

        $md = $this->buildMarkdown($fixes);

        return [
            'fix_suggestions' => [
                'count' => count($fixes),
                'fixes' => $fixes,
                'markdown' => $md,
                'safety_note' => 'These are suggestions only. Manually review before applying any changes.',
                'confidence' => 75,
            ],
        ];
    }

    private function convertToFix(array $finding): ?array
    {
        $type = $finding['type'] ?? '';
        if (empty($type)) return null;

        $severity = $finding['severity'] ?? 'info';

        // Only actionable findings become fix suggestions
        $actionable = [
            'fat_controller', 'fat_model', 'large_service',
            'missing_authorization', 'missing_transaction',
            'dead_controller', 'unnamed_routes',
            'duplicate_validation', 'potential_n_plus_one',
            'large_class',
        ];

        if (!in_array($type, $actionable)) return null;

        return [
            'type' => $type,
            'severity' => $severity,
            'title' => $this->fixTitle($type, $finding),
            'description' => $finding['message'] ?? '',
            'affected_file' => $finding['path'] ?? $finding['class'] ?? '',
            'affected_class' => $finding['class'] ?? null,
            'recommended_action' => $this->fixAction($type),
            'code_pattern' => $this->fixPattern($type, $finding),
            'estimated_effort' => $this->fixEffort($type),
            'evidence' => $finding['evidence'] ?? '',
            'confidence' => $finding['confidence'] ?? 50,
        ];
    }

    private function fixTitle(string $type, array $finding): string
    {
        $className = $finding['class'] ?? 'unknown';
        $metric = $finding['metric'] ?? '?';
        return match ($type) {
            'fat_controller' => "Refactor {$className} into smaller controllers",
            'fat_model' => "Extract traits from {$className}",
            'large_service' => "Split {$className} into smaller services",
            'missing_authorization' => "Add Policy for {$className}",
            'missing_transaction' => "Wrap DB operations in DB::transaction() in {$className}",
            'dead_controller' => "Remove or verify {$className}",
            'unnamed_routes' => "Name unnamed routes",
            'duplicate_validation' => "Extract shared validation rules",
            'potential_n_plus_one' => "Add eager loading in {$className}",
            'large_class' => "Refactor {$className} ({$metric} lines)",
            default => "Review {$className}",
        };
    }

    private function fixAction(string $type): string
    {
        return match ($type) {
            'fat_controller' => 'split',
            'fat_model' => 'extract_trait',
            'large_service' => 'split',
            'missing_authorization' => 'create_file',
            'missing_transaction' => 'wrap_code',
            'dead_controller' => 'remove',
            'unnamed_routes' => 'add_name',
            'duplicate_validation' => 'extract_class',
            'potential_n_plus_one' => 'add_eager_loading',
            'large_class' => 'refactor',
            default => 'review',
        };
    }

    private function fixPattern(string $type, array $finding): string
    {
        $className = $finding['class'] ?? '';
        return match ($type) {
            'missing_authorization' => "php artisan make:policy {$className}Policy --model={$className}",
            'missing_transaction' => "DB::transaction(function () {\n    // existing code\n});",
            'unnamed_routes' => "->name('name')",
            'potential_n_plus_one' => "->with(['relation'])->get()  // or  ->load('relation')",
            'duplicate_validation' => "php artisan make:rule SharedRule",
            default => '',
        };
    }

    private function fixEffort(string $type): string
    {
        return match ($type) {
            'fat_controller' => 'medium',
            'fat_model' => 'medium',
            'large_service' => 'medium',
            'missing_authorization' => 'small',
            'missing_transaction' => 'small',
            'dead_controller' => 'small',
            'unnamed_routes' => 'small',
            'duplicate_validation' => 'small',
            'potential_n_plus_one' => 'small',
            'large_class' => 'medium',
            default => 'unknown',
        };
    }

    private function buildMethodMapping(array $split): array
    {
        $mapping = [];
        foreach ($split['new_controllers'] ?? [] as $nc) {
            foreach ($nc['methods'] as $method) {
                $mapping[] = [
                    'method' => $method,
                    'moves_to' => $nc['name'],
                ];
            }
        }
        return $mapping;
    }

    private function buildMarkdown(array $fixes): string
    {
        $md = [];
        $md[] = '# Fix Suggestions';
        $md[] = '';
        $md[] = '> Read-only analysis. None of these changes are applied automatically.';
        $md[] = '';
        $md[] = '---';
        $md[] = '';

        $md[] = '## Summary';
        $md[] = '';
        $md[] = '| Severity | Count |';
        $md[] = '|---------|-------|';
        $md[] = '| 🔴 Critical | ' . count(array_filter($fixes, fn($f) => $f['severity'] === 'critical')) . ' |';
        $md[] = '| 🟠 High | ' . count(array_filter($fixes, fn($f) => $f['severity'] === 'high')) . ' |';
        $md[] = '| 🟡 Warning | ' . count(array_filter($fixes, fn($f) => $f['severity'] === 'warning')) . ' |';
        $md[] = '| 🔵 Info | ' . count(array_filter($fixes, fn($f) => $f['severity'] === 'info')) . ' |';
        $md[] = '';

        foreach ($fixes as $fix) {
            $icon = match ($fix['severity']) {
                'critical' => '🔴', 'high' => '🟠',
                'warning' => '🟡', default => '🔵',
            };
            $md[] = "### {$icon} {$fix['title']}";
            $md[] = '';
            $md[] = "**Description:** {$fix['description']}";
            $md[] = "**Effort:** {$fix['estimated_effort']}";
            $md[] = "**Action:** {$fix['recommended_action']}";
            $md[] = "**Confidence:** {$fix['confidence']}%";
            if (!empty($fix['code_pattern'])) {
                $md[] = "**Pattern:** `{$fix['code_pattern']}`";
            }
            if (!empty($fix['new_files_suggested'])) {
                $md[] = '**New files:**';
                foreach ($fix['new_files_suggested'] as $f) {
                    $md[] = "- `{$f}`";
                }
            }
            $md[] = '';
        }

        $md[] = '---';
        $md[] = '*Generated by Laravel Beacon v1.0.0 — AI Copilot*';
        $md[] = '*All suggestions are read-only. Review manually before applying.*';

        return implode("\n", $md);
    }
}