<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Detects complete business workflows by analyzing routes, controllers,
 * services, models, events, and notifications.
 *
 * Each workflow traces: User → Route → Controller → Validation → Service → Repository → Model → DB → Event → Notification
 * Only detects real, verifiable workflows.
 */
class WorkflowDetector
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function detect(array $data): array
    {
        $workflows = [];

        // Analyze CRUD controllers -> each is a potential workflow
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            if (!($ctrl['is_crud'] ?? false)) continue;

            $modelName = $this->inferModelFromController($ctrl['name']);
            if (!$modelName) continue;

            $workflow = $this->buildCrudWorkflow($ctrl, $modelName, $data);
            if ($workflow) {
                $workflows[] = $workflow;
            }
        }

        // Analyze routes for action-based workflows
        $routeWorkflows = $this->buildRouteWorkflows($data);
        $workflows = array_merge($workflows, $routeWorkflows);

        // Analyze jobs -> each job can be a workflow step
        $jobWorkflows = $this->buildJobWorkflows($data);
        $workflows = array_merge($workflows, $jobWorkflows);

        // Deduplicate by name
        $unique = [];
        foreach ($workflows as $wf) {
            $key = $wf['name'];
            if (!isset($unique[$key])) {
                $unique[$key] = $wf;
            }
        }

        return [
            'workflows' => [
                'count' => count($unique),
                'items' => array_values($unique),
                'confidence' => 75,
            ],
        ];
    }

    private function buildCrudWorkflow(array $ctrl, string $modelName, array $data): ?array
    {
        $steps = [];
        $service = $this->findServiceForModel($modelName, $data);
        $repository = $this->findRepositoryForModel($modelName, $data);
        $request = $this->findRequestForController($ctrl['name'], $data);
        $policy = $this->findPolicyForModel($modelName, $data);
        $routes = $this->findRoutesForController($ctrl['name'], $data);
        $events = $this->findEventsForModel($modelName, $data);
        $notifications = $this->findNotificationsForModel($modelName, $data);

        foreach ($ctrl['methods'] ?? [] as $method) {
            $steps[] = [
                'step' => $method,
                'type' => 'controller_method',
                'class' => $ctrl['name'],
                'confidence' => 90,
            ];
        }

        if ($service) {
            $steps[] = [
                'step' => 'business_logic',
                'type' => 'service',
                'class' => $service['name'],
                'confidence' => 85,
            ];
        }

        if ($repository) {
            $steps[] = [
                'step' => 'data_access',
                'type' => 'repository',
                'class' => $repository['name'],
                'confidence' => 85,
            ];
        }

        $steps[] = [
            'step' => 'persistence',
            'type' => 'model',
            'class' => $modelName,
            'confidence' => 95,
        ];

        $steps[] = [
            'step' => 'database',
            'type' => 'database',
            'table' => $this->modelToTable($modelName),
            'confidence' => 90,
        ];

        if ($request) {
            $steps[] = [
                'step' => 'validation',
                'type' => 'form_request',
                'class' => $request['name'],
                'confidence' => 90,
            ];
        }

        if ($policy) {
            $steps[] = [
                'step' => 'authorization',
                'type' => 'policy',
                'class' => $policy['name'],
                'abilities' => $policy['abilities'] ?? [],
                'confidence' => 85,
            ];
        }

        if (!empty($events)) {
            foreach ($events as $event) {
                $steps[] = [
                    'step' => 'event_dispatched',
                    'type' => 'event',
                    'class' => $event,
                    'confidence' => 80,
                ];
            }
        }

        if (!empty($notifications)) {
            foreach ($notifications as $notif) {
                $steps[] = [
                    'step' => 'notification_sent',
                    'type' => 'notification',
                    'class' => $notif,
                    'confidence' => 75,
                ];
            }
        }

        return [
            'name' => "{$modelName} CRUD",
            'type' => 'crud',
            'entry_point' => $routes[0]['uri'] ?? "/{$this->modelToRoute($modelName)}",
            'controller' => $ctrl['name'],
            'model' => $modelName,
            'routes' => array_map(fn($r) => $r['uri'], $routes),
            'steps' => $steps,
            'confidence' => 80,
        ];
    }

    private function buildRouteWorkflows(array $data): array
    {
        $workflows = [];
        $routeGroups = $data['route_intelligence']['groups'] ?? [];

        foreach ($routeGroups as $module => $group) {
            if ($group['total'] < 2) continue;

            $routes = $group['routes'] ?? [];
            $controllers = $group['controllers'] ?? [];

            // Group related routes (same controller)
            $groupedByController = [];
            foreach ($routes as $route) {
                $action = $route['action'] ?? '';
                if (str_contains($action, '@')) {
                    $parts = explode('@', $action);
                    $ctrlName = substr(strrchr($parts[0], '\\') ?: $parts[0], 1);
                    $method = $parts[1] ?? '';
                    $groupedByController[$ctrlName][] = [
                        'method' => $method,
                        'uri' => $route['uri'],
                        'methods' => $route['methods'] ?? [],
                        'name' => $route['name'] ?? '',
                    ];
                }
            }

            foreach ($groupedByController as $ctrlName => $ctrlRoutes) {
                if (count($ctrlRoutes) < 2) continue;

                $modelName = $this->inferModelFromController($ctrlName);
                $steps = [];
                foreach ($ctrlRoutes as $route) {
                    $steps[] = [
                        'step' => $route['method'] . ': ' . $route['uri'],
                        'type' => 'route',
                        'methods' => $route['methods'],
                        'confidence' => 85,
                    ];
                }

                $workflows[] = [
                    'name' => ($modelName ?? $ctrlName) . ' Workflow',
                    'type' => 'route_group',
                    'entry_point' => $ctrlRoutes[0]['uri'],
                    'controller' => $ctrlName,
                    'model' => $modelName,
                    'routes' => array_map(fn($r) => $r['uri'], $ctrlRoutes),
                    'steps' => $steps,
                    'confidence' => 75,
                ];
            }
        }

        return $workflows;
    }

    private function buildJobWorkflows(array $data): array
    {
        $workflows = [];

        foreach ($data['jobs']['items'] ?? [] as $job) {
            if (!$job['queued']) continue;

            $steps = [
                [
                    'step' => 'dispatched',
                    'type' => 'dispatch',
                    'confidence' => 90,
                ],
                [
                    'step' => 'queued',
                    'type' => 'queue',
                    'driver' => $data['queue']['default_driver'] ?? 'sync',
                    'confidence' => 80,
                ],
                [
                    'step' => 'handle',
                    'type' => 'job_execution',
                    'class' => $job['name'],
                    'confidence' => 95,
                ],
            ];

            // Find dispatchers
            $dispatchers = array_filter($data['jobs']['dispatchers'] ?? [], fn($d) => in_array($job['name'], $d['dispatches'] ?? []));
            if (!empty($dispatchers)) {
                foreach ($dispatchers as $d) {
                    $steps[] = [
                        'step' => 'dispatched_by',
                        'type' => 'dispatcher',
                        'class' => $d['class'],
                        'confidence' => 85,
                    ];
                }
            }

            $workflows[] = [
                'name' => "{$job['name']} Job",
                'type' => 'background_job',
                'entry_point' => 'Dispatched from ' . ($dispatchers[0]['class'] ?? 'unknown'),
                'job' => $job['name'],
                'queued' => true,
                'steps' => $steps,
                'confidence' => 80,
            ];
        }

        return $workflows;
    }

    private function findServiceForModel(string $modelName, array $data): ?array
    {
        foreach ($data['services']['items'] ?? [] as $svc) {
            foreach ($svc['referenced_models'] ?? [] as $ref) {
                if (str_contains($ref, "\\{$modelName}")) {
                    return $svc;
                }
            }
            if (str_contains($svc['name'], $modelName)) {
                return $svc;
            }
        }
        return null;
    }

    private function findRepositoryForModel(string $modelName, array $data): ?array
    {
        foreach ($data['repositories']['items'] ?? [] as $repo) {
            foreach ($repo['referenced_models'] ?? [] as $ref) {
                if (str_contains($ref, "\\{$modelName}")) {
                    return $repo;
                }
            }
            if (str_contains($repo['name'], $modelName)) {
                return $repo;
            }
        }
        return null;
    }

    private function findRequestForController(string $ctrlName, array $data): ?array
    {
        $modelName = $this->inferModelFromController($ctrlName);
        if (!$modelName) return null;

        foreach ($data['form_requests']['items'] ?? [] as $req) {
            if (str_contains($req['name'], $modelName)) {
                return $req;
            }
        }
        return null;
    }

    private function findPolicyForModel(string $modelName, array $data): ?array
    {
        foreach ($data['policies']['items'] ?? [] as $policy) {
            if ($policy['model'] === $modelName) {
                return $policy;
            }
        }
        return null;
    }

    private function findRoutesForController(string $ctrlName, array $data): array
    {
        $routes = [];
        foreach ($data['routes']['items'] ?? [] as $route) {
            $action = $route['action'] ?? '';
            if (str_contains($action, "\\{$ctrlName}@") || str_contains($action, "{$ctrlName}@")) {
                $routes[] = $route;
            }
        }
        return $routes;
    }

    private function findEventsForModel(string $modelName, array $data): array
    {
        $events = [];
        foreach ($data['events']['items'] ?? [] as $event) {
            if (str_contains($event['name'], $modelName)) {
                $events[] = $event['name'];
            }
        }
        return $events;
    }

    private function findNotificationsForModel(string $modelName, array $data): array
    {
        $notifs = [];
        foreach ($data['notifications']['items'] ?? [] as $notif) {
            if (str_contains($notif['name'], $modelName)) {
                $notifs[] = $notif['name'];
            }
        }
        return $notifs;
    }

    private function inferModelFromController(string $name): ?string
    {
        return preg_replace('/Controller$/', '', $name) ?: null;
    }

    private function modelToTable(string $model): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $model)) . 's';
    }

    private function modelToRoute(string $model): string
    {
        return strtolower(preg_replace('/([a-z])([A-Z])/', '$1-$2', $model)) . 's';
    }
}