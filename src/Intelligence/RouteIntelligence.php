<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates route intelligence grouped by module, with middleware, controller, and permission analysis.
 */
class RouteIntelligence
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        $routes = $data['routes']['items'] ?? [];
        $grouped = [];

        // Group routes by module
        foreach ($routes as $route) {
            $module = $route['module'] ?? 'unknown';

            if (!isset($grouped[$module])) {
                $grouped[$module] = [
                    'total' => 0,
                    'routes' => [],
                    'middleware' => [],
                    'controllers' => [],
                    'methods_summary' => [],
                ];
            }

            $grouped[$module]['total']++;
            $grouped[$module]['routes'][] = [
                'uri' => $route['uri'] ?? '',
                'methods' => $route['methods'] ?? [],
                'name' => $route['name'] ?? null,
                'action' => $route['action'] ?? '',
            ];

            // Collect middleware
            $middleware = $route['middleware'] ?? [];
            foreach ($middleware as $m) {
                if (!in_array($m, $grouped[$module]['middleware'])) {
                    $grouped[$module]['middleware'][] = $m;
                }
            }

            // Collect controllers
            $action = $route['action'] ?? '';
            if (str_contains($action, '@')) {
                $parts = explode('@', $action);
                $ctrlParts = explode('\\', $parts[0]);
                $ctrlName = end($ctrlParts);
                if ($ctrlName && !in_array($ctrlName, $grouped[$module]['controllers'])) {
                    $grouped[$module]['controllers'][] = $ctrlName;
                }
            }

            // Collect HTTP methods
            foreach ($route['methods'] ?? [] as $method) {
                if ($method !== 'HEAD') {
                    $grouped[$module]['methods_summary'][] = $method;
                }
            }
        }

        return [
            'route_intelligence' => [
                'groups' => $grouped,
                'total_routes' => count($routes),
                'group_count' => count($grouped),
            ],
        ];
    }
}