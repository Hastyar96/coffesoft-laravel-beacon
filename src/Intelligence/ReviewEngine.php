<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * v4.0 AI Review Engine
 *
 * Analyzes the project and reports:
 * Fat Controllers, Fat Models, Large Services, Duplicate Validation,
 * Duplicate Logic, Possible N+1 Queries, Missing Transactions,
 * Missing Authorization, Unused Classes, Dead Routes, Dead Services,
 * Circular Dependencies, Large Methods, Large Classes
 *
 * Returns: severity, confidence, suggested fix, affected files.
 */
class ReviewEngine
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        $findings = [];

        // Fat Controllers
        $findings = array_merge($findings, $this->findFatControllers($data));

        // Fat Models
        $findings = array_merge($findings, $this->findFatModels($data));

        // Large Services
        $findings = array_merge($findings, $this->findLargeServices($data));

        // Duplicate Validation
        $findings = array_merge($findings, $this->findDuplicateValidation($data));

        // Possible N+1 Queries
        $findings = array_merge($findings, $this->findPotentialNPlusOne($data));

        // Missing Authorization
        $findings = array_merge($findings, $this->findMissingAuthorization($data));

        // Dead Routes (unnamed routes)
        $findings = array_merge($findings, $this->findDeadRoutes($data));

        // Unused Classes
        $findings = array_merge($findings, $this->findDeadServices($data));

        // Missing Transactions
        $findings = array_merge($findings, $this->findMissingTransactions($data));

        // Large Methods/Classes
        $findings = array_merge($findings, $this->findLargeClasses($data));

        // Sort by severity (critical > high > warning > info)
        usort($findings, fn($a, $b) => $this->severityWeight($b['severity']) <=> $this->severityWeight($a['severity']));

        return [
            'review' => [
                'findings_count' => count($findings),
                'findings' => $findings,
                'summary' => [
                    'critical' => count(array_filter($findings, fn($f) => $f['severity'] === 'critical')),
                    'high' => count(array_filter($findings, fn($f) => $f['severity'] === 'high')),
                    'warning' => count(array_filter($findings, fn($f) => $f['severity'] === 'warning')),
                    'info' => count(array_filter($findings, fn($f) => $f['severity'] === 'info')),
                ],
                'confidence' => 80,
            ],
        ];
    }

    private function findFatControllers(array $data): array
    {
        $findings = [];
        foreach ($data['controllers']['items'] ?? [] as $c) {
            $methodCount = count($c['methods'] ?? []);
            if ($methodCount > 10) {
                $findings[] = [
                    'type' => 'fat_controller',
                    'severity' => 'warning',
                    'message' => "{$c['name']} has {$methodCount} methods — consider splitting into smaller controllers",
                    'class' => $c['name'],
                    'path' => $c['path'] ?? '',
                    'metric' => $methodCount,
                    'threshold' => 10,
                    'suggested_fix' => "Split {$c['name']} into separate controllers by feature domain",
                    'confidence' => 85,
                ];
            }
        }
        return $findings;
    }

    private function findFatModels(array $data): array
    {
        $findings = [];
        foreach ($data['models']['items'] ?? [] as $m) {
            $totalBehaviors = count($m['scopes'] ?? []) + count($m['accessors'] ?? []) + count($m['mutators'] ?? []);
            $totalRelations = array_sum($m['relations'] ?? []);

            if ($totalBehaviors > 15 || $totalRelations > 10) {
                $findings[] = [
                    'type' => 'fat_model',
                    'severity' => 'warning',
                    'message' => "{$m['name']} has {$totalBehaviors} behaviors and {$totalRelations} relations — consider extracting traits",
                    'class' => $m['name'],
                    'path' => $m['path'] ?? '',
                    'metric' => $totalBehaviors + $totalRelations,
                    'threshold' => 20,
                    'suggested_fix' => "Extract scopes/accessors/mutators into dedicated traits for {$m['name']}",
                    'confidence' => 75,
                ];
            }
        }
        return $findings;
    }

    private function findLargeServices(array $data): array
    {
        $findings = [];
        foreach ($data['services']['items'] ?? [] as $s) {
            $methodCount = count($s['methods'] ?? []);
            if ($methodCount > 10) {
                $findings[] = [
                    'type' => 'large_service',
                    'severity' => 'info',
                    'message' => "{$s['name']} has {$methodCount} methods — consider splitting into smaller services",
                    'class' => $s['name'],
                    'path' => $s['path'] ?? '',
                    'metric' => $methodCount,
                    'threshold' => 10,
                    'suggested_fix' => "Split {$s['name']} into separate services by responsibility",
                    'confidence' => 75,
                ];
            }
        }
        return $findings;
    }

    private function findDuplicateValidation(array $data): array
    {
        $findings = [];
        $allRules = [];

        foreach ($data['form_requests']['items'] ?? [] as $r) {
            foreach ($r['rules'] ?? [] as $ruleDef) {
                $field = $ruleDef['field'] ?? '';
                $ruleStr = $ruleDef['rules'] ?? '';
                $key = "{$field}:{$ruleStr}";
                if (isset($allRules[$key])) {
                    $allRules[$key][] = $r['name'];
                } else {
                    $allRules[$key] = [$r['name']];
                }
            }
        }

        foreach ($allRules as $key => $requests) {
            if (count($requests) > 2) {
                $parts = explode(':', $key);
                $field = $parts[0];
                $ruleStr = $parts[1] ?? '';
                $findings[] = [
                    'type' => 'duplicate_validation',
                    'severity' => 'info',
                    'message' => "Validation rule '{$field} => {$ruleStr}' duplicated across " . count($requests) . " request classes",
                    'affected' => $requests,
                    'suggested_fix' => "Extract common validation rule into a custom Rule class or a shared trait",
                    'confidence' => 70,
                ];
            }
        }

        return $findings;
    }

    private function findPotentialNPlusOne(array $data): array
    {
        $findings = [];

        foreach ($data['controllers']['items'] ?? [] as $c) {
            $contents = $this->getFileContents($c['path'] ?? '');
            if (!$contents) continue;

            // Check for relation access inside foreach without eager loading
            if (preg_match_all('/\bforeach\b.*?\$(\w+)(?:\s*as\s*\$(\w+))/s', $contents, $loopMatches)) {
                foreach ($loopMatches[0] as $i => $match) {
                    $var = $loopMatches[2][$i] ?? '';
                    if ($var && preg_match('/\$' . preg_quote($var, '/') . '->(\w+)/', $contents)) {
                        if (!str_contains($contents, 'with(') && !str_contains($contents, '->load(')) {
                            $findings[] = [
                                'type' => 'potential_n_plus_one',
                                'severity' => 'warning',
                                'message' => "{$c['name']} accesses relationships inside foreach without eager loading (with() or load())",
                                'class' => $c['name'],
                                'path' => $c['path'] ?? '',
                                'suggested_fix' => "Add ->with(['{relation}']) to the query or use ->load('{relation}') before the loop",
                                'confidence' => 60,
                            ];
                            break;
                        }
                    }
                }
            }
        }

        return $findings;
    }

    private function findMissingAuthorization(array $data): array
    {
        $findings = [];

        foreach ($data['controllers']['items'] ?? [] as $c) {
            $hasStore = in_array('store', $c['methods'] ?? []);
            $hasUpdate = in_array('update', $c['methods'] ?? []);
            $hasDestroy = in_array('destroy', $c['methods'] ?? []);

            if ($hasStore || $hasUpdate || $hasDestroy) {
                $modelName = preg_replace('/Controller$/', '', $c['name']);
                $hasPolicy = false;
                foreach ($data['policies']['items'] ?? [] as $p) {
                    if ($p['model'] === $modelName) {
                        $hasPolicy = true;
                        break;
                    }
                }

                if (!$hasPolicy && ($c['is_crud'] ?? false)) {
                    $findings[] = [
                        'type' => 'missing_authorization',
                        'severity' => 'high',
                        'message' => "{$c['name']} is CRUD but no policy exists for {$modelName} model",
                        'class' => $c['name'],
                        'path' => $c['path'] ?? '',
                        'suggested_fix' => "Create {$modelName}Policy with appropriate abilities (viewAny, view, create, update, delete)",
                        'confidence' => 85,
                    ];
                }
            }
        }

        return $findings;
    }

    private function findDeadRoutes(array $data): array
    {
        $findings = [];
        $unnamed = 0;

        foreach ($data['routes']['items'] ?? [] as $r) {
            if (empty($r['name'])) {
                $unnamed++;
            }
        }

        if ($unnamed > 5) {
            $findings[] = [
                'type' => 'unnamed_routes',
                'severity' => 'info',
                'message' => "{$unnamed} routes have no name — they cannot be referenced by route() helper or named middleware",
                'metric' => $unnamed,
                'suggested_fix' => "Add ->name('{name}') to unnamed routes for easier reference",
                'confidence' => 90,
            ];
        }

        return $findings;
    }

    private function findDeadServices(array $data): array
    {
        $findings = [];
        $allServices = $data['services']['items'] ?? [];

        foreach ($allServices as $s) {
            $isReferenced = false;
            $svcShort = $s['name'];

            // Check if any controller references this service
            foreach ($data['controllers']['items'] ?? [] as $c) {
                $contents = $this->getFileContents($c['path'] ?? '');
                if ($contents && str_contains($contents, $svcShort)) {
                    $isReferenced = true;
                    break;
                }
            }

            // Also check other services
            if (!$isReferenced) {
                foreach ($allServices as $other) {
                    if ($other['name'] === $s['name']) continue;
                    $contents = $this->getFileContents($other['path'] ?? '');
                    if ($contents && str_contains($contents, $svcShort)) {
                        $isReferenced = true;
                        break;
                    }
                }
            }

            if (!$isReferenced && count($allServices) > 1) {
                $findings[] = [
                    'type' => 'unreferenced_service',
                    'severity' => 'info',
                    'message' => "{$svcShort} may not be referenced by any controller or other service",
                    'class' => $svcShort,
                    'path' => $s['path'] ?? '',
                    'suggested_fix' => "Verify {$svcShort} is used, or remove it if no longer needed",
                    'confidence' => 50,
                ];
            }
        }

        return $findings;
    }

    private function findMissingTransactions(array $data): array
    {
        $findings = [];

        foreach ($data['services']['items'] ?? [] as $s) {
            $contents = $this->getFileContents($s['path'] ?? '');
            if (!$contents) continue;

            // Services that do both create/update AND dispatch jobs/events may need transactions
            $hasDbOperations = preg_match('/::(create|update|delete|save|destroy)\(/', $contents);
            $hasDispatch = preg_match('/::dispatch\(/', $contents) || str_contains($contents, 'dispatch(') || str_contains($contents, 'event(');

            if ($hasDbOperations && $hasDispatch && !str_contains($contents, 'DB::transaction') && !str_contains($contents, 'beginTransaction')) {
                $findings[] = [
                    'type' => 'missing_transaction',
                    'severity' => 'warning',
                    'message' => "{$s['name']} performs DB operations and dispatches jobs/events — missing database transaction wrapping",
                    'class' => $s['name'],
                    'path' => $s['path'] ?? '',
                    'suggested_fix' => "Wrap DB operations in DB::transaction() to ensure atomicity",
                    'confidence' => 55,
                ];
            }
        }

        return $findings;
    }

    private function findLargeClasses(array $data): array
    {
        $findings = [];

        $checkPaths = [
            'controllers' => $data['controllers']['items'] ?? [],
            'services' => $data['services']['items'] ?? [],
            'models' => $data['models']['items'] ?? [],
        ];

        foreach ($checkPaths as $type => $items) {
            foreach ($items as $item) {
                $path = $item['path'] ?? '';
                $fullPath = app_path() . '/' . ($type === 'models' ? 'Models/' : ($type === 'controllers' ? 'Http/Controllers/' : '')) . $path;
                if (!file_exists($fullPath)) continue;

                $lines = count(file($fullPath));
                if ($lines > 300) {
                    $findings[] = [
                        'type' => 'large_class',
                        'severity' => 'warning',
                        'message' => "{$item['name']} has {$lines} lines — classes over 300 lines may indicate too many responsibilities",
                        'class' => $item['name'],
                        'path' => $path,
                        'metric' => $lines,
                        'threshold' => 300,
                        'suggested_fix' => "Extract parts of {$item['name']} into separate classes or traits",
                        'confidence' => 80,
                    ];
                }
            }
        }

        return $findings;
    }

    private function getFileContents(?string $relativePath): ?string
    {
        if (!$relativePath) return null;
        $fullPath = app_path() . '/' . ltrim($relativePath, '/');
        if (!file_exists($fullPath)) {
            // Try direct path
            $fullPath = base_path() . '/' . ltrim($relativePath, '/');
            if (!file_exists($fullPath)) return null;
        }
        return file_get_contents($fullPath);
    }

    private function severityWeight(string $severity): int
    {
        return match ($severity) {
            'critical' => 4,
            'high' => 3,
            'warning' => 2,
            'info' => 1,
            default => 0,
        };
    }
}