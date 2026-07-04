<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Feature Map Generator
 *
 * Detects features using multiple signals:
 * Controllers, Route groups, Views, Models, Services, Policies,
 * Requests, Livewire components, Blade folders, Resource classes.
 *
 * Generates rich feature descriptions with slice analysis.
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
        $seenNames = [];

        // Strategy 1: Group by route prefix
        $routeFeatures = $this->buildRouteGroupFeatures($data);
        foreach ($routeFeatures as $f) {
            $key = $f['name'];
            if (!isset($seenNames[$key])) {
                $features[] = $f;
                $seenNames[$key] = true;
            }
        }

        // Strategy 2: CRUD controllers as features (richer than route groups)
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            if (!($ctrl['is_crud'] ?? false)) continue;
            $modelName = preg_replace('/Controller$/', '', $ctrl['name']);
            if (!$modelName) continue;

            $feature = $this->buildMultiSignalFeature($ctrl, $modelName, $data);
            if (!$feature) continue;

            $key = $feature['name'];
            if (!isset($seenNames[$key])) {
                $features[] = $feature;
                $seenNames[$key] = true;
            } else {
                // Merge into existing feature
                foreach ($features as &$existing) {
                    if ($existing['name'] === $key) {
                        $existing = $this->mergeFeatures($existing, $feature);
                        break;
                    }
                }
            }
        }

        // Strategy 3: All non-CRUD controllers
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            if ($ctrl['is_crud'] ?? false) continue;
            $modelName = preg_replace('/Controller$/', '', $ctrl['name']);
            $feature = $this->buildMultiSignalFeature($ctrl, $modelName, $data);
            if (!$feature || $feature['slice_count'] < 2) continue;

            $key = $feature['name'];
            if (!isset($seenNames[$key])) {
                $features[] = $feature;
                $seenNames[$key] = true;
            }
        }

        // Strategy 4: Livewire component features
        foreach ($data['livewire']['components'] ?? [] as $lw) {
            $feature = $this->buildLivewireFeature($lw, $data);
            if (!$feature) continue;

            $key = $feature['name'];
            if (!isset($seenNames[$key])) {
                $features[] = $feature;
                $seenNames[$key] = true;
            }
        }

        // Sort by slice count (richest features first)
        usort($features, fn($a, $b) => ($b['slice_count'] ?? 0) <=> ($a['slice_count'] ?? 0));

        return [
            'features' => [
                'count' => count($features),
                'items' => $features,
                'confidence' => 80,
            ],
        ];
    }

    private function buildRouteGroupFeatures(array $data): array
    {
        $features = [];
        $routeGroups = $data['route_intelligence']['groups'] ?? [];

        foreach ($routeGroups as $module => $group) {
            if ($group['total'] < 2) continue;

            $features[] = [
                'name' => ucfirst($module),
                'purpose' => "{$module} module with {$group['total']} routes",
                'routes' => array_map(fn($r) => ['uri' => $r['uri'], 'methods' => $r['methods'] ?? []], array_slice($group['routes'] ?? [], 0, 20)),
                'controllers' => $group['controllers'] ?? [],
                'middleware' => $group['middleware'] ?? [],
                'model' => null,
                'views' => [],
                'service' => null,
                'policy' => null,
                'requests' => [],
                'jobs' => [],
                'events' => [],
                'notifications' => [],
                'database_tables' => [],
                'permissions' => [],
                'livewire_components' => [],
                'api_resources' => [],
                'slice_count' => 1 + count($group['controllers'] ?? []),
                'confidence' => 70,
            ];
        }

        return $features;
    }

    private function buildMultiSignalFeature(array $ctrl, ?string $modelName, array $data): ?array
    {
        $name = $modelName ?? $ctrl['name'];
        $ctrlName = $ctrl['name'];
        $group = $ctrl['group'] ?? '';

        // Routes for this controller
        $routes = array_filter($data['routes']['items'] ?? [], fn($r) =>
            str_contains($r['action'] ?? '', "\\{$ctrlName}@") ||
            str_contains($r['action'] ?? '', "{$ctrlName}@")
        );

        // Associated model
        $model = null;
        if ($modelName) {
            foreach ($data['models']['items'] ?? [] as $m) {
                if ($m['name'] === $modelName) {
                    $model = $m;
                    break;
                }
            }
            // Also try to find by matching path/directory pattern
            if (!$model) {
                foreach ($data['models']['items'] ?? [] as $m) {
                    $mPath = $m['path'] ?? '';
                    if (str_contains($mPath, '/' . $modelName . '.php') || $mPath === $modelName . '.php') {
                        $model = $m;
                        break;
                    }
                }
            }
        }

        // Views via multiple signals
        $views = [];
        $signals = [
            strtolower($name),
            strtolower($ctrlName),
            strtolower(preg_replace('/controller$/', '', $ctrlName)),
            $group ? strtolower($group) . '.' . strtolower($name) : null,
        ];
        $signals = array_filter($signals);

        foreach ($data['blade']['views'] ?? [] as $view) {
            $viewName = $view['name'] ?? '';
            foreach ($signals as $signal) {
                if ($signal && str_contains($viewName, $signal)) {
                    $views[] = $viewName;
                    break;
                }
            }
        }

        // Service association
        $service = null;
        $serviceNames = [];
        foreach ($data['services']['items'] ?? [] as $svc) {
            $svcName = $svc['name'];
            $svcUses = $svc['referenced_models'] ?? [];
            $nameMatch = str_contains($svcName, $name);
            $modelRefMatch = false;

            if ($model) {
                foreach ($svcUses as $ref) {
                    if (str_contains($ref, "\\{$model['name']}")) {
                        $modelRefMatch = true;
                        break;
                    }
                }
            }

            if ($nameMatch || $modelRefMatch) {
                $serviceNames[] = $svcName;
                if (!$service) $service = $svcName;
            }
        }

        // Policy
        $policy = null;
        foreach ($data['policies']['items'] ?? [] as $p) {
            if ($p['model'] === $name || ($model && $p['model'] === $model['name'])) {
                $policy = $p['name'];
                break;
            }
        }

        // Form Requests
        $requests = [];
        foreach ($data['form_requests']['items'] ?? [] as $req) {
            if (str_contains($req['name'], $name)) {
                $requests[] = $req['name'];
            }
        }

        // Permissions from middleware
        $permissions = [];
        foreach ($routes as $route) {
            foreach ($route['middleware'] ?? [] as $mw) {
                if (str_contains($mw, 'can:') || str_contains($mw, 'permission:') || str_contains($mw, 'role:')) {
                    $permissions[] = $mw;
                }
            }
        }
        // Also check controller middleware
        foreach ($ctrl['middleware'] ?? [] as $mw) {
            if (str_contains($mw, 'can:') || str_contains($mw, 'permission:') || str_contains($mw, 'role:')) {
                $permissions[] = $mw;
            }
        }

        // Jobs
        $jobs = [];
        foreach ($data['jobs']['items'] ?? [] as $job) {
            if (str_contains($job['name'], $name)) $jobs[] = $job['name'];
        }
        // Also check job dispatchers referencing this controller
        foreach ($data['jobs']['dispatchers'] ?? [] as $d) {
            if (str_contains($d['class'] ?? '', $ctrlName)) {
                foreach ($d['dispatches'] ?? [] as $j) {
                    if (!in_array($j, $jobs)) $jobs[] = $j;
                }
            }
        }

        // Events
        $events = [];
        foreach ($data['events']['items'] ?? [] as $event) {
            if (str_contains($event['name'], $name)) $events[] = $event['name'];
        }
        // Dispatchers from this controller
        foreach ($data['events']['dispatchers'] ?? [] as $d) {
            if (str_contains($d['class'] ?? '', $ctrlName)) {
                foreach ($d['dispatches'] ?? [] as $ev) {
                    if (!in_array($ev, $events)) $events[] = $ev;
                }
            }
        }

        // Notifications
        $notifications = [];
        foreach ($data['notifications']['items'] ?? [] as $notif) {
            if (str_contains($notif['name'], $name)) $notifications[] = $notif['name'];
        }

        // Database tables
        $tables = [];
        $tableName = $this->nameToTable($name);
        foreach ($data['database']['tables'] ?? [] as $table) {
            if ($table['name'] === $tableName) {
                $tables[] = $table['name'];
            } elseif ($model && $tableName !== $table['name']) {
                // Try model-to-table for the model name
                $modelTable = $this->nameToTable($model['name']);
                if ($table['name'] === $modelTable) {
                    $tables[] = $table['name'];
                }
            }
        }

        // Livewire components
        $livewireComponents = [];
        foreach ($data['livewire']['components'] ?? [] as $lw) {
            $lwName = $lw['name'] ?? '';
            if (str_contains($lwName, $name)) {
                $livewireComponents[] = $lwName;
            }
        }

        // API Resources
        $apiResources = [];
        foreach ($data['api']['resources'] ?? [] as $res) {
            if (str_contains($res['name'], $name)) {
                $apiResources[] = $res['name'];
            }
        }

        // Slice count
        $sliceCount = 0;
        $sliceCount += count($routes) > 0 ? 1 : 0;
        $sliceCount += $model ? 1 : 0;
        $sliceCount += count($views) > 0 ? 1 : 0;
        $sliceCount += $service ? 1 : 0;
        $sliceCount += $policy ? 1 : 0;
        $sliceCount += count($requests) > 0 ? 1 : 0;
        $sliceCount += count($jobs) > 0 ? 1 : 0;
        $sliceCount += count($events) > 0 ? 1 : 0;
        $sliceCount += count($tables) > 0 ? 1 : 0;
        $sliceCount += count($livewireComponents) > 0 ? 1 : 0;
        $sliceCount += count($apiResources) > 0 ? 1 : 0;

        if ($sliceCount < 1) return null;

        // Generate rich description
        $purpose = $this->generateRichDescription($name, $ctrl, $model, $group, $requests, $service, $views);
        $description = $this->generateFeatureSummary($purpose, $ctrl, $model, $routes);

        return [
            'name' => $name,
            'purpose' => $purpose,
            'description' => $description,
            'group' => $group,
            'type' => ($ctrl['is_crud'] ?? false) ? 'crud' : 'feature',
            'routes' => array_map(fn($r) => [
                'uri' => $r['uri'],
                'methods' => array_diff($r['methods'] ?? [], ['HEAD']),
                'name' => $r['name'] ?? null,
                'action' => $r['action'] ?? '',
            ], $routes),
            'route_count' => count($routes),
            'controller' => [
                'name' => $ctrlName,
                'path' => $ctrl['path'] ?? '',
                'methods' => $ctrl['methods'] ?? [],
                'is_crud' => $ctrl['is_crud'] ?? false,
                'middleware' => $ctrl['middleware'] ?? [],
            ],
            'model' => $model ? [
                'name' => $model['name'],
                'fillable' => $model['fillable'] ?? [],
                'casts' => $model['casts'] ?? [],
                'traits' => $model['traits'] ?? [],
                'relations' => $model['relations'] ?? [],
                'scopes' => $model['scopes'] ?? [],
            ] : null,
            'views' => $views,
            'view_count' => count($views),
            'service' => $service,
            'services' => $serviceNames,
            'policy' => $policy,
            'requests' => $requests,
            'jobs' => $jobs,
            'events' => $events,
            'notifications' => $notifications,
            'database_tables' => $tables,
            'permissions' => array_values(array_unique($permissions)),
            'livewire_components' => $livewireComponents,
            'api_resources' => $apiResources,
            'slice_count' => $sliceCount,
            'confidence' => min(70 + $sliceCount * 3, 90),
        ];
    }

    private function buildLivewireFeature(array $lw, array $data): ?array
    {
        $name = $lw['name'] ?? '';
        if (!$name) return null;

        $view = $lw['view'] ?? null;
        $properties = $lw['properties'] ?? [];
        $methods = $lw['methods'] ?? [];
        $emits = $lw['emits'] ?? [];
        $listens = $lw['listens'] ?? [];

        return [
            'name' => $name . ' (Livewire)',
            'purpose' => "Livewire component: {$name}" . ($view ? " (view: {$view})" : ''),
            'type' => 'livewire',
            'livewire_component' => $name,
            'view' => $view,
            'properties' => $properties,
            'methods' => $methods,
            'emits' => $emits,
            'listens' => $listens,
            'routes' => [],
            'controller' => null,
            'model' => null,
            'views' => $view ? [$view] : [],
            'service' => null,
            'policy' => null,
            'requests' => [],
            'slice_count' => 2,
            'confidence' => 60,
        ];
    }

    private function mergeFeatures(array $existing, array $new): array
    {
        $merged = $existing;

        // Merge arrays
        foreach (['routes', 'views', 'jobs', 'events', 'notifications', 'database_tables', 'permissions', 'requests', 'services', 'livewire_components', 'api_resources'] as $key) {
            $existingItems = $existing[$key] ?? [];
            $newItems = $new[$key] ?? [];
            $merged[$key] = array_values(array_unique(array_merge(
                is_array($existingItems) ? $existingItems : [$existingItems],
                is_array($newItems) ? $newItems : [$newItems]
            )));
        }

        // Merge single values (prefer non-null)
        foreach (['model', 'service', 'policy'] as $key) {
            if (empty($merged[$key]) && !empty($new[$key])) {
                $merged[$key] = $new[$key];
            }
        }

        // Recalculate slice count
        if (isset($new['slice_count'])) {
            $merged['slice_count'] = max($merged['slice_count'] ?? 0, $new['slice_count']);
        }

        // Combine descriptions
        if (!empty($new['description'])) {
            $merged['description'] = ($merged['description'] ?? '') . "\n" . $new['description'];
        }

        $merged['confidence'] = min(($existing['confidence'] ?? 70) + 5, 90);

        return $merged;
    }

    private function generateRichDescription(string $name, array $ctrl, ?array $model, ?string $group, array $requests, ?string $service, array $views): string
    {
        $parts = [];

        if ($ctrl['is_crud'] ?? false) {
            $parts[] = "Manage {$name} entities with full CRUD operations";
        } elseif (in_array('__invoke', $ctrl['methods'] ?? [])) {
            $parts[] = "Single-action handler for {$name}";
        } else {
            $parts[] = "Feature handler for {$name}";
        }

        $methods = $ctrl['methods'] ?? [];
        if (in_array('index', $methods)) $parts[] = "lists {$name}";
        if (in_array('create', $methods)) $parts[] = "shows creation form";
        if (in_array('store', $methods)) $parts[] = "validates and creates records";
        if (in_array('show', $methods)) $parts[] = "displays single {$name}";
        if (in_array('edit', $methods)) $parts[] = "shows edit form";
        if (in_array('update', $methods)) $parts[] = "validates and updates records";
        if (in_array('destroy', $methods)) $parts[] = "deletes records";

        if ($group && $group !== '.' && $group !== 'root') {
            $parts[] = "located in {$group} group";
        }

        if ($service) {
            $parts[] = "uses {$service} for business logic";
        }

        if (!empty($requests)) {
            $parts[] = "validated by " . implode(', ', $requests);
        }

        if (!empty($views)) {
            $parts[] = "renders " . count($views) . " views";
        }

        if ($model) {
            $fillable = $model['fillable'] ?? [];
            if (!empty($fillable)) {
                $parts[] = "manages attributes: " . implode(', ', array_slice($fillable, 0, 6)) . (count($fillable) > 6 ? '...' : '');
            }
            $rels = $model['relations'] ?? [];
            if (!empty($rels)) {
                $relDesc = array_map(fn($r) => ($r['type'] ?? '?') . '->' . ($r['target'] ?? '?'), $rels);
                $parts[] = "relationships: " . implode(', ', $relDesc);
            }
        }

        return implode('. ', $parts) . '.';
    }

    private function generateFeatureSummary(string $purpose, array $ctrl, ?array $model, array $routes): string
    {
        $summary = $purpose;
        $summary .= "\nController: {$ctrl['name']}";
        $summary .= "\nMethods: " . implode(', ', $ctrl['methods'] ?? []);
        if ($model) {
            $summary .= "\nModel: {$model['name']}";
        }
        if (!empty($routes)) {
            $summary .= "\nRoutes: " . count($routes);
        }
        return $summary;
    }

    private function nameToView(string $name): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1.$2', $name)) ?? strtolower($name);
    }

    private function nameToTable(string $name): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name)) . 's';
    }
}