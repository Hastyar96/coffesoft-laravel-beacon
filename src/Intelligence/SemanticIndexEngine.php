<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Semantic AI Index — every important class gets semantic metadata:
 * Purpose, Responsibilities, Related Features, Dependencies, Related Models,
 * Related Controllers, Related Routes, Business Rules, Possible Side Effects, Keywords, Tags.
 */
class SemanticIndexEngine
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $entries = [];

        foreach ($data['models']['items'] ?? [] as $m) {
            $entries[] = $this->buildModelEntry($m, $data);
        }
        foreach ($data['controllers']['items'] ?? [] as $c) {
            $entries[] = $this->buildControllerEntry($c, $data);
        }
        foreach ($data['services']['items'] ?? [] as $s) {
            $entries[] = $this->buildServiceEntry($s, $data);
        }
        foreach ($data['repositories']['items'] ?? [] as $r) {
            $entries[] = $this->buildRepositoryEntry($r, $data);
        }
        foreach ($data['events']['items'] ?? [] as $e) {
            $entries[] = $this->buildEventEntry($e, $data);
        }
        foreach ($data['jobs']['items'] ?? [] as $j) {
            $entries[] = $this->buildJobEntry($j, $data);
        }
        foreach ($data['notifications']['items'] ?? [] as $n) {
            $entries[] = $this->buildNotificationEntry($n, $data);
        }
        foreach ($data['policies']['items'] ?? [] as $p) {
            $entries[] = $this->buildPolicyEntry($p, $data);
        }
        foreach ($data['form_requests']['items'] ?? [] as $r) {
            $entries[] = $this->buildRequestEntry($r, $data);
        }

        return [
            'semantic_index' => [
                'count' => count($entries),
                'entries' => $entries,
                'confidence' => 80,
            ],
        ];
    }

    private function buildModelEntry(array $m, array $data): array
    {
        $fillable = $m['fillable'] ?? [];
        $traits = $m['traits'] ?? [];
        $relations = $m['relations'] ?? [];
        $scopes = $m['scopes'] ?? [];

        // Find related controllers
        $relatedControllers = [];
        foreach ($data['controllers']['items'] ?? [] as $c) {
            if (preg_replace('/Controller$/', '', $c['name']) === $m['name']) {
                $relatedControllers[] = $c['name'];
            }
        }

        // Find related services
        $relatedServices = [];
        foreach ($data['services']['items'] ?? [] as $s) {
            foreach ($s['referenced_models'] ?? [] as $ref) {
                if (str_contains($ref, "\\{$m['name']}")) {
                    $relatedServices[] = $s['name'];
                    break;
                }
            }
        }

        // Find related routes
        $relatedRoutes = [];
        foreach ($relatedControllers as $ctrl) {
            foreach ($data['routes']['items'] ?? [] as $r) {
                if (str_contains($r['action'] ?? '', "\\{$ctrl}@") || str_contains($r['action'] ?? '', "{$ctrl}@")) {
                    $relatedRoutes[] = $r['uri'];
                }
            }
        }

        // Business rules from fillable/validation
        $businessRules = [];
        if (!empty($fillable)) $businessRules[] = "Attributes: " . implode(', ', $fillable);
        if (in_array('SoftDeletes', $traits)) $businessRules[] = "Records are soft-deleted";
        foreach ($scopes as $scope) $businessRules[] = "Query scope: {$scope}";

        $tags = ['model', 'eloquent'];
        if (in_array('SoftDeletes', $traits)) $tags[] = 'soft-deletes';
        foreach ($relations as $rel) {
            $tags[] = $rel['type'] ?? 'unknown';
        }

        $relRelatedModels = array_unique(array_map(fn($r) => $r['target'] ?? 'unknown', $relations));

        $keywords = array_merge(
            [$m['name']],
            $fillable,
            $traits,
            array_map(fn($s) => "scope:{$s}", $scopes),
        );

        return [
            'id' => 'model:' . $m['name'],
            'type' => 'model',
            'name' => $m['name'],
            'purpose' => "Represents the {$m['name']} entity in the database",
            'responsibilities' => ['Data persistence', 'Relationships', empty($scopes) ? null : 'Query scopes: ' . implode(', ', $scopes)],
            'related_features' => $relatedControllers,
            'dependencies' => array_merge($traits ?: []),
            'related_models' => $relRelatedModels,
            'related_controllers' => $relatedControllers,
            'related_services' => $relatedServices,
            'related_routes' => $relatedRoutes,
            'business_rules' => $businessRules,
            'possible_side_effects' => 'Editing this model affects ' . (count($relatedControllers) + count($relatedServices)) . ' related classes',
            'keywords' => array_values(array_unique(array_filter($keywords))),
            'tags' => array_values(array_unique($tags)),
            'confidence' => 85,
        ];
    }

    private function buildControllerEntry(array $c, array $data): array
    {
        $methods = $c['methods'] ?? [];
        $middleware = $c['middleware'] ?? [];
        $modelName = preg_replace('/Controller$/', '', $c['name']);

        // Related routes
        $routes = array_filter($data['routes']['items'] ?? [], fn($r) =>
            str_contains($r['action'] ?? '', "\\{$c['name']}@") || str_contains($r['action'] ?? '', "{$c['name']}@")
        );

        // Related services
        $services = [];
        foreach ($data['services']['items'] ?? [] as $s) {
            if (str_contains($s['name'], $modelName)) $services[] = $s['name'];
        }

        $tags = ['controller'];
        if ($c['is_crud'] ?? false) $tags[] = 'crud';
        if ($c['group'] ?? false) $tags[] = $c['group'];

        return [
            'id' => 'controller:' . $c['name'],
            'type' => 'controller',
            'name' => $c['name'],
            'purpose' => ($c['is_crud'] ?? false) ? "Manage {$modelName} entities with full CRUD operations" : "Handle HTTP requests in {$c['group']} group",
            'responsibilities' => $methods,
            'related_features' => [$modelName],
            'dependencies' => $services,
            'related_models' => [$modelName],
            'related_controllers' => [],
            'related_services' => $services,
            'related_routes' => array_map(fn($r) => $r['uri'], $routes),
            'business_rules' => $middleware,
            'possible_side_effects' => 'Routes handled by this controller will be affected',
            'keywords' => array_merge([$c['name']], $methods, $middleware),
            'tags' => $tags,
            'confidence' => 85,
        ];
    }

    private function buildServiceEntry(array $s, array $data): array
    {
        $methods = $s['methods'] ?? [];
        $models = array_map(fn($m) => substr(strrchr($m, '\\') ?: $m, 1), $s['referenced_models'] ?? []);
        $repos = array_map(fn($r) => substr(strrchr($r, '\\') ?: $r, 1), $s['referenced_repositories'] ?? []);

        return [
            'id' => 'service:' . $s['name'],
            'type' => 'service',
            'name' => $s['name'],
            'purpose' => 'Encapsulates business logic for the domain',
            'responsibilities' => $s['responsibilities'] ?? $methods,
            'related_features' => [preg_replace('/Service$/', '', $s['name'])],
            'dependencies' => array_merge($models, $repos),
            'related_models' => $models,
            'related_controllers' => [],
            'related_services' => [],
            'related_routes' => [],
            'business_rules' => [],
            'possible_side_effects' => 'Editing this service affects ' . (count($models) + count($repos)) . ' related classes',
            'keywords' => array_merge([$s['name']], $methods, $models),
            'tags' => ['service', $s['type'] ?? 'service'],
            'confidence' => 80,
        ];
    }

    private function buildRepositoryEntry(array $r, array $data): array
    {
        $models = array_map(fn($m) => substr(strrchr($m, '\\') ?: $m, 1), $r['referenced_models'] ?? []);
        return [
            'id' => 'repository:' . $r['name'],
            'type' => 'repository',
            'name' => $r['name'],
            'purpose' => 'Data access layer for database operations',
            'responsibilities' => $r['methods'] ?? [],
            'related_features' => $models,
            'dependencies' => $models,
            'related_models' => $models,
            'related_controllers' => [],
            'related_services' => [],
            'related_routes' => [],
            'business_rules' => [],
            'possible_side_effects' => "Editing this repository affects database queries for " . implode(', ', $models),
            'keywords' => array_merge([$r['name']], $r['methods'] ?? [], $models),
            'tags' => ['repository', 'data_access'],
            'confidence' => 80,
        ];
    }

    private function buildEventEntry(array $e, array $data): array
    {
        return [
            'id' => 'event:' . $e['name'],
            'type' => 'event',
            'name' => $e['name'],
            'purpose' => 'Signals that something happened in the application',
            'responsibilities' => ['State change notification'],
            'related_features' => [],
            'dependencies' => [],
            'related_models' => [],
            'related_controllers' => [],
            'related_services' => [],
            'related_routes' => [],
            'business_rules' => [],
            'possible_side_effects' => 'Listeners will react to this event',
            'keywords' => [$e['name']],
            'tags' => ['event', $e['should_broadcast'] ? 'broadcast' : null],
            'confidence' => 80,
        ];
    }

    private function buildJobEntry(array $j, array $data): array
    {
        return [
            'id' => 'job:' . $j['name'],
            'type' => 'job',
            'name' => $j['name'],
            'purpose' => ($j['queued'] ? 'Async' : 'Sync') . ' background task processing',
            'responsibilities' => ['Background processing'],
            'related_features' => [],
            'dependencies' => [],
            'related_models' => [],
            'related_controllers' => [],
            'related_services' => [],
            'related_routes' => [],
            'business_rules' => [],
            'possible_side_effects' => 'Dispatches events, processes data in background',
            'keywords' => [$j['name']],
            'tags' => ['job', $j['queued'] ? 'queued' : 'sync'],
            'confidence' => 80,
        ];
    }

    private function buildNotificationEntry(array $n, array $data): array
    {
        return [
            'id' => 'notification:' . $n['name'],
            'type' => 'notification',
            'name' => $n['name'],
            'purpose' => 'Sends notifications through configured channels',
            'responsibilities' => ['Notification delivery'],
            'related_features' => [],
            'dependencies' => [],
            'related_models' => [],
            'related_controllers' => [],
            'related_services' => [],
            'related_routes' => [],
            'business_rules' => ['Channels: ' . implode(', ', $n['channels'] ?? [])],
            'possible_side_effects' => 'Sends messages via configured channels',
            'keywords' => array_merge([$n['name']], $n['channels'] ?? []),
            'tags' => array_merge(['notification'], $n['channels'] ?? []),
            'confidence' => 80,
        ];
    }

    private function buildPolicyEntry(array $p, array $data): array
    {
        return [
            'id' => 'policy:' . $p['name'],
            'type' => 'policy',
            'name' => $p['name'],
            'purpose' => "Authorization rules for {$p['model']} model",
            'responsibilities' => $p['abilities'] ?? [],
            'related_features' => [$p['model']],
            'dependencies' => [$p['model']],
            'related_models' => [$p['model']],
            'related_controllers' => [],
            'related_services' => [],
            'related_routes' => [],
            'business_rules' => ['Abilities: ' . implode(', ', $p['abilities'] ?? [])],
            'possible_side_effects' => 'Controls authorization for all related controllers',
            'keywords' => array_merge([$p['name']], $p['abilities'] ?? [], [$p['model']]),
            'tags' => ['policy', 'authorization'],
            'confidence' => 85,
        ];
    }

    private function buildRequestEntry(array $r, array $data): array
    {
        $rules = $r['rules'] ?? [];
        $fields = array_map(fn($rl) => $rl['field'], array_slice($rules, 0, 10));

        return [
            'id' => 'request:' . $r['name'],
            'type' => 'form_request',
            'name' => $r['name'],
            'purpose' => 'Validates incoming HTTP request data',
            'responsibilities' => ['Validation', $r['authorize'] ? 'Authorization check' : null],
            'related_features' => [],
            'dependencies' => [],
            'related_models' => [],
            'related_controllers' => [],
            'related_services' => [],
            'related_routes' => [],
            'business_rules' => $fields,
            'possible_side_effects' => 'Rejects invalid requests before they reach controllers',
            'keywords' => array_merge([$r['name']], $fields),
            'tags' => ['form_request', 'validation'],
            'confidence' => 85,
        ];
    }
}