<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Route Health Engine
 *
 * Analyzes all routes for quality issues:
 * - unnamed routes (cannot use route() helper)
 * - duplicate routes (same URI + method combination)
 * - orphan routes (controller class doesn't exist)
 * - missing REST patterns
 */
class RouteHealthEngine
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        $issues = [];
        $routeCount = count($data['routes']['items'] ?? []);
        $allUris = [];

        // Collect all route URIs for duplicate detection
        foreach ($data['routes']['items'] ?? [] as $r) {
            $uri = $r['uri'] ?? '';
            foreach (array_diff($r['methods'] ?? [], ['HEAD']) as $method) {
                $key = "{$method}:{$uri}";
                $allUris[$key][] = $r;
            }
        }

        // 1. Unnamed routes
        $unnamed = array_filter($data['routes']['items'] ?? [], fn($r) => empty($r['name']));
        if (!empty($unnamed)) {
            $issues[] = [
                'type' => 'unnamed_routes',
                'severity' => 'info',
                'count' => count($unnamed),
                'message' => count($unnamed) . ' of ' . $routeCount . ' routes have no name',
                'routes' => array_map(fn($r) => $r['uri'], array_slice($unnamed, 0, 10)),
                'suggested_fix' => "Add ->name('name') to unnamed routes for route() helper and URL generation",
                'confidence' => 95,
            ];
        }

        // 2. Duplicate routes (same method + URI)
        $duplicateCount = 0;
        $duplicateDetails = [];
        foreach ($allUris as $key => $routes) {
            if (count($routes) > 1) {
                $duplicateCount++;
                $duplicateDetails[] = [
                    'key' => $key,
                    'count' => count($routes),
                    'actions' => array_map(fn($r) => $r['action'], $routes),
                ];
            }
        }
        if ($duplicateCount > 0) {
            $issues[] = [
                'type' => 'duplicate_routes',
                'severity' => 'high',
                'count' => $duplicateCount,
                'message' => $duplicateCount . ' route(s) have duplicate method + URI combinations — only the first will be matched',
                'duplicates' => array_slice($duplicateDetails, 0, 10),
                'suggested_fix' => 'Remove or rename duplicate route definitions',
                'confidence' => 90,
            ];
        }

        // 3. Orphan routes (controller doesn't exist)
        $controllerNames = array_map(fn($c) => $c['name'], $data['controllers']['items'] ?? []);
        $orphanCount = 0;
        $orphanDetails = [];
        foreach ($data['routes']['items'] ?? [] as $r) {
            $action = $r['action'] ?? '';
            if (!str_contains($action, '@')) continue;
            $parts = explode('@', $action);
            $ctrlName = substr(strrchr($parts[0], '\\') ?: $parts[0], 1);
            if (!in_array($ctrlName, $controllerNames) && !str_contains($action, 'Closure')) {
                $orphanCount++;
                $orphanDetails[] = ['uri' => $r['uri'], 'controller' => $ctrlName, 'method' => $parts[1] ?? ''];
            }
        }
        if ($orphanCount > 0) {
            $issues[] = [
                'type' => 'orphan_routes',
                'severity' => 'high',
                'count' => $orphanCount,
                'message' => $orphanCount . ' route(s) reference non-existent controllers',
                'orphans' => array_slice($orphanDetails, 0, 10),
                'suggested_fix' => 'Create the missing controllers or remove the routes',
                'confidence' => 85,
            ];
        }

        // 4. REST convention check
        $getRoutes = 0;
        $postRoutes = 0;
        $putRoutes = 0;
        $patchRoutes = 0;
        $deleteRoutes = 0;
        foreach ($data['routes']['items'] ?? [] as $r) {
            foreach ($r['methods'] ?? [] as $m) {
                if ($m === 'HEAD') continue;
                match ($m) {
                    'GET' => $getRoutes++,
                    'POST' => $postRoutes++,
                    'PUT' => $putRoutes++,
                    'PATCH' => $patchRoutes++,
                    'DELETE' => $deleteRoutes++,
                    default => null,
                };
            }
        }
        $totalNonGet = $postRoutes + $putRoutes + $patchRoutes + $deleteRoutes;
        if ($routeCount > 0 && $totalNonGet === 0) {
            $issues[] = [
                'type' => 'rest_convention',
                'severity' => 'info',
                'message' => 'All routes use GET — no POST/PUT/PATCH/DELETE routes detected for mutations',
                'method_breakdown' => compact('getRoutes', 'postRoutes', 'putRoutes', 'patchRoutes', 'deleteRoutes'),
                'suggested_fix' => 'Add POST/PUT/DELETE routes for resource mutations following REST conventions',
                'confidence' => 70,
            ];
        }

        // 5. Routes without corresponding views (web routes)
        $viewNames = array_map(fn($v) => $v['name'], $data['blade']['views'] ?? []);
        $webRouteNames = [];
        foreach ($data['routes']['items'] ?? [] as $r) {
            if ($r['name']) $webRouteNames[] = $r['name'];
        }
        $routeViewGap = count($webRouteNames) > 0 && count($viewNames) > 0
            && count($webRouteNames) > count($viewNames) * 2;
        if ($routeViewGap) {
            $issues[] = [
                'type' => 'route_view_mismatch',
                'severity' => 'info',
                'message' => count($webRouteNames) . ' named routes but only ' . count($viewNames) . ' views — some routes may lack views',
                'suggested_fix' => 'Create missing views for named routes or verify route-view mapping',
                'confidence' => 40,
            ];
        }

        return [
            'route_health' => [
                'issues_count' => count($issues),
                'issues' => $issues,
                'total_routes' => $routeCount,
                'method_breakdown' => compact('getRoutes', 'postRoutes', 'putRoutes', 'patchRoutes', 'deleteRoutes'),
                'confidence' => 85,
            ],
        ];
    }
}