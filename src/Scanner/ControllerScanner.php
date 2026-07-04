<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\MethodBodyAnalyzer;
use Coffesoft\LaravelBeacon\Reader\PhpParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Improved ControllerScanner — extracts proven controller metadata.
 *
 * Uses token-based AST analysis (MethodBodyAnalyzer) to extract
 * proven relationships: constructor dependencies, models used,
 * services, events, jobs, notifications, views, redirects, transactions.
 *
 * No naming-convention-based inference. Only proven extraction.
 * ONLY uses static source code parsing - no class instantiation, no autoloading.
 */
class ControllerScanner
{
    public function __construct(
        private readonly MethodBodyAnalyzer $methodAnalyzer,
        private readonly PhpParser $phpParser,
    ) {}

    /**
     * Scan controllers and return structured data.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $path = app_path('Http/Controllers');

        if (! is_dir($path)) {
            return ['controllers' => ['count' => 0, 'items' => []]];
        }

        $files = $this->getPhpFiles($path);
        $items = [];

        foreach ($files as $file) {
            $relativePath = $file['relative_path'];

            $contents = file_get_contents($file['pathname']);
            if ($contents === false) {
                continue;
            }

            $parsed = $this->phpParser->parse($contents);

            $name = $parsed['class_name'] ?? $this->extractClassName($contents);
            $namespace = $parsed['namespace'] ?? $this->extractNamespace($contents) ?? 'App\\Http\\Controllers';

            if ($name === null) {
                continue;
            }

            $fqcn = $namespace . '\\' . $name;

            // Extract proven method list from PhpParser (more accurate)
            $methods = $this->extractPublicMethods($parsed['methods'] ?? []);

            // Extract constructor dependencies (proven from type hints)
            $constructorDeps = $this->extractConstructorDependencies($contents);

            // Extract middleware from $this->middleware() (proven from code)
            $middleware = $this->extractMiddleware($contents);

            // Extract policy/gate checks (proven from code)
            $policies = $this->extractPolicyChecks($contents);

            // Extract validation request classes (proven from type hints + $this->validate())
            $validationClasses = $this->extractValidation($contents);

            // Analyze each public method using MethodBodyAnalyzer
            $methodAnalyses = [];
            foreach ($parsed['methods'] ?? [] as $method) {
                if ($method['visibility'] !== 'public') {
                    continue;
                }

                $methodName = $method['name'];
                // Skip magic methods
                if (in_array($methodName, ['__construct', '__destruct', '__call', '__callStatic', '__invoke'])) {
                    continue;
                }

                $methodAnalysis = $this->methodAnalyzer->analyzeMethod($contents, $methodName);

                if ($this->methodHasRelevantContent($methodAnalysis)) {
                    $methodAnalyses[] = $methodAnalysis;
                }
            }

            // Aggregate dependencies across all methods
            $aggregated = $this->aggregateDependencies($methodAnalyses);

            $items[] = [
                // Core metadata (backward compatible)
                'name' => $name,
                'namespace' => $namespace,
                'fqcn' => $fqcn,
                'path' => $relativePath,
                'methods' => $methods,
                'group' => $this->detectGroup($relativePath),
                'is_crud' => $this->isCrudController($methods),
                'is_resource' => $this->isResourceController($name),
                'line_count' => $parsed['line_count'] ?? 0,

                // v6 Proven metadata
                'parent' => $parsed['parent'],
                'interfaces' => $parsed['interfaces'],
                'is_abstract' => $parsed['is_abstract'],
                'is_final' => $parsed['is_final'],
                'traits' => $parsed['traits'] ?? [],
                'traits_count' => count($parsed['traits'] ?? []),

                // Constructor (proven)
                'constructor_dependencies' => $constructorDeps,
                'constructor_dependency_count' => count($constructorDeps),

                // Middleware (proven from code)
                'middleware' => $middleware,
                'middleware_count' => count($middleware),

                // Policy checks (proven from code)
                'policy_checks' => $policies,
                'policy_checks_count' => count($policies),

                // Validation (proven from code)
                'validation_classes' => $validationClasses,
                'validation_classes_count' => count($validationClasses),

                // Proven aggregated dependencies
                'models_used' => $aggregated['models_used'],
                'models_used_count' => count($aggregated['models_used']),
                'services_used' => $aggregated['services_used'],
                'services_used_count' => count($aggregated['services_used']),
                'form_requests_used' => $aggregated['form_requests_used'],
                'form_requests_used_count' => count($aggregated['form_requests_used']),
                'resources_used' => $aggregated['resources_used'],
                'resources_used_count' => count($aggregated['resources_used']),
                'events_dispatched' => $aggregated['events_dispatched'],
                'events_dispatched_count' => count($aggregated['events_dispatched']),
                'jobs_dispatched' => $aggregated['jobs_dispatched'],
                'jobs_dispatched_count' => count($aggregated['jobs_dispatched']),
                'notifications_sent' => $aggregated['notifications_sent'],
                'notifications_sent_count' => count($aggregated['notifications_sent']),
                'views_returned' => $aggregated['views_returned'],
                'views_returned_count' => count($aggregated['views_returned']),
                'redirects' => $aggregated['redirects'],
                'redirects_count' => count($aggregated['redirects']),
                'database_transactions' => $aggregated['database_transactions'],
                'database_transactions_count' => count($aggregated['database_transactions']),

                // Per-method breakdown
                'public_methods' => array_map(fn($m) => [
                    'name' => $m['method'],
                    'start_line' => $m['start_line'],
                    'end_line' => $m['end_line'],
                    'models_used' => array_map(fn($mu) => $mu['class'], $m['models_used'] ?? []),
                    'events' => array_map(fn($e) => $e['class'], $m['events_dispatched'] ?? []),
                    'jobs' => array_map(fn($j) => $j['class'], $m['jobs_dispatched'] ?? []),
                    'views' => array_map(fn($v) => $v['name'], $m['views_returned'] ?? []),
                    'services' => array_map(fn($s) => $s['class'], $m['services_called'] ?? []),
                ], $methodAnalyses),

                'methods_analyzed' => count($methodAnalyses),
                'methods_count' => count($methods),
            ];
        }

        usort($items, fn($a, $b) => $a['fqcn'] <=> $b['fqcn']);

        return [
            'controllers' => [
                'count' => count($items),
                'items' => $items,
            ],
        ];
    }

    /**
     * Extract public method names from PhpParser results.
     */
    private function extractPublicMethods(array $methods): array
    {
        $public = [];
        foreach ($methods as $method) {
            if ($method['visibility'] === 'public' && !in_array($method['name'], ['__construct', '__destruct', '__call', '__callStatic', '__invoke'])) {
                $public[] = $method['name'];
            }
        }
        return $public;
    }

    private function extractConstructorDependencies(string $contents): array
    {
        $deps = [];
        $tokens = $this->tokenize($contents);
        if (empty($tokens)) return $deps;

        $count = count($tokens);
        for ($i = 0; $i < $count - 3; $i++) {
            if ($tokens[$i]->id === T_FUNCTION
                && $tokens[$i + 1]?->id === T_WHITESPACE
                && $tokens[$i + 2]?->id === T_STRING
                && $tokens[$i + 2]->text === '__construct') {

                for ($j = $i; $j < $count; $j++) {
                    if ($tokens[$j]->text === '(') {
                        $params = [];
                        $depth = 1;
                        for ($k = $j + 1; $k < $count && $depth > 0; $k++) {
                            if ($tokens[$k]->text === '(') $depth++;
                            elseif ($tokens[$k]->text === ')') $depth--;
                            if ($depth > 0) $params[] = $tokens[$k];
                        }

                        for ($p = 0; $p < count($params); $p++) {
                            if ($params[$p]->id === T_VARIABLE) {
                                $typeHint = null;
                                for ($b = $p - 1; $b >= 0; $b--) {
                                    if ($params[$b]->id === T_WHITESPACE) continue;
                                    if ($params[$b]->id === T_STRING || $params[$b]->id === T_NAME_QUALIFIED) {
                                        $typeHint = '';
                                        for ($t = $b; $t >= 0; $t--) {
                                            if ($params[$t]->id === T_WHITESPACE && !empty($typeHint)) break;
                                            if ($params[$t]->id === T_STRING || $params[$t]->id === T_NAME_QUALIFIED || $params[$t]->text === '\\') {
                                                $typeHint = $params[$t]->text . $typeHint;
                                            } elseif ($params[$t]->text === ',' || $params[$t]->text === '(') break;
                                            elseif ($params[$t]->id !== T_WHITESPACE) break;
                                        }
                                        break;
                                    }
                                    if ($params[$b]->text === ',' || $params[$b]->text === '(') break;
                                }

                                if ($typeHint && !in_array($typeHint, ['int', 'string', 'bool', 'float', 'array', 'callable', 'iterable', 'mixed', 'null', 'void'])) {
                                    $deps[] = [
                                        'class' => $typeHint,
                                        'variable' => $params[$p]->text,
                                        'line' => $params[$p]->line,
                                        'evidence' => 'constructor_type_hint',
                                    ];
                                }
                            }
                        }
                        break;
                    }
                }
                break;
            }
        }

        return $deps;
    }

    private function extractMiddleware(string $contents): array
    {
        $middleware = [];
        if (preg_match_all('/\$this->middleware\([\'"]([^\'"]+)[\'"](?:\s*,\s*\[([^\]]*)\])?\s*\)/', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $middleware[] = [
                    'name' => $match[1],
                    'options' => !empty($match[2]) ? $match[2] : null,
                    'line' => $this->findLine($contents, $match[0]),
                ];
            }
        }
        return $middleware;
    }

    private function extractPolicyChecks(string $contents): array
    {
        $policies = [];

        if (preg_match_all('/\$this->authorize\([\'"]([^\'"]+)[\'"]\s*,/', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $policies[] = [
                    'type' => 'authorize',
                    'ability' => $match[1],
                    'line' => $this->findLine($contents, $match[0]),
                ];
            }
        }

        if (preg_match_all('/(?:\\\\?Gate)::(authorize|allows|denies|check|any|none)\([\'"]([^\'"]+)[\'"]/', $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $policies[] = [
                    'type' => 'gate',
                    'method' => $match[1],
                    'ability' => $match[2],
                    'line' => $this->findLine($contents, $match[0]),
                ];
            }
        }

        return $policies;
    }

    private function extractValidation(string $contents): array
    {
        $validations = [];

        if (str_contains($contents, '$this->validate(')) {
            $validations[] = [
                'type' => 'inline',
                'class' => '(inline $this->validate())',
            ];
        }

        if (preg_match_all('/(\w+Request)\s+\$/', $contents, $matches)) {
            foreach ($matches[1] as $req) {
                $validations[] = [
                    'type' => 'form_request',
                    'class' => $req,
                ];
            }
        }

        return $validations;
    }

    private function aggregateDependencies(array $methodAnalyses): array
    {
        $models = [];
        $services = [];
        $formRequests = [];
        $resources = [];
        $events = [];
        $jobs = [];
        $notifications = [];
        $views = [];
        $redirects = [];
        $transactions = [];

        foreach ($methodAnalyses as $method) {
            foreach ($method['models_used'] ?? [] as $m) {
                $key = $m['class'];
                if (!isset($models[$key])) {
                    $models[$key] = ['class' => $m['class'], 'methods' => [], 'lines' => []];
                }
                foreach ($m['methods'] ?? [] as $methodName) {
                    if (!in_array($methodName, $models[$key]['methods'])) {
                        $models[$key]['methods'][] = $methodName;
                    }
                }
                $models[$key]['lines'][] = $m['line'];
            }

            foreach ($method['services_called'] ?? [] as $s) {
                $key = $s['class'];
                if (!isset($services[$key])) {
                    $services[$key] = ['class' => $s['class'], 'lines' => []];
                }
                $services[$key]['lines'][] = $s['line'];
            }

            foreach ($method['validation_requests'] ?? [] as $r) {
                if ($r['class'] === '(inline)' || $r['class'] === null) continue;
                $key = $r['class'];
                if (!isset($formRequests[$key])) {
                    $formRequests[$key] = ['class' => $r['class'], 'lines' => []];
                }
                $formRequests[$key]['lines'][] = $r['line'];
            }

            foreach ($method['resources_returned'] ?? [] as $r) {
                $key = $r['class'];
                if (!isset($resources[$key])) {
                    $resources[$key] = ['class' => $r['class'], 'lines' => []];
                }
                $resources[$key]['lines'][] = $r['line'];
            }

            foreach ($method['events_dispatched'] ?? [] as $e) {
                $key = $e['class'];
                if (!isset($events[$key])) {
                    $events[$key] = ['class' => $e['class'], 'method' => $e['method'] ?? '', 'lines' => []];
                }
                $events[$key]['lines'][] = $e['line'];
            }

            foreach ($method['jobs_dispatched'] ?? [] as $j) {
                $key = $j['class'];
                if (!isset($jobs[$key])) {
                    $jobs[$key] = ['class' => $j['class'], 'method' => $j['method'] ?? '', 'lines' => []];
                }
                $jobs[$key]['lines'][] = $j['line'];
            }

            foreach ($method['notifications_sent'] ?? [] as $n) {
                $key = $n['class'];
                if (!isset($notifications[$key])) {
                    $notifications[$key] = ['class' => $n['class'], 'method' => $n['method'] ?? '', 'lines' => []];
                }
                $notifications[$key]['lines'][] = $n['line'];
            }

            foreach ($method['views_returned'] ?? [] as $v) {
                $views[] = $v;
            }

            foreach ($method['redirects'] ?? [] as $r) {
                $redirects[] = $r;
            }

            foreach ($method['database_transactions'] ?? [] as $t) {
                $transactions[] = $t;
            }
        }

        return [
            'models_used' => array_values($models),
            'services_used' => array_values($services),
            'form_requests_used' => array_values($formRequests),
            'resources_used' => array_values($resources),
            'events_dispatched' => array_values($events),
            'jobs_dispatched' => array_values($jobs),
            'notifications_sent' => array_values($notifications),
            'views_returned' => $views,
            'redirects' => $redirects,
            'database_transactions' => $transactions,
        ];
    }

    private function methodHasRelevantContent(array $methodAnalysis): bool
    {
        return !empty($methodAnalysis['calls'])
            || !empty($methodAnalysis['models_used'])
            || !empty($methodAnalysis['events_dispatched'])
            || !empty($methodAnalysis['jobs_dispatched'])
            || !empty($methodAnalysis['notifications_sent'])
            || !empty($methodAnalysis['validation_requests'])
            || !empty($methodAnalysis['views_returned'])
            || !empty($methodAnalysis['resources_returned'])
            || !empty($methodAnalysis['redirects'])
            || !empty($methodAnalysis['database_transactions']);
    }

    private function getPhpFiles(string $path): array
    {
        $result = [];
        $basePath = rtrim(realpath($path), '/');
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $pathname = $file->getPathname();
                    $relativePath = str_replace($basePath . '/', '', $pathname);
                    $result[] = [
                        'pathname' => $pathname,
                        'relative_path' => $relativePath,
                        'filename' => $file->getFilename(),
                    ];
                }
            }
        } catch (\Throwable) {
            return [];
        }
        return $result;
    }

    private function extractClassName(string $contents): ?string
    {
        if (preg_match('/^class\s+(\w+)/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractNamespace(string $contents): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function detectGroup(string $relativePath): string
    {
        $dir = dirname($relativePath);
        return ($dir === '.') ? 'root' : $dir;
    }

    private function isCrudController(array $methods): bool
    {
        $crudPatterns = ['index', 'create', 'store', 'show', 'edit', 'update', 'destroy'];
        $found = 0;
        foreach ($crudPatterns as $pattern) {
            if (in_array($pattern, $methods)) {
                $found++;
            }
        }
        return $found >= 3;
    }

    private function isResourceController(string $name): bool
    {
        $lower = strtolower($name);
        return str_ends_with($lower, 'controller') && !str_contains($lower, 'auth');
    }

    private function findLine(string $contents, string $substring): int
    {
        $pos = strpos($contents, $substring);
        if ($pos === false) return 0;
        return substr_count(substr($contents, 0, $pos), "\n") + 1;
    }

    private function tokenize(string $contents): array
    {
        try {
            return \PhpToken::tokenize($contents);
        } catch (\Throwable) {
            return [];
        }
    }
}