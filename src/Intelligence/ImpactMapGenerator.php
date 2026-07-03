<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates a change impact analysis map.
 * Editing any class reveals all other classes that could be affected.
 */
class ImpactMapGenerator
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $impacts = [];

        // For each model, find everything that references it
        foreach ($data['models']['items'] ?? [] as $model) {
            $impacts[] = $this->buildModelImpact($model['name'], $data);
        }

        // For each service, find everything that uses it
        foreach ($data['services']['items'] ?? [] as $svc) {
            $impacts[] = $this->buildServiceImpact($svc['name'], $data);
        }

        // For each controller
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $impacts[] = $this->buildControllerImpact($ctrl['name'], $data);
        }

        return [
            'impact_map' => [
                'count' => count($impacts),
                'items' => $impacts,
                'confidence' => 75,
            ],
        ];
    }

    private function buildModelImpact(string $modelName, array $data): array
    {
        $affected = [];

        // Controllers that work with this model
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $ctrlModel = preg_replace('/Controller$/', '', $ctrl['name']);
            if ($ctrlModel === $modelName) {
                $affected[] = ['type' => 'controller', 'name' => $ctrl['name'], 'path' => $ctrl['path']];
            }
        }

        // Services that reference this model
        foreach ($data['services']['items'] ?? [] as $svc) {
            foreach ($svc['referenced_models'] ?? [] as $ref) {
                if (str_contains($ref, "\\{$modelName}")) {
                    $affected[] = ['type' => 'service', 'name' => $svc['name'], 'path' => $svc['path']];
                    break;
                }
            }
        }

        // Repositories that reference this model
        foreach ($data['repositories']['items'] ?? [] as $repo) {
            foreach ($repo['referenced_models'] ?? [] as $ref) {
                if (str_contains($ref, "\\{$modelName}")) {
                    $affected[] = ['type' => 'repository', 'name' => $repo['name'], 'path' => $repo['path']];
                    break;
                }
            }
        }

        // Policy for this model
        foreach ($data['policies']['items'] ?? [] as $policy) {
            if ($policy['model'] === $modelName) {
                $affected[] = ['type' => 'policy', 'name' => $policy['name'], 'path' => $policy['path']];
            }
        }

        // Form requests for this model
        foreach ($data['form_requests']['items'] ?? [] as $req) {
            if (str_contains($req['name'], $modelName)) {
                $affected[] = ['type' => 'form_request', 'name' => $req['name'], 'path' => $req['path']];
            }
        }

        // Views for this model
        $viewName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1.$2', $modelName));
        foreach ($data['blade']['views'] ?? [] as $view) {
            if (str_contains($view['name'] ?? '', $viewName)) {
                $affected[] = ['type' => 'blade_view', 'name' => $view['name']];
            }
        }

        // API resources for this model
        foreach ($data['api']['resources'] ?? [] as $res) {
            if (str_contains($res['name'], $modelName)) {
                $affected[] = ['type' => 'api_resource', 'name' => $res['name'], 'path' => $res['path']];
            }
        }

        // Routes for this model's controller
        $ctrlName = $modelName . 'Controller';
        foreach ($data['routes']['items'] ?? [] as $route) {
            if (str_contains($route['action'] ?? '', "\\{$ctrlName}@") || str_contains($route['action'] ?? '', "{$ctrlName}@")) {
                $affected[] = ['type' => 'route', 'uri' => $route['uri'], 'methods' => $route['methods']];
            }
        }

        return [
            'target' => ['type' => 'model', 'name' => $modelName],
            'affects' => $affected,
            'impact_count' => count($affected),
        ];
    }

    private function buildServiceImpact(string $serviceName, array $data): array
    {
        $affected = [];

        // Controllers that inject this service
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $ctrlUses = $this->getFileUses(app_path('Http/Controllers/' . ($ctrl['path'] ?? '')));
            foreach ($ctrlUses as $use) {
                if (str_contains($use, "\\{$serviceName}")) {
                    $affected[] = ['type' => 'controller', 'name' => $ctrl['name'], 'path' => $ctrl['path']];
                    break;
                }
            }
        }

        // Other services that inject this service
        foreach ($data['services']['items'] ?? [] as $svc) {
            if ($svc['name'] === $serviceName) continue;
            $svcUses = $this->getFileUses(app_path('Services/' . ($svc['path'] ?? '')));
            foreach ($svcUses as $use) {
                if (str_contains($use, "\\{$serviceName}")) {
                    $affected[] = ['type' => 'service', 'name' => $svc['name'], 'path' => $svc['path']];
                    break;
                }
            }
        }

        // Models referenced by this service
        foreach ($data['services']['items'] ?? [] as $svc) {
            if ($svc['name'] !== $serviceName) continue;
            foreach ($svc['referenced_models'] ?? [] as $ref) {
                $parts = explode('\\', $ref);
                $affected[] = ['type' => 'model', 'name' => end($parts)];
            }
        }

        return [
            'target' => ['type' => 'service', 'name' => $serviceName],
            'affects' => $affected,
            'impact_count' => count($affected),
        ];
    }

    private function buildControllerImpact(string $ctrlName, array $data): array
    {
        $affected = [];

        // Routes for this controller
        foreach ($data['routes']['items'] ?? [] as $route) {
            if (str_contains($route['action'] ?? '', "\\{$ctrlName}@") || str_contains($route['action'] ?? '', "{$ctrlName}@")) {
                $affected[] = ['type' => 'route', 'uri' => $route['uri'], 'methods' => $route['methods']];
            }
        }

        // Views are typically returned by controllers
        $viewName = null;
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            if ($ctrl['name'] !== $ctrlName) continue;
            $modelName = preg_replace('/Controller$/', '', $ctrlName);
            $viewName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1.$2', $modelName));
        }

        if ($viewName) {
            foreach ($data['blade']['views'] ?? [] as $view) {
                if (str_contains($view['name'] ?? '', $viewName)) {
                    $affected[] = ['type' => 'blade_view', 'name' => $view['name']];
                }
            }
        }

        return [
            'target' => ['type' => 'controller', 'name' => $ctrlName],
            'affects' => $affected,
            'impact_count' => count($affected),
        ];
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
}