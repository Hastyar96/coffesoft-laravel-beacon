<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Review Engine — Smarter, evidence-based code quality analysis.
 *
 * Instead of just saying "Controller too large", suggests exactly HOW to split it
 * based on detected method patterns. Every finding includes severity, confidence,
 * evidence, suggested fix, and affected files.
 */
class ReviewEngine
{
    public function analyze(array $data): array
    {
        $findings = [];

        $findings = array_merge($findings, $this->findFatControllers($data));
        $findings = array_merge($findings, $this->findFatModels($data));
        $findings = array_merge($findings, $this->findLargeServices($data));
        $findings = array_merge($findings, $this->findDuplicateValidation($data));
        $findings = array_merge($findings, $this->findPotentialNPlusOne($data));
        $findings = array_merge($findings, $this->findMissingAuthorization($data));
        $findings = array_merge($findings, $this->findDeadRoutes($data));
        $findings = array_merge($findings, $this->findMissingTransactions($data));
        $findings = array_merge($findings, $this->findLargeClasses($data));
        $findings = array_merge($findings, $this->findDeadControllers($data));
        $findings = array_merge($findings, $this->findRouteInconsistencies($data));
        $findings = array_merge($findings, $this->findRepeatedBusinessLogic($data));

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

    /**
     * SMART split suggestion: groups methods into logical sub-controllers.
     */
    private function findFatControllers(array $data): array
    {
        $findings = [];
        $modelNames = array_map(fn($m) => $m['name'], $data['models']['items'] ?? []);

        foreach ($data['controllers']['items'] ?? [] as $c) {
            $methods = $c['methods'] ?? [];
            $methodCount = count($methods);
            if ($methodCount <= 10) continue;

            $evidence = "{$methodCount} public methods detected";
            $suggestedSplits = $this->suggestControllerSplit($c['name'], $methods, $modelNames);
            $splitCount = count($suggestedSplits);

            $fix = "Split into separate controllers by domain:";
            foreach ($suggestedSplits as $split) {
                $fix .= "\n      - {$split['controller']}: " . implode(', ', $split['methods']);
            }

            $findings[] = [
                'type' => 'fat_controller',
                'severity' => 'warning',
                'message' => "{$c['name']} has {$methodCount} methods — suggest splitting into {$splitCount} sub-controllers",
                'class' => $c['name'],
                'path' => $c['path'] ?? '',
                'metric' => $methodCount,
                'threshold' => 10,
                'evidence' => $evidence,
                'suggested_fix' => $fix,
                'suggested_splits' => $suggestedSplits,
                'confidence' => 80,
            ];
        }
        return $findings;
    }

    /**
     * Groups controller methods into logical sub-controllers by prefix.
     */
    private function suggestControllerSplit(string $ctrlName, array $methods, array $modelNames): array
    {
        $groups = [];
        $used = [];

        foreach ($methods as $m) {
            // Try to match methods to model names
            foreach ($modelNames as $model) {
                $modelLower = strtolower($model);
                if (str_contains(strtolower($m), $modelLower) && !in_array($m, $used)) {
                    $key = $model . 'Controller';
                    $groups[$key][] = $m;
                    $used[] = $m;
                    break;
                }
            }
        }

        // Remaining methods grouped by prefix pattern
        $prefixGroups = [];
        foreach ($methods as $m) {
            if (in_array($m, $used)) continue;
            $prefix = preg_replace('/[A-Z].*/', '', $m);
            $prefixGroups[$prefix][] = $m;
            $used[] = $m;
        }

        $result = [];
        $baseName = preg_replace('/Controller$/', '', $ctrlName);

        foreach ($groups as $ctrl => $methods) {
            if (count($methods) >= 2) {
                $result[] = ['controller' => $ctrl, 'methods' => $methods];
            }
        }
        foreach ($prefixGroups as $prefix => $methods) {
            if (count($methods) >= 2) {
                $result[] = ['controller' => "{$prefix}Controller", 'methods' => $methods];
            }
        }

        // If no good splits found, suggest by feature
        if (empty($result)) {
            $result[] = ['controller' => "{$baseName}Controller", 'methods' => $methods];
        }

        return $result;
    }

    private function findFatModels(array $data): array
    {
        $findings = [];
        foreach ($data['models']['items'] ?? [] as $m) {
            $totalBehaviors = count($m['scopes'] ?? []) + count($m['accessors'] ?? []) + count($m['mutators'] ?? []);
            $totalRelations = count($m['relations'] ?? []);
            $total = $totalBehaviors + $totalRelations;

            if ($total <= 20) continue;

            $scopes = $m['scopes'] ?? [];
            $accessors = $m['accessors'] ?? [];

            $fix = "Extract into traits:";
            if (!empty($scopes)) $fix .= "\n      - {$m['name']}Scopes (query scopes)";
            if (!empty($accessors)) $fix .= "\n      - {$m['name']}Accessors (accessors/mutators)";
            if ($totalRelations > 8) $fix .= "\n      - {$m['name']}Relationships (hasMany/belongsTo definitions)";

            $findings[] = [
                'type' => 'fat_model',
                'severity' => 'warning',
                'message' => "{$m['name']} has {$totalBehaviors} behaviors and {$totalRelations} relations — consider extracting traits",
                'class' => $m['name'],
                'path' => $m['path'] ?? '',
                'metric' => $total,
                'threshold' => 20,
                'evidence' => "{$totalBehaviors} scopes/accessors/mutators, {$totalRelations} relations",
                'suggested_fix' => $fix,
                'confidence' => 75,
            ];
        }
        return $findings;
    }

    private function findLargeServices(array $data): array
    {
        $findings = [];
        foreach ($data['services']['items'] ?? [] as $s) {
            $methodCount = count($s['methods'] ?? []);
            if ($methodCount <= 10) continue;

            $responsibilities = $s['responsibilities'] ?? [];
            $groups = [];
            foreach ($s['methods'] ?? [] as $m) {
                $prefix = preg_replace('/[A-Z].*/', '', $m);
                $groups[$prefix][] = $m;
            }

            $fix = "Split {$s['name']} into:";
            $splitCount = 0;
            foreach ($groups as $prefix => $methods) {
                if (count($methods) >= 2 && $splitCount < 3) {
                    $fix .= "\n      - " . ucfirst($prefix) . ucfirst($prefix) . "Service: " . implode(', ', $methods);
                    $splitCount++;
                }
            }
            if ($splitCount === 0) {
                $fix .= "\n      - Separate services by feature domain";
            }

            $findings[] = [
                'type' => 'large_service',
                'severity' => 'info',
                'message' => "{$s['name']} has {$methodCount} methods — suggest splitting into smaller services",
                'class' => $s['name'],
                'path' => $s['path'] ?? '',
                'metric' => $methodCount,
                'threshold' => 10,
                'evidence' => "{$methodCount} methods" . (!empty($responsibilities) ? ", responsibilities: " . implode(', ', $responsibilities) : ''),
                'suggested_fix' => $fix,
                'confidence' => 75,
            ];
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
                $allRules[$key][] = $r['name'];
            }
        }

        foreach ($allRules as $key => $requests) {
            if (count($requests) <= 2) continue;
            $parts = explode(':', $key);
            $field = $parts[0];
            $ruleStr = $parts[1] ?? '';

            $findings[] = [
                'type' => 'duplicate_validation',
                'severity' => 'info',
                'message' => "Validation rule '{$field} => {$ruleStr}' duplicated across " . count($requests) . " request classes",
                'affected' => $requests,
                'evidence' => "Found in: " . implode(', ', $requests),
                'suggested_fix' => "Extract into a custom Rule class (php artisan make:rule {$field}Rule) or a shared trait",
                'confidence' => 70,
            ];
        }

        return $findings;
    }

    private function findPotentialNPlusOne(array $data): array
    {
        $findings = [];
        foreach ($data['controllers']['items'] ?? [] as $c) {
            $contents = $this->getFileContents($c['path'] ?? '');
            if (!$contents) continue;

            if (preg_match_all('/\bforeach\b[^{]*\{[^}]*\$(\w+)->(\w+)/s', $contents, $matches, PREG_SET_ORDER)) {
                $hasEager = str_contains($contents, 'with(') || str_contains($contents, '->load(');
                if (!$hasEager) {
                    $findings[] = [
                        'type' => 'potential_n_plus_one',
                        'severity' => 'warning',
                        'message' => "{$c['name']} accesses relationships (e.g. ->{$matches[0][2]}) inside foreach without with() or load()",
                        'class' => $c['name'],
                        'path' => $c['path'] ?? '',
                        'evidence' => "Foreach loop accessing ->{$matches[0][2]} without eager loading",
                        'suggested_fix' => "Add ->with(['{$matches[0][2]}']) to the query or use ->load('{$matches[0][2]}') before the loop",
                        'confidence' => 60,
                    ];
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
            if (!($hasStore || $hasUpdate || $hasDestroy)) continue;
            if (!($c['is_crud'] ?? false)) continue;

            $modelName = preg_replace('/Controller$/', '', $c['name']);
            $hasPolicy = false;
            foreach ($data['policies']['items'] ?? [] as $p) {
                if ($p['model'] === $modelName) { $hasPolicy = true; break; }
            }

            if (!$hasPolicy) {
                $findings[] = [
                    'type' => 'missing_authorization',
                    'severity' => 'high',
                    'message' => "{$c['name']} is CRUD but no policy exists for {$modelName} model",
                    'class' => $c['name'],
                    'path' => $c['path'] ?? '',
                    'evidence' => "CRUD controller with store/update/destroy but no Policy registered for {$modelName}",
                    'suggested_fix' => "Create {$modelName}Policy:\n" .
                        "      php artisan make:policy {$modelName}Policy --model={$modelName}\n" .
                        "      Add abilities: viewAny, view, create, update, delete",
                    'confidence' => 85,
                ];
            }
        }
        return $findings;
    }

    private function findDeadRoutes(array $data): array
    {
        $findings = [];
        $unnamed = 0;
        foreach ($data['routes']['items'] ?? [] as $r) {
            if (empty($r['name'])) $unnamed++;
        }

        if ($unnamed > 5) {
            $findings[] = [
                'type' => 'unnamed_routes',
                'severity' => 'info',
                'message' => "{$unnamed} routes have no name — they cannot be referenced by route() helper",
                'metric' => $unnamed,
                'evidence' => "{$unnamed} of " . count($data['routes']['items'] ?? []) . " routes are unnamed",
                'suggested_fix' => "Add ->name('name') to unnamed routes for easier reference and URL generation",
                'confidence' => 90,
            ];
        }
        return $findings;
    }

    private function findMissingTransactions(array $data): array
    {
        $findings = [];
        foreach ($data['services']['items'] ?? [] as $s) {
            $contents = $this->getFileContents($s['path'] ?? '');
            if (!$contents) continue;

            $hasDbOps = preg_match('/::(create|update|delete|save|destroy)\(/', $contents);
            $hasDispatch = str_contains($contents, 'dispatch(') || str_contains($contents, 'event(');
            $hasTransaction = str_contains($contents, 'DB::transaction') || str_contains($contents, 'beginTransaction');

            if ($hasDbOps && $hasDispatch && !$hasTransaction) {
                $findings[] = [
                    'type' => 'missing_transaction',
                    'severity' => 'warning',
                    'message' => "{$s['name']} does DB operations AND dispatches jobs/events — wrap in DB::transaction() for atomicity",
                    'class' => $s['name'],
                    'path' => $s['path'] ?? '',
                    'evidence' => 'DB operations and dispatch() calls detected without transaction wrapping',
                    'suggested_fix' => "Wrap the DB operations in:\n" .
                        "      DB::transaction(function () {\n" .
                        "          // existing code here\n" .
                        "      });",
                    'confidence' => 55,
                ];
            }
        }
        return $findings;
    }

    private function findLargeClasses(array $data): array
    {
        $findings = [];
        $checks = [
            'controllers' => ['dir' => 'Http/Controllers/', 'items' => $data['controllers']['items'] ?? []],
            'services' => ['dir' => '', 'items' => $data['services']['items'] ?? []],
            'models' => ['dir' => 'Models/', 'items' => $data['models']['items'] ?? []],
        ];

        foreach ($checks as $type => $check) {
            foreach ($check['items'] as $item) {
                $path = $item['path'] ?? '';
                // Try multiple path strategies
                $fullPath = app_path($check['dir'] . $path);
                if (!file_exists($fullPath)) {
                    $fullPath = base_path($path);
                    if (!file_exists($fullPath)) continue;
                }

                $lines = count(file($fullPath));
                if ($lines > 300) {
                    $findings[] = [
                        'type' => 'large_class',
                        'severity' => 'warning',
                        'message' => "{$item['name']} has {$lines} lines (threshold: 300) — consider extracting smaller classes",
                        'class' => $item['name'],
                        'path' => $path,
                        'metric' => $lines,
                        'threshold' => 300,
                        'evidence' => "{$lines} lines of code in a single file",
                        'suggested_fix' => "Extract related methods into separate classes or traits. Target: < 200 lines per class.",
                        'confidence' => 80,
                    ];
                }
            }
        }
        return $findings;
    }

    private function findDeadControllers(array $data): array
    {
        $findings = [];
        foreach ($data['controllers']['items'] ?? [] as $c) {
            $hasRoute = false;
            foreach ($data['routes']['items'] ?? [] as $r) {
                if (str_contains($r['action'] ?? '', "\\{$c['name']}@") || str_contains($r['action'] ?? '', "{$c['name']}@")) {
                    $hasRoute = true;
                    break;
                }
            }
            if (!$hasRoute) {
                $findings[] = [
                    'type' => 'dead_controller',
                    'severity' => 'info',
                    'message' => "{$c['name']} has no registered routes — it may be dead code",
                    'class' => $c['name'],
                    'path' => $c['path'] ?? '',
                    'evidence' => 'No routes reference this controller in web.php or api.php',
                    'suggested_fix' => "Verify {$c['name']} is used. If not, remove it.",
                    'confidence' => 70,
                ];
            }
        }
        return $findings;
    }

    private function findRouteInconsistencies(array $data): array
    {
        $findings = [];
        $routeMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
        $usedMethods = [];
        $consistency = [];

        foreach ($data['routes']['items'] ?? [] as $r) {
            foreach ($r['methods'] ?? [] as $m) {
                if ($m === 'HEAD') continue;
                $usedMethods[$m] = ($usedMethods[$m] ?? 0) + 1;
                $prefix = explode('/', $r['uri'] ?? '')[0];
                $consistency[$prefix][$m] = ($consistency[$prefix][$m] ?? 0) + 1;
            }
        }

        // Check for inconsistent method usage across route groups
        foreach ($consistency as $prefix => $methods) {
            $total = array_sum($methods);
            // If a prefix has many GET routes but few others, flag it
            if ($total >= 5 && isset($methods['GET']) && $methods['GET'] > $total * 0.8) {
                $findings[] = [
                    'type' => 'route_inconsistency',
                    'severity' => 'info',
                    'message' => "Route group '{$prefix}' uses mostly GET requests ({$methods['GET']}/{$total}) — consider adding other HTTP methods if needed",
                    'evidence' => "GET: {$methods['GET']}, others: " . ($total - $methods['GET']),
                    'suggested_fix' => "Review route methods in routes/{$prefix}.php for completeness",
                    'confidence' => 40,
                ];
            }
        }
        return $findings;
    }

    private function findRepeatedBusinessLogic(array $data): array
    {
        $findings = [];
        $checked = [];

        // Check for similar method patterns across services
        foreach ($data['services']['items'] ?? [] as $s1) {
            foreach ($data['services']['items'] ?? [] as $s2) {
                if ($s1['name'] >= $s2['name']) continue;
                $key = $s1['name'] . '|' . $s2['name'];
                if (isset($checked[$key])) continue;
                $checked[$key] = true;

                $methods1 = array_map('strtolower', $s1['methods'] ?? []);
                $methods2 = array_map('strtolower', $s2['methods'] ?? []);
                $common = array_intersect($methods1, $methods2);

                if (count($common) >= 4) {
                    $findings[] = [
                        'type' => 'repeated_business_logic',
                        'severity' => 'info',
                        'message' => "{$s1['name']} and {$s2['name']} share " . count($common) . " method names — possible duplicated logic",
                        'affected' => [$s1['name'], $s2['name']],
                        'evidence' => "Shared methods: " . implode(', ', array_slice($common, 0, 6)) . (count($common) > 6 ? '...' : ''),
                        'suggested_fix' => "Extract shared logic into a base service class or trait",
                        'confidence' => 40,
                    ];
                }
            }
        }
        return $findings;
    }

    private function getFileContents(?string $relativePath): ?string
    {
        if (!$relativePath) return null;
        $paths = [
            app_path(ltrim($relativePath, '/')),
            base_path(ltrim($relativePath, '/')),
        ];
        foreach ($paths as $p) {
            if (file_exists($p)) return file_get_contents($p);
        }
        return null;
    }

    private function severityWeight(string $severity): int
    {
        return match ($severity) {
            'critical' => 4, 'high' => 3, 'warning' => 2, 'info' => 1, default => 0,
        };
    }
}