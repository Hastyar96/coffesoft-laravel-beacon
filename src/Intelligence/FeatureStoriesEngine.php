<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Feature Stories — generates technical documentation for each detected feature.
 * Includes: Purpose, User Flow, Main Controllers, Models, Services, Database Tables,
 * Routes, Views, Permissions, Dependencies, Possible Risks.
 */
class FeatureStoriesEngine
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $stories = [];

        // Use the enhanced features from FeatureMapGenerator
        $features = $data['features']['items'] ?? [];

        foreach ($features as $feature) {
            $story = $this->buildStory($feature, $data);
            if ($story) {
                $stories[] = $story;
            }
        }

        return [
            'feature_stories' => [
                'count' => count($stories),
                'stories' => $stories,
                'confidence' => 75,
            ],
        ];
    }

    private function buildStory(array $feature, array $data): ?array
    {
        $name = $feature['name'] ?? null;
        if (!$name) return null;

        $controllers = $feature['controller']['name'] ?? null;
        if (!$controllers) {
            $controllersList = $feature['controllers'] ?? [];
        } else {
            $controllersList = [$controllers];
        }

        // User flow from controller methods
        $userFlow = $this->buildUserFlow($feature, $data);

        // Find models used
        $models = [];
        if ($feature['model']) {
            $models[] = $feature['model']['name'] ?? $feature['model'];
        }

        // Find database tables
        $tables = $feature['database_tables'] ?? [];

        // Permissions
        $permissions = $feature['permissions'] ?? [];

        // Risks
        $risks = $this->assessRisks($feature, $data);

        $lines = [];

        $lines[] = "# {$name}";
        $lines[] = '';
        $lines[] = ($feature['purpose'] ?? 'Feature management');
        $lines[] = '';

        $lines[] = '## User Flow';
        $lines[] = '';
        foreach ($userFlow as $step) {
            $lines[] = "- {$step}";
        }
        $lines[] = '';

        $lines[] = '## Main Controllers';
        $lines[] = '';
        foreach ($controllersList as $c) {
            $lines[] = "- `{$c}`";
        }
        $lines[] = '';

        $lines[] = '## Main Models';
        $lines[] = '';
        foreach ($models as $m) {
            $lines[] = "- `{$m}`";
            // Add fillable info
            $modelData = null;
            foreach ($data['models']['items'] ?? [] as $md) {
                if ($md['name'] === $m) {
                    $modelData = $md;
                    break;
                }
            }
            if ($modelData && !empty($modelData['fillable'])) {
                $lines[] = "  - Attributes: " . implode(', ', $modelData['fillable']);
            }
        }
        $lines[] = '';

        $services = [];
        if ($feature['service']) {
            $services[] = $feature['service'];
        }
        if (!empty($feature['services'])) {
            $services = array_merge($services, $feature['services']);
        }
        if (!empty($services)) {
            $lines[] = '## Main Services';
            $lines[] = '';
            foreach (array_unique($services) as $s) {
                $lines[] = "- `{$s}`";
            }
            $lines[] = '';
        }

        if (!empty($tables)) {
            $lines[] = '## Database Tables';
            $lines[] = '';
            foreach ($tables as $t) {
                $lines[] = "- `{$t}`";
            }
            $lines[] = '';
        }

        $routes = $feature['routes'] ?? [];
        if (!empty($routes)) {
            $lines[] = '## Routes';
            $lines[] = '';
            foreach ($routes as $route) {
                $uri = $route['uri'] ?? $route;
                $methods = '';
                if (is_array($route) && isset($route['methods'])) {
                    $methods = implode(',', array_diff($route['methods'], ['HEAD'])) . ' ';
                }
                $lines[] = "- `{$methods}{$uri}`";
            }
            $lines[] = '';
        }

        $views = $feature['views'] ?? [];
        if (!empty($views)) {
            $lines[] = '## Views';
            $lines[] = '';
            foreach ($views as $v) {
                $lines[] = "- `{$v}`";
            }
            $lines[] = '';
        }

        if (!empty($permissions)) {
            $lines[] = '## Permissions';
            $lines[] = '';
            foreach ($permissions as $p) {
                $lines[] = "- `{$p}`";
            }
            $lines[] = '';
        }

        if (!empty($risks)) {
            $lines[] = '## Possible Risks';
            $lines[] = '';
            foreach ($risks as $risk) {
                $lines[] = "- {$risk}";
            }
            $lines[] = '';
        }

        // Dependencies
        $deps = [];
        if (!empty($feature['jobs'])) $deps = array_merge($deps, $feature['jobs']);
        if (!empty($feature['events'])) $deps = array_merge($deps, $feature['events']);
        if (!empty($feature['notifications'])) $deps = array_merge($deps, $feature['notifications']);
        if (!empty($deps)) {
            $lines[] = '## Dependencies';
            $lines[] = '';
            foreach ($deps as $d) {
                $lines[] = "- `{$d}`";
            }
            $lines[] = '';
        }

        return [
            'name' => $name,
            'type' => $feature['type'] ?? 'feature',
            'content' => implode("\n", $lines),
            'confidence' => 75,
        ];
    }

    private function buildUserFlow(array $feature, array $data): array
    {
        $flow = [];

        // Starting with routes
        $routes = $feature['routes'] ?? [];
        $controllers = $feature['controllers'] ?? [];

        if (!empty($routes)) {
            $routeSample = $routes[0];
            $uri = is_array($routeSample) ? ($routeSample['uri'] ?? '') : $routeSample;
            $flow[] = "User accesses `{$uri}`";
        } elseif (!empty($controllers)) {
            $flow[] = "User invokes a controller action in `" . $controllers[0] . "`";
        }

        // Controller processes
        $ctrlList = [];
        if ($feature['controller']['name'] ?? null) {
            $ctrlList[] = $feature['controller'];
        }
        if (!empty($ctrlList)) {
            $methods = $ctrlList[0]['methods'] ?? [];
            if (in_array('validate', $methods)) $flow[] = "Request is validated";
            if (in_array('store', $methods) || in_array('create', $methods)) $flow[] = "Data is persisted to database";
            if (in_array('update', $methods)) $flow[] = "Existing data is updated";
            if (in_array('delete', $methods) || in_array('destroy', $methods)) $flow[] = "Data is removed";
        }

        // Model interaction
        if ($feature['model']) {
            $flow[] = "System interacts with the database via the model";
        }

        // Jobs
        if (!empty($feature['jobs'])) {
            $flow[] = "Background jobs are dispatched for async processing";
        }

        // Events
        if (!empty($feature['events'])) {
            $flow[] = "Events are dispatched to trigger side effects";
        }

        // Notifications
        if (!empty($feature['notifications'])) {
            $flow[] = "Notifications are sent to relevant users";
        }

        $flow[] = "Response is returned to the user";

        return $flow;
    }

    private function assessRisks(array $feature, array $data): array
    {
        $risks = [];

        // Check for missing policy
        if (!$feature['policy'] && ($feature['type'] ?? '') === 'crud') {
            $risks[] = 'No authorization policy detected — any user may access this feature';
        }

        // Check for missing validation
        if (empty($feature['requests']) && ($feature['type'] ?? '') === 'crud') {
            $risks[] = 'No form request validation detected — data may not be validated';
        }

        // Check for missing service
        if (!$feature['service'] && count($feature['controllers'] ?? []) > 0) {
            $risks[] = 'No service layer detected — business logic may be in controllers';
        }

        // Check for unguarded models
        if ($feature['model']) {
            $modelName = $feature['model']['name'] ?? $feature['model'];
            foreach ($data['models']['items'] ?? [] as $m) {
                if ($m['name'] === $modelName && empty($m['fillable']) && empty($m['guarded'])) {
                    $risks[] = "{$modelName} model has no fillable/guarded — mass assignment not protected";
                }
            }
        }

        return $risks;
    }
}