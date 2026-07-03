<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates a complete project relationship graph connecting models,
 * controllers, services, repositories, policies, views, API resources, and routes.
 */
class RelationshipGraph
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $nodes = [];
        $edges = [];

        // Add model nodes and relationships
        foreach ($data['models']['items'] ?? [] as $model) {
            $nodeId = 'model:' . $model['name'];
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'model',
                'name' => $model['name'],
                'namespace' => $model['namespace'] ?? '',
                'path' => $model['path'] ?? '',
            ];

            // Add relationship edges between models
            $relations = $model['relations'] ?? [];
            foreach ($relations as $type => $count) {
                if ($count > 0) {
                    // We can't determine the target model without deeper analysis
                    // but we note the relationship type exists
                }
            }
        }

        // Add controller nodes and connect to models
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $nodeId = 'controller:' . $ctrl['name'];
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'controller',
                'name' => $ctrl['name'],
                'namespace' => $ctrl['namespace'] ?? '',
                'path' => $ctrl['path'] ?? '',
                'group' => $ctrl['group'] ?? '',
                'methods' => $ctrl['methods'] ?? [],
                'is_crud' => $ctrl['is_crud'] ?? false,
            ];

            // Infer model relationship from controller name
            $modelName = $this->inferModelFromController($ctrl['name']);
            if ($modelName && isset($nodes['model:' . $modelName])) {
                $edges[] = [
                    'from' => $nodeId,
                    'to' => 'model:' . $modelName,
                    'type' => 'manages',
                    'label' => 'uses',
                ];
            }
        }

        // Add service nodes and connect to referenced models
        foreach ($data['services']['items'] ?? [] as $service) {
            $nodeId = 'service:' . $service['name'];
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'service',
                'name' => $service['name'],
                'namespace' => $service['namespace'] ?? '',
                'path' => $service['path'] ?? '',
                'methods' => $service['methods'] ?? [],
            ];

            // Connect to referenced models
            $refModels = $service['referenced_models'] ?? [];
            foreach ($refModels as $ref) {
                $shortName = (new \ReflectionClass($ref))->getShortName();
                if (isset($nodes['model:' . $shortName])) {
                    $edges[] = [
                        'from' => $nodeId,
                        'to' => 'model:' . $shortName,
                        'type' => 'references',
                        'label' => 'uses model',
                    ];
                }
            }
        }

        // Add repository nodes
        foreach ($data['repositories']['items'] ?? [] as $repo) {
            $nodeId = 'repository:' . $repo['name'];
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'repository',
                'name' => $repo['name'],
                'namespace' => $repo['namespace'] ?? '',
                'path' => $repo['path'] ?? '',
                'methods' => $repo['methods'] ?? [],
            ];

            // Connect to referenced models
            $refModels = $repo['referenced_models'] ?? [];
            foreach ($refModels as $ref) {
                $parts = explode('\\', $ref);
                $shortName = end($parts);
                if (isset($nodes['model:' . $shortName])) {
                    $edges[] = [
                        'from' => $nodeId,
                        'to' => 'model:' . $shortName,
                        'type' => 'stores',
                        'label' => 'stores',
                    ];
                }
            }
        }

        // Add form request nodes and connect to controllers
        foreach ($data['form_requests']['items'] ?? [] as $request) {
            $nodeId = 'request:' . $request['name'];
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'form_request',
                'name' => $request['name'],
                'namespace' => $request['namespace'] ?? '',
                'path' => $request['path'] ?? '',
                'rules_count' => count($request['rules'] ?? []),
            ];

            // Infer controller usage from request name pattern
            $ctrlName = $this->inferControllerFromRequest($request['name']);
            if ($ctrlName && isset($nodes['controller:' . $ctrlName])) {
                $edges[] = [
                    'from' => $nodeId,
                    'to' => 'controller:' . $ctrlName,
                    'type' => 'validated_by',
                    'label' => 'validates requests for',
                ];
            }
        }

        // Add policy nodes and connect to models
        foreach ($data['policies']['items'] ?? [] as $policy) {
            $nodeId = 'policy:' . $policy['name'];
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'policy',
                'name' => $policy['name'],
                'namespace' => $policy['namespace'] ?? '',
                'path' => $policy['path'] ?? '',
                'abilities' => $policy['abilities'] ?? [],
                'model' => $policy['model'] ?? '',
            ];

            $modelName = $policy['model'] ?? '';
            if ($modelName && isset($nodes['model:' . $modelName])) {
                $edges[] = [
                    'from' => $nodeId,
                    'to' => 'model:' . $modelName,
                    'type' => 'authorizes',
                    'label' => 'authorizes',
                ];
            }
        }

        // Add blade view nodes
        foreach ($data['blade']['views'] ?? [] as $view) {
            $nodeId = 'view:' . $view['name'];
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'blade_view',
                'name' => $view['name'],
                'path' => $view['path'],
                'extends' => $view['extends'],
                'components' => $view['components'] ?? [],
            ];
        }

        // Add API resource nodes
        foreach ($data['api']['resources'] ?? [] as $resource) {
            $nodeId = 'api_resource:' . $resource['name'];
            $nodes[$nodeId] = [
                'id' => $nodeId,
                'type' => 'api_resource',
                'name' => $resource['name'],
                'namespace' => $resource['namespace'] ?? '',
                'path' => $resource['path'] ?? '',
            ];
        }

        // Add route grouping
        $routeGroups = [];
        foreach ($data['routes']['items'] ?? [] as $route) {
            $uri = $route['uri'] ?? '';
            $module = $route['module'] ?? '';

            if (!isset($routeGroups[$module])) {
                $routeGroups[$module] = ['count' => 0, 'routes' => []];
            }
            $routeGroups[$module]['count']++;
            $routeGroups[$module]['routes'][] = [
                'uri' => $uri,
                'methods' => $route['methods'] ?? [],
                'action' => $route['action'] ?? '',
                'name' => $route['name'] ?? '',
            ];
        }

        return [
            'project_graph' => [
                'nodes' => array_values($nodes),
                'edges' => $edges,
                'route_groups' => $routeGroups,
            ],
        ];
    }

    private function inferModelFromController(string $controllerName): ?string
    {
        // Common patterns: ProductController -> Product
        $name = preg_replace('/Controller$/', '', $controllerName);
        if (empty($name)) return null;

        return $name;
    }

    private function inferControllerFromRequest(string $requestName): ?string
    {
        // Common patterns: StoreProductRequest, UpdateProductRequest -> ProductController
        $name = preg_replace('/^(Store|Update|Create|Delete|Destroy|View|Show|List)\s*/', '', $requestName);
        $name = preg_replace('/Request$/', '', $name);

        if (empty($name)) return null;

        return $name . 'Controller';
    }
}