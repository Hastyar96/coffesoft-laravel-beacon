<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Task Context Engine — Improved matching
 *
 * Uses keyword similarity instead of exact matching.
 * Tokenizes task, normalizes words, searches models/controllers/services/routes/filenames/namespaces.
 *
 * Example: "Create workout reports" discovers Workout, Exercise, Course,
 * Program, WorkoutController, ReportController, and related classes.
 */
class TaskContextEngine
{
    public function analyze(array $data, string $task): array
    {
        $keywords = $this->extractKeywords($task);
        $mainEntity = $this->extractMainEntity($task);

        // Score all project entities using keyword similarity (not exact match)
        $scoredModels = $this->scoreModels($data, $keywords, $mainEntity);
        $scoredControllers = $this->scoreControllers($data, $keywords, $mainEntity);
        $scoredServices = $this->scoreServices($data, $keywords, $mainEntity);
        $scoredRoutes = $this->scoreRoutes($data, $keywords, $mainEntity);
        $scoredTables = $this->scoreTables($data, $keywords, $mainEntity);
        $scoredViews = $this->scoreViews($data, $keywords, $mainEntity);

        $similarImplementations = $this->findSimilarImplementations($data, $mainEntity);
        $newFilesNeeded = $this->determineNewFiles($data, $mainEntity, $keywords);
        $filesToModify = $this->determineFilesToModify($data, $scoredModels, $scoredControllers, $scoredServices);
        $risks = $this->assessRisks($data, $mainEntity);
        $dependencies = $this->determineDependencies($data, $mainEntity);

        // Build the context
        $lines = $this->buildMarkdown($task, $mainEntity, $keywords, $scoredModels, $scoredControllers,
            $scoredServices, $scoredRoutes, $scoredTables, $scoredViews,
            $filesToModify, $newFilesNeeded, $similarImplementations, $dependencies, $risks);

        return [
            'task_context' => [
                'task' => $task,
                'main_entity' => $mainEntity,
                'keywords' => $keywords,
                'markdown_content' => implode("\n", $lines),
                'json' => [
                    'related_models' => array_values(array_filter($scoredModels, fn($m) => $m['score'] >= 50)),
                    'related_controllers' => array_values(array_filter($scoredControllers, fn($c) => $c['score'] >= 40)),
                    'related_services' => array_values(array_filter($scoredServices, fn($s) => $s['score'] >= 40)),
                    'related_routes' => array_values(array_filter($scoredRoutes, fn($r) => $r['score'] >= 40)),
                    'related_tables' => array_values(array_filter($scoredTables, fn($t) => $t['score'] >= 40)),
                    'files_to_modify' => $filesToModify,
                    'new_files_needed' => $newFilesNeeded,
                    'similar_implementations' => $similarImplementations,
                    'dependencies' => $dependencies,
                    'risks' => $risks,
                ],
                'confidence' => 80,
            ],
        ];
    }

    private function extractKeywords(string $task): array
    {
        $stopWords = ['a', 'an', 'the', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for',
                       'of', 'with', 'by', 'from', 'as', 'is', 'was', 'are', 'be', 'been',
                       'create', 'make', 'add', 'new', 'system', 'module', 'feature', 'build',
                       'implement', 'develop', 'integrate', 'set', 'up', 'out', 'get', 'all',
                       'manage', 'management'];

        $words = preg_split('/[\s,]+/', strtolower($task));
        $keywords = [];
        foreach ($words as $word) {
            $word = trim($word, '.,;:!?\'"()');
            if (strlen($word) > 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }

        return array_values(array_unique($keywords));
    }

    private function extractMainEntity(string $task): ?string
    {
        $patterns = [
            '/create\s+(?:a|an)\s+(\w+)/i',
            '/create\s+(\w+)\s+system/i',
            '/add\s+(?:a|an)\s+(\w+)/i',
            '/add\s+(\w+)\s+(?:system|module|feature)/i',
            '/build\s+(?:a|an)\s+(\w+)/i',
            '/manage\s+(\w+)/i',
            '/(\w+)\s+management/i',
            '/(\w+)\s+system/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $task, $matches)) {
                return ucfirst(strtolower($matches[1]));
            }
        }

        $keywords = $this->extractKeywords($task);
        return !empty($keywords) ? ucfirst($keywords[0]) : null;
    }

    /**
     * Score models using keyword similarity — matches substrings, not just exact.
     */
    private function scoreModels(array $data, array $keywords, ?string $mainEntity): array
    {
        $scored = [];
        foreach ($data['models']['items'] ?? [] as $m) {
            $score = 0;
            $reasons = [];
            $name = strtolower($m['name']);

            // Direct match with main entity
            if ($mainEntity && strtolower($mainEntity) === $name) {
                $score += 90;
                $reasons[] = 'Direct match with task entity';
            }

            // Partial match: main entity is contained in model name OR model name is contained in main entity
            if ($mainEntity) {
                $mainLower = strtolower($mainEntity);
                if (str_contains($name, $mainLower) || str_contains($mainLower, $name)) {
                    $score += 60;
                    if (empty($reasons)) $reasons[] = 'Related to task entity';
                }
            }

            // Keyword similarity: check each keyword for substring matches in model name
            foreach ($keywords as $kw) {
                if ($kw === 'create' || $kw === 'add' || $kw === 'new') continue;
                // Partial keyword match
                if (str_contains($name, $kw) || str_contains($kw, $name)) {
                    $score += 25;
                    $reasons[] = "Similar to keyword '{$kw}'";
                    break;
                }
                // Check fillable attributes
                foreach (array_map('strtolower', $m['fillable'] ?? []) as $attr) {
                    if (str_contains($attr, $kw) || str_contains($kw, $attr)) {
                        $score += 10;
                        if (!in_array("Has '{$kw}' attribute", $reasons)) {
                            $reasons[] = "Has '{$kw}' attribute";
                        }
                        break 2;
                    }
                }
            }

            if ($score > 0) {
                $scored[] = [
                    'name' => $m['name'],
                    'type' => 'model',
                    'score' => min($score, 95),
                    'reason' => implode('; ', array_slice(array_unique($reasons), 0, 2)),
                    'fillable' => $m['fillable'] ?? [],
                    'relations' => $m['relations'] ?? [],
                ];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    /**
     * Score controllers using keyword similarity and namespace-aware matching.
     */
    private function scoreControllers(array $data, array $keywords, ?string $mainEntity): array
    {
        $scored = [];
        foreach ($data['controllers']['items'] ?? [] as $c) {
            $score = 0;
            $reasons = [];
            $name = strtolower($c['name']);

            if ($mainEntity) {
                $ctrlEntity = strtolower($mainEntity . 'controller');
                $mainLower = strtolower($mainEntity);

                // Exact match
                if ($name === $ctrlEntity) {
                    $score += 90;
                    $reasons[] = 'Direct controller for task entity';
                }
                // Partial: controller name contains entity or vice versa
                elseif (str_contains($name, $mainLower) || str_contains($mainLower, preg_replace('/controller$/', '', $name))) {
                    $score += 50;
                    $reasons[] = 'Related to task entity';
                }
            }

            // Keyword similarity
            foreach ($keywords as $kw) {
                if (str_contains($name, $kw) || str_contains($kw, $name)) {
                    $score += 20;
                    $reasons[] = "Similar to keyword '{$kw}'";
                    break;
                }
            }

            // Check namespace for relevance
            $ns = strtolower($c['namespace'] ?? '');
            foreach ($keywords as $kw) {
                if (str_contains($ns, $kw)) {
                    $score += 10;
                    break;
                }
            }

            // Check methods for relevance
            foreach ($c['methods'] ?? [] as $method) {
                foreach ($keywords as $kw) {
                    if (str_contains(strtolower($method), $kw)) {
                        $score += 5;
                        break 2;
                    }
                }
            }

            if ($score > 0) {
                $scored[] = [
                    'name' => $c['name'],
                    'type' => 'controller',
                    'score' => min($score, 95),
                    'reason' => implode('; ', array_slice($reasons, 0, 2)),
                    'methods' => $c['methods'] ?? [],
                    'path' => $c['path'] ?? '',
                ];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    private function scoreServices(array $data, array $keywords, ?string $mainEntity): array
    {
        $scored = [];
        foreach ($data['services']['items'] ?? [] as $s) {
            $score = 0;
            $reasons = [];
            $name = strtolower($s['name']);

            if ($mainEntity) {
                $svcEntity = strtolower($mainEntity . 'service');
                $mainLower = strtolower($mainEntity);
                if ($name === $svcEntity) {
                    $score += 90;
                    $reasons[] = 'Direct service for task entity';
                } elseif (str_contains($name, $mainLower) || str_contains($mainLower, preg_replace('/service$/', '', $name))) {
                    $score += 50;
                    $reasons[] = 'Related to task entity';
                }
            }

            foreach ($keywords as $kw) {
                if (str_contains($name, $kw) || str_contains($kw, $name)) {
                    $score += 20;
                    $reasons[] = "Similar to keyword '{$kw}'";
                    break;
                }
            }

            if ($score > 0) {
                $scored[] = [
                    'name' => $s['name'],
                    'type' => 'service',
                    'score' => min($score, 95),
                    'reason' => implode('; ', array_slice($reasons, 0, 2)),
                    'methods' => $s['methods'] ?? [],
                ];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    private function scoreRoutes(array $data, array $keywords, ?string $mainEntity): array
    {
        $scored = [];
        foreach ($data['routes']['items'] ?? [] as $r) {
            $score = 0;
            $uri = strtolower($r['uri'] ?? '');

            if ($mainEntity) {
                $mainLower = strtolower($mainEntity);
                if (str_contains($uri, $mainLower)) $score += 60;
            }

            foreach ($keywords as $kw) {
                if (str_contains($uri, $kw)) { $score += 25; break; }
            }

            if ($score > 0) {
                $scored[] = [
                    'uri' => $r['uri'] ?? '',
                    'methods' => $r['methods'] ?? [],
                    'score' => min($score, 95),
                ];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    private function scoreTables(array $data, array $keywords, ?string $mainEntity): array
    {
        $scored = [];
        foreach ($data['database']['tables'] ?? [] as $t) {
            $score = 0;
            $name = strtolower($t['name']);

            if ($mainEntity) {
                $tableEntity = strtolower($this->entityToTable($mainEntity));
                $mainLower = strtolower($mainEntity);
                if ($name === $tableEntity) $score += 90;
                elseif (str_contains($name, $mainLower)) $score += 50;
            }

            foreach ($keywords as $kw) {
                if (str_contains($name, $kw) || str_contains($kw, $name)) { $score += 20; break; }
            }

            if ($score > 0) {
                $scored[] = ['name' => $t['name'], 'score' => min($score, 95), 'columns' => $t['columns'] ?? []];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    private function scoreViews(array $data, array $keywords, ?string $mainEntity): array
    {
        $scored = [];
        foreach ($data['blade']['views'] ?? [] as $v) {
            $score = 0;
            $name = strtolower($v['name'] ?? '');
            $path = strtolower($v['path'] ?? '');

            if ($mainEntity && str_contains($name, strtolower($mainEntity))) $score += 50;
            foreach ($keywords as $kw) {
                if (str_contains($name, $kw) || str_contains($path, $kw)) { $score += 20; break; }
            }

            if ($score > 0) {
                $scored[] = ['name' => $v['name'] ?? '', 'score' => min($score, 90), 'path' => $v['path'] ?? ''];
            }
        }

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
        return $scored;
    }

    private function findSimilarImplementations(array $data, ?string $mainEntity): array
    {
        $similar = [];
        if (!$mainEntity) return $similar;

        foreach ($data['controllers']['items'] ?? [] as $c) {
            if (($c['is_crud'] ?? false) && strtolower($c['name']) !== strtolower($mainEntity . 'Controller')) {
                $similar[] = [
                    'name' => $c['name'],
                    'type' => 'controller',
                    'description' => 'CRUD controller — reference pattern',
                ];
                break;
            }
        }

        foreach ($data['services']['items'] ?? [] as $s) {
            if (count($s['methods'] ?? []) >= 3) {
                $similar[] = [
                    'name' => $s['name'],
                    'type' => 'service',
                    'description' => 'Service with ' . count($s['methods']) . ' methods — reference pattern',
                ];
                break;
            }
        }

        return $similar;
    }

    private function determineNewFiles(array $data, ?string $mainEntity, array $keywords): array
    {
        $newFiles = [];
        if (!$mainEntity) return $newFiles;

        $entity = $mainEntity;

        // Check if model exists (by name, or by keyword similarity)
        $modelExists = $this->entityExists($data['models']['items'] ?? [], $entity);
        if (!$modelExists) {
            $newFiles[] = [
                'path' => "app/Models/{$entity}.php",
                'type' => 'model',
                'reason' => "{$entity} model does not exist yet",
            ];
        }

        $controllerExists = $this->entityExists($data['controllers']['items'] ?? [], $entity . 'Controller');
        if (!$controllerExists) {
            $newFiles[] = [
                'path' => "app/Http/Controllers/{$entity}Controller.php",
                'type' => 'controller',
                'reason' => "{$entity} controller does not exist yet",
            ];
        }

        $serviceExists = $this->entityExists($data['services']['items'] ?? [], $entity . 'Service');
        if (!$serviceExists) {
            $newFiles[] = [
                'path' => "app/Services/{$entity}Service.php",
                'type' => 'service',
                'reason' => "{$entity} service does not exist yet",
            ];
        }

        $tableName = $this->entityToTable($entity);
        $migrationExists = false;
        foreach ($data['database']['tables'] ?? [] as $t) {
            if ($t['name'] === $tableName) { $migrationExists = true; break; }
        }
        if (!$migrationExists) {
            $newFiles[] = [
                'path' => "database/migrations/create_{$tableName}_table.php",
                'type' => 'migration',
                'reason' => "{$tableName} table does not exist yet",
            ];
        }

        $storeReqExists = $this->entityExists($data['form_requests']['items'] ?? [], "Store{$entity}Request");
        if (!$storeReqExists) {
            $newFiles[] = [
                'path' => "app/Http/Requests/Store{$entity}Request.php",
                'type' => 'form_request',
                'reason' => "Store request for {$entity} does not exist",
            ];
        }

        $updateReqExists = $this->entityExists($data['form_requests']['items'] ?? [], "Update{$entity}Request");
        if (!$updateReqExists) {
            $newFiles[] = [
                'path' => "app/Http/Requests/Update{$entity}Request.php",
                'type' => 'form_request',
                'reason' => "Update request for {$entity} does not exist",
            ];
        }

        $policyExists = $this->entityExists($data['policies']['items'] ?? [], $entity . 'Policy');
        if (!$policyExists) {
            $newFiles[] = [
                'path' => "app/Policies/{$entity}Policy.php",
                'type' => 'policy',
                'reason' => "Policy for {$entity} does not exist yet",
            ];
        }

        return $newFiles;
    }

    private function entityExists(array $items, string $name): bool
    {
        $nameLower = strtolower($name);
        foreach ($items as $item) {
            if (strtolower($item['name'] ?? '') === $nameLower) return true;
        }
        return false;
    }

    private function determineFilesToModify(array $data, array $scoredModels, array $scoredControllers, array $scoredServices): array
    {
        $files = [];

        foreach ($scoredControllers as $c) {
            if ($c['score'] >= 70 && !empty($c['path'])) {
                $files[] = [
                    'path' => "app/Http/Controllers/" . $c['path'],
                    'type' => 'controller',
                    'action' => 'Add new methods for the task',
                ];
            }
        }

        foreach ($scoredServices as $s) {
            if ($s['score'] >= 70) {
                $files[] = [
                    'path' => "app/Services/" . ($s['name'] ?? '') . ".php",
                    'type' => 'service',
                    'action' => 'Add business logic for the task',
                ];
            }
        }

        $files[] = [
            'path' => 'routes/web.php',
            'type' => 'route',
            'action' => 'Add new routes for the task',
        ];

        return $files;
    }

    private function assessRisks(array $data, ?string $mainEntity): array
    {
        $risks = [];
        if (!$mainEntity) return $risks;

        $conflicts = [];
        foreach ($data['models']['items'] ?? [] as $m) {
            if (str_contains(strtolower($m['name']), strtolower($mainEntity))) {
                $conflicts[] = "model:{$m['name']}";
            }
        }

        if (!empty($conflicts)) {
            $risks[] = [
                'type' => 'name_conflict',
                'message' => "Name '{$mainEntity}' partially matches: " . implode(', ', $conflicts),
            ];
        }

        $tableName = $this->entityToTable($mainEntity);
        foreach ($data['database']['tables'] ?? [] as $t) {
            if (str_contains($t['name'], $tableName) && $t['name'] !== $tableName) {
                $risks[] = [
                    'type' => 'table_conflict',
                    'message' => "Table '{$t['name']}' similar to '{$tableName}'",
                ];
                break;
            }
        }

        return $risks;
    }

    private function determineDependencies(array $data, ?string $mainEntity): array
    {
        $deps = ['Laravel framework (already installed)'];

        if (!empty($data['policies']['items'])) {
            $deps[] = 'Policies already set up — follow existing authorization pattern';
        }

        return $deps;
    }

    private function entityToTable(string $entity): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $entity)) . 's';
    }

    private function buildMarkdown(string $task, ?string $mainEntity, array $keywords,
        array $scoredModels, array $scoredControllers, array $scoredServices,
        array $scoredRoutes, array $scoredTables, array $scoredViews,
        array $filesToModify, array $newFilesNeeded, array $similarImplementations,
        array $dependencies, array $risks): array
    {
        $lines = [];

        $lines[] = '# Task Context: ' . $task;
        $lines[] = '';
        $lines[] = '> Generated by Laravel Beacon v1.0.0 — AI Working Context';
        $lines[] = '> This file prepares the exact context an AI needs before coding this task.';
        $lines[] = '> Confidence scores (0–100) indicate relevance to your task.';
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        $lines[] = '## Task Summary';
        $lines[] = '';
        $lines[] = "- **Task:** {$task}";
        $lines[] = "- **Main Entity Detected:** " . ($mainEntity ?: 'Not determined');
        $lines[] = "- **Keywords:** " . implode(', ', $keywords);
        $lines[] = '';
        $lines[] = '---';
        $lines[] = '';

        $highModels = array_filter($scoredModels, fn($m) => $m['score'] >= 50);
        if (!empty($highModels)) {
            $lines[] = '## Related Models';
            $lines[] = '';
            $lines[] = '| Model | Relevance | Reason |';
            $lines[] = '|-------|-----------|--------|';
            foreach ($highModels as $m) {
                $lines[] = "| `{$m['name']}` | {$m['score']}% | {$m['reason']} |";
            }
            $lines[] = '';
        } else {
            $lines[] = '## Related Models';
            $lines[] = '';
            $lines[] = 'No closely related models found. The task entity may be new.';
            $lines[] = '';
        }

        $highControllers = array_filter($scoredControllers, fn($c) => $c['score'] >= 40);
        if (!empty($highControllers)) {
            $lines[] = '## Related Controllers';
            $lines[] = '';
            $lines[] = '| Controller | Relevance | Reason |';
            $lines[] = '|-----------|-----------|--------|';
            foreach ($highControllers as $c) {
                $lines[] = "| `{$c['name']}` | {$c['score']}% | {$c['reason']} |";
            }
            $lines[] = '';
        }

        $highServices = array_filter($scoredServices, fn($s) => $s['score'] >= 40);
        if (!empty($highServices)) {
            $lines[] = '## Related Services';
            $lines[] = '';
            $lines[] = '| Service | Relevance | Reason |';
            $lines[] = '|---------|-----------|--------|';
            foreach ($highServices as $s) {
                $lines[] = "| `{$s['name']}` | {$s['score']}% | {$s['reason']} |";
            }
            $lines[] = '';
        }

        $highRoutes = array_filter($scoredRoutes, fn($r) => $r['score'] >= 40);
        if (!empty($highRoutes)) {
            $lines[] = '## Related Routes';
            $lines[] = '';
            $lines[] = '| Route | Methods | Relevance |';
            $lines[] = '|-------|---------|-----------|';
            foreach (array_slice($highRoutes, 0, 10) as $r) {
                $methods = implode(',', array_diff($r['methods'] ?? [], ['HEAD']));
                $lines[] = "| `{$r['uri']}` | {$methods} | {$r['score']}% |";
            }
            $lines[] = '';
        }

        $highTables = array_filter($scoredTables, fn($t) => $t['score'] >= 40);
        if (!empty($highTables)) {
            $lines[] = '## Related Database Tables';
            $lines[] = '';
            $lines[] = '| Table | Relevance |';
            $lines[] = '|------|-----------|';
            foreach ($highTables as $t) {
                $lines[] = "| `{$t['name']}` | {$t['score']}% |";
            }
            $lines[] = '';
        }

        if (!empty($filesToModify)) {
            $lines[] = '---';
            $lines[] = '## Files That Probably Need Modification';
            $lines[] = '';
            $lines[] = '| File | Type | Action |';
            $lines[] = '|------|------|--------|';
            foreach ($filesToModify as $f) {
                $lines[] = "| `{$f['path']}` | {$f['type']} | {$f['action']} |";
            }
            $lines[] = '';
        }

        if (!empty($newFilesNeeded)) {
            $lines[] = '---';
            $lines[] = '## New Files That Should Be Created';
            $lines[] = '';
            $lines[] = '| Suggested Path | Type | Reason |';
            $lines[] = '|---------------|------|--------|';
            foreach ($newFilesNeeded as $f) {
                $lines[] = "| `{$f['path']}` | {$f['type']} | {$f['reason']} |";
            }
            $lines[] = '';
        }

        if (!empty($similarImplementations)) {
            $lines[] = '---';
            $lines[] = '## Similar Implementations (Reference)';
            $lines[] = '';
            foreach ($similarImplementations as $impl) {
                $lines[] = "- **{$impl['name']}** ({$impl['type']}): {$impl['description']}";
            }
            $lines[] = '';
        }

        if (!empty($dependencies)) {
            $lines[] = '---';
            $lines[] = '## Dependencies';
            $lines[] = '';
            foreach ($dependencies as $dep) {
                $lines[] = "- {$dep}";
            }
            $lines[] = '';
        }

        if (!empty($risks)) {
            $lines[] = '---';
            $lines[] = '## Possible Risks';
            $lines[] = '';
            foreach ($risks as $risk) {
                $lines[] = "- **{$risk['type']}**: {$risk['message']}";
            }
            $lines[] = '';
        }

        return $lines;
    }
}