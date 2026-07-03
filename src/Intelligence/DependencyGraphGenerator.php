<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates a fine-grained dependency graph mapping:
 * Controller → Service, Service → Repository, Repository → Model,
 * Controller → Request, Controller → Policy, Job → Event, Listener → Notification, Middleware → Routes
 */
class DependencyGraphGenerator
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $deps = [
            'controller_to_service' => $this->mapControllerToService($data),
            'service_to_repository' => $this->mapServiceToRepository($data),
            'repository_to_model' => $this->mapRepositoryToModel($data),
            'controller_to_request' => $this->mapControllerToRequest($data),
            'controller_to_policy' => $this->mapControllerToPolicy($data),
            'controller_to_model' => $this->mapControllerToModel($data),
            'service_to_model' => $this->mapServiceToModel($data),
            'job_to_event' => $this->mapJobToEvent($data),
            'listener_to_notification' => $this->mapListenerToNotification($data),
            'middleware_to_routes' => $this->mapMiddlewareToRoutes($data),
            'service_to_job' => $this->mapServiceToJob($data),
            'controller_to_middleware' => $this->mapControllerToMiddleware($data),
        ];

        return [
            'dependency_graph' => [
                'nodes' => $this->getAllNodes($deps),
                'edges' => $this->getAllEdges($deps),
                'edge_count' => $this->countEdges($deps),
                'confidence' => 80,
            ],
        ];
    }

    private function mapControllerToService(array $data): array
    {
        $edges = [];
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $ctrlUses = $this->getFileUses(app_path('Http/Controllers/' . ($ctrl['path'] ?? '')));
            foreach ($data['services']['items'] ?? [] as $svc) {
                $svcName = $svc['name'];
                foreach ($ctrlUses as $use) {
                    if (str_contains($use, "\\{$svcName}")) {
                        $edges[] = [
                            'from' => "controller:{$ctrl['name']}",
                            'to' => "service:{$svcName}",
                            'label' => 'injects',
                        ];
                    }
                }
            }
        }
        return $edges;
    }

    private function mapServiceToRepository(array $data): array
    {
        $edges = [];
        foreach ($data['services']['items'] ?? [] as $svc) {
            $svcUses = $this->getFileUses(app_path('Services/' . ($svc['path'] ?? '')));
            foreach ($data['repositories']['items'] ?? [] as $repo) {
                $repoName = $repo['name'];
                foreach ($svcUses as $use) {
                    if (str_contains($use, "\\{$repoName}")) {
                        $edges[] = [
                            'from' => "service:{$svc['name']}",
                            'to' => "repository:{$repoName}",
                            'label' => 'injects',
                        ];
                    }
                }
            }
        }
        return $edges;
    }

    private function mapRepositoryToModel(array $data): array
    {
        $edges = [];
        foreach ($data['repositories']['items'] ?? [] as $repo) {
            foreach ($repo['referenced_models'] ?? [] as $ref) {
                $parts = explode('\\', $ref);
                $modelName = end($parts);
                $edges[] = [
                    'from' => "repository:{$repo['name']}",
                    'to' => "model:{$modelName}",
                    'label' => 'queries',
                ];
            }
        }
        return $edges;
    }

    private function mapControllerToRequest(array $data): array
    {
        $edges = [];
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $ctrlUses = $this->getFileUses(app_path('Http/Controllers/' . ($ctrl['path'] ?? '')));
            foreach ($data['form_requests']['items'] ?? [] as $req) {
                $reqName = $req['name'];
                foreach ($ctrlUses as $use) {
                    if (str_contains($use, "\\{$reqName}") || $reqName === $use) {
                        $edges[] = [
                            'from' => "controller:{$ctrl['name']}",
                            'to' => "request:{$reqName}",
                            'label' => 'validates_with',
                        ];
                    }
                }
            }
        }
        return $edges;
    }

    private function mapControllerToPolicy(array $data): array
    {
        $edges = [];
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $contents = $this->getFileContents(app_path('Http/Controllers/' . ($ctrl['path'] ?? '')));
            if (!$contents) continue;
            foreach ($data['policies']['items'] ?? [] as $policy) {
                $policyName = $policy['name'];
                if (str_contains($contents, $policyName) || str_contains($contents, "\\{$policyName}")) {
                    $edges[] = [
                        'from' => "controller:{$ctrl['name']}",
                        'to' => "policy:{$policyName}",
                        'label' => 'authorizes_via',
                    ];
                }
            }
        }
        return $edges;
    }

    private function mapControllerToModel(array $data): array
    {
        $edges = [];
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $modelName = preg_replace('/Controller$/', '', $ctrl['name']);
            if (!$modelName) continue;
            foreach ($data['models']['items'] ?? [] as $model) {
                if ($model['name'] === $modelName) {
                    $edges[] = [
                        'from' => "controller:{$ctrl['name']}",
                        'to' => "model:{$modelName}",
                        'label' => 'manages',
                    ];
                }
            }
        }
        return $edges;
    }

    private function mapServiceToModel(array $data): array
    {
        $edges = [];
        foreach ($data['services']['items'] ?? [] as $svc) {
            foreach ($svc['referenced_models'] ?? [] as $ref) {
                $parts = explode('\\', $ref);
                $modelName = end($parts);
                $edges[] = [
                    'from' => "service:{$svc['name']}",
                    'to' => "model:{$modelName}",
                    'label' => 'uses',
                ];
            }
        }
        return $edges;
    }

    private function mapJobToEvent(array $data): array
    {
        $edges = [];
        foreach ($data['events']['dispatchers'] ?? [] as $dispatcher) {
            foreach ($dispatcher['dispatches'] ?? [] as $eventName) {
                // Check if the dispatcher is a job
                $edges[] = [
                    'from' => "class:{$dispatcher['class']}",
                    'to' => "event:{$eventName}",
                    'label' => 'dispatches',
                ];
            }
        }
        return $edges;
    }

    private function mapListenerToNotification(array $data): array
    {
        $edges = [];
        foreach ($data['events']['listeners'] ?? [] as $listener) {
            $listenerUses = $this->getFileUses(app_path('Listeners/' . ($listener['path'] ?? '')));
            foreach ($data['notifications']['items'] ?? [] as $notif) {
                foreach ($listenerUses as $use) {
                    if (str_contains($use, "\\{$notif['name']}")) {
                        $edges[] = [
                            'from' => "listener:{$listener['name']}",
                            'to' => "notification:{$notif['name']}",
                            'label' => 'sends',
                        ];
                    }
                }
            }
        }
        return $edges;
    }

    private function mapMiddlewareToRoutes(array $data): array
    {
        $edges = [];
        foreach ($data['routes']['items'] ?? [] as $route) {
            foreach ($route['middleware'] ?? [] as $mw) {
                $edges[] = [
                    'from' => "middleware:{$mw}",
                    'to' => "route:{$route['uri']}",
                    'label' => 'protects',
                ];
            }
        }
        return $edges;
    }

    private function mapServiceToJob(array $data): array
    {
        $edges = [];
        foreach ($data['jobs']['dispatchers'] ?? [] as $dispatcher) {
            foreach ($dispatcher['dispatches'] ?? [] as $jobName) {
                $edges[] = [
                    'from' => "service:{$dispatcher['class']}",
                    'to' => "job:{$jobName}",
                    'label' => 'dispatches',
                ];
            }
        }
        return $edges;
    }

    private function mapControllerToMiddleware(array $data): array
    {
        $edges = [];
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            foreach ($ctrl['middleware'] ?? [] as $mw) {
                $edges[] = [
                    'from' => "middleware:{$mw}",
                    'to' => "controller:{$ctrl['name']}",
                    'label' => 'applied_to',
                ];
            }
        }
        return $edges;
    }

    private function getFileUses(?string $path): array
    {
        if (!$path || !file_exists($path)) return [];
        $contents = file_get_contents($path);
        $uses = [];
        if (preg_match_all('/^use\s+([^;]+);/m', $contents, $matches)) {
            $uses = $matches[1];
        }
        return $uses;
    }

    private function getFileContents(?string $path): ?string
    {
        if (!$path || !file_exists($path)) return null;
        return file_get_contents($path);
    }

    private function getAllNodes(array $deps): array
    {
        $nodes = [];
        foreach ($deps as $edges) {
            foreach ($edges as $edge) {
                $fromParts = explode(':', $edge['from'], 2);
                $toParts = explode(':', $edge['to'], 2);
                $nodes[$edge['from']] = ['id' => $edge['from'], 'type' => $fromParts[0] ?? 'unknown', 'name' => $fromParts[1] ?? $edge['from']];
                $nodes[$edge['to']] = ['id' => $edge['to'], 'type' => $toParts[0] ?? 'unknown', 'name' => $toParts[1] ?? $edge['to']];
            }
        }
        return array_values($nodes);
    }

    private function getAllEdges(array $deps): array
    {
        $edges = [];
        foreach ($deps as $key => $edgeList) {
            foreach ($edgeList as $edge) {
                $edges[] = $edge;
            }
        }
        return $edges;
    }

    private function countEdges(array $deps): int
    {
        $count = 0;
        foreach ($deps as $edgeList) {
            $count += count($edgeList);
        }
        return $count;
    }
}