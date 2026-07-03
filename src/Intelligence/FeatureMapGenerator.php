<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates a feature map by grouping related controllers, models, views, services, policies, and routes.
 */
class FeatureMapGenerator
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $features = [];

        // Each CRUD controller-grouped workflow becomes a feature
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            if (!($ctrl['is_crud'] ?? false)) continue;

            $modelName = preg_replace('/Controller$/', '', $ctrl['name']);
            if (!$modelName) continue;

            $feature = $this->buildFeature($ctrl, $modelName, $data);
            if ($feature) {
                $features[] = $feature;
            }
        }

        // Add non-CRUD controllers as features too
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            if ($ctrl['is_crud'] ?? false) continue;
            $modelName = preg_replace('/Controller$/', '', $ctrl['name']);
            $feature = $this->buildFeature($ctrl, $modelName, $data);
            if ($feature && $feature['slice_count'] > 1) {
                $features[] = $feature;
            }
        }

        return [
            'features' => [
                'count' => count($features),
                'items' => $features,
                'confidence' => 75,
            ],
        ];
    }

    private function buildFeature(array $ctrl, ?string $modelName, array $data): ?array
    {
        $name = $modelName ?? $ctrl['name'];
        $ctrlName = $ctrl['name'];

        // Gather routes for this controller
        $routes = array_filter($data['routes']['items'] ?? [], fn($r) =>
            str_contains($r['action'] ?? '', "\\{$ctrlName}@") ||
            str_contains($r['action'] ?? '', "{$ctrlName}@")
        );

        // Find associated model
        $model = null;
        if ($modelName) {
            foreach ($data['models']['items'] ?? [] as $m) {
                if ($m['name'] === $modelName) {
                    $model = $m;
                    break;
                }
            }
        }

        // Find associated views
        $views = [];
        $viewName = $this->nameToView($name);
        foreach ($data['blade']['views'] ?? [] as $view) {
            if (str_contains($view['name'] ?? '', $viewName)) {
                $views[] = $view['name'];
            }
        }

        // Find associated service
        $service = null;
        foreach ($data['services']['items'] ?? [] as $svc) {
            if (str_contains($svc['name'], $name)) {
                $service = $svc['name'];
                break;
            }
        }

        // Find associated policy
        $policy = null;
        foreach ($data['policies']['items'] ?? [] as $p) {
            if ($p['model'] === $name) {
                $policy = $p['name'];
                break;
            }
        }

        // Find permissions from routes middleware
        $permissions = [];
        foreach ($routes as $route) {
            foreach ($route['middleware'] ?? [] as $mw) {
                if (str_contains($mw, 'can:') || str_contains($mw, 'permission:')) {
                    $permissions[] = $mw;
                }
            }
        }

        // Find associated jobs
        $jobs = [];
        foreach ($data['jobs']['items'] ?? [] as $job) {
            if (str_contains($job['name'], $name)) {
                $jobs[] = $job['name'];
            }
        }

        // Find associated events
        $events = [];
        foreach ($data['events']['items'] ?? [] as $event) {
            if (str_contains($event['name'], $name)) {
                $events[] = $event['name'];
            }
        }

        // Find associated notifications
        $notifications = [];
        foreach ($data['notifications']['items'] ?? [] as $notif) {
            if (str_contains($notif['name'], $name)) {
                $notifications[] = $notif['name'];
            }
        }

        // Database tables
        $tables = [];
        $tableName = $this->nameToTable($name);
        foreach ($data['database']['tables'] ?? [] as $table) {
            if ($table['name'] === $tableName || str_contains($table['name'], $tableName)) {
                $tables[] = $table['name'];
            }
        }

        // Slice count
        $sliceCount = 0;
        $sliceCount += count($routes) > 0 ? 1 : 0;
        $sliceCount += $model ? 1 : 0;
        $sliceCount += count($views) > 0 ? 1 : 0;
        $sliceCount += $service ? 1 : 0;
        $sliceCount += $policy ? 1 : 0;
        $sliceCount += count($jobs) > 0 ? 1 : 0;
        $sliceCount += count($events) > 0 ? 1 : 0;
        $sliceCount += count($tables) > 0 ? 1 : 0;

        if ($sliceCount < 1) return null;

        return [
            'name' => $name,
            'purpose' => $this->inferFeaturePurpose($name, $ctrl, $model),
            'routes' => array_map(fn($r) => ['uri' => $r['uri'], 'methods' => $r['methods']], $routes),
            'controller' => $ctrlName,
            'model' => $modelName,
            'views' => $views,
            'service' => $service,
            'policy' => $policy,
            'jobs' => $jobs,
            'events' => $events,
            'notifications' => $notifications,
            'database_tables' => $tables,
            'permissions' => array_values(array_unique($permissions)),
            'slice_count' => $sliceCount,
            'confidence' => 70,
        ];
    }

    private function nameToView(string $name): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1.$2', $name)) ?? strtolower($name);
    }

    private function nameToTable(string $name): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name)) . 's';
    }

    private function inferFeaturePurpose(string $name, array $ctrl, ?array $model): string
    {
        if ($ctrl['is_crud'] ?? false) {
            return "Manage {$name} entities with full CRUD operations";
        }

        $methods = $ctrl['methods'] ?? [];
        if (in_array('__invoke', $methods)) {
            return "Single-action handler for {$name}";
        }
        if (count($methods) <= 3) {
            return "Simple feature handler for {$name}";
        }

        return "Feature management for {$name}";
    }
}