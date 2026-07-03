<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates AI-friendly summaries for every important class in the project.
 *
 * Each summary includes: purpose, responsibilities, dependencies,
 * important methods, related models, security concerns, and business role.
 * Maximum 15 lines per summary. No code duplication.
 */
class AISummarizer
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $summaries = [];

        // Summarize models
        foreach ($data['models']['items'] ?? [] as $model) {
            $summaries[] = $this->summarizeModel($model);
        }

        // Summarize controllers
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $summaries[] = $this->summarizeController($ctrl, $data);
        }

        // Summarize services
        foreach ($data['services']['items'] ?? [] as $service) {
            $summaries[] = $this->summarizeService($service);
        }

        // Summarize repositories
        foreach ($data['repositories']['items'] ?? [] as $repo) {
            $summaries[] = $this->summarizeRepository($repo);
        }

        // Summarize form requests
        foreach ($data['form_requests']['items'] ?? [] as $request) {
            $summaries[] = $this->summarizeFormRequest($request);
        }

        // Summarize policies
        foreach ($data['policies']['items'] ?? [] as $policy) {
            $summaries[] = $this->summarizePolicy($policy);
        }

        // Summarize events
        foreach ($data['events']['items'] ?? [] as $event) {
            $summaries[] = $this->summarizeEvent($event);
        }

        // Summarize jobs
        foreach ($data['jobs']['items'] ?? [] as $job) {
            $summaries[] = $this->summarizeJob($job);
        }

        // Summarize notifications
        foreach ($data['notifications']['items'] ?? [] as $notification) {
            $summaries[] = $this->summarizeNotification($notification);
        }

        return [
            'ai_summaries' => [
                'count' => count($summaries),
                'items' => $summaries,
            ],
        ];
    }

    private function summarizeModel(array $model): array
    {
        $lines = [];
        $lines[] = "Model: {$model['name']} ({$model['namespace']})";
        $lines[] = "Purpose: Represents the '{$model['name']}' entity in the database.";
        $lines[] = "Database table: inferred from model name.";

        $fillable = $model['fillable'] ?? [];
        $casts = $model['casts'] ?? [];
        $relations = $model['relations'] ?? [];

        if (!empty($fillable)) {
            $lines[] = "Fillable attributes: " . implode(', ', $fillable);
        }
        if (!empty($casts)) {
            $lines[] = "Casts: " . implode(', ', array_map(fn($k, $v) => "$k => $v", array_keys($casts), $casts));
        }
        if (!empty($relations)) {
            foreach ($relations as $type => $count) {
                $lines[] = "Relationships ({$type}): {$count} defined";
            }
        }
        if (!empty($model['scopes'])) {
            $lines[] = "Query scopes: " . implode(', ', $model['scopes']);
        }
        if (!empty($model['accessors'])) {
            $lines[] = "Accessors: " . implode(', ', $model['accessors']);
        }
        if (!empty($model['mutators'])) {
            $lines[] = "Mutators: " . implode(', ', $model['mutators']);
        }
        $lines[] = "Traits: " . implode(', ', $model['traits'] ?? []);

        return [
            'class' => $model['name'],
            'type' => 'model',
            'namespace' => $model['namespace'],
            'path' => $model['path'],
            'summary' => implode("\n", array_slice($lines, 0, 15)),
            'tags' => ['model', 'eloquent', 'database'],
        ];
    }

    private function summarizeController(array $ctrl, array $data): array
    {
        $lines = [];
        $lines[] = "Controller: {$ctrl['name']} ({$ctrl['namespace']})";
        $lines[] = "Group: {$ctrl['group']}";
        $lines[] = "Is CRUD: " . ($ctrl['is_crud'] ? 'Yes' : 'No');

        $methods = $ctrl['methods'] ?? [];
        $lines[] = "Methods (" . count($methods) . "): " . implode(', ', $methods);

        if (!empty($ctrl['middleware'])) {
            $lines[] = "Middleware: " . implode(', ', $ctrl['middleware']);
        }
        if (!empty($ctrl['validation_classes'])) {
            $lines[] = "Validated by: " . implode(', ', $ctrl['validation_classes']);
        }

        // Infer purpose from methods
        if ($ctrl['is_crud'] ?? false) {
            $lines[] = "Responsibilities: Full CRUD operations for the associated model.";
        } else {
            $lines[] = "Responsibilities: Handles web requests and returns responses.";
        }

        return [
            'class' => $ctrl['name'],
            'type' => 'controller',
            'namespace' => $ctrl['namespace'],
            'path' => $ctrl['path'],
            'summary' => implode("\n", array_slice($lines, 0, 15)),
            'tags' => ['controller', $ctrl['is_crud'] ? 'crud' : 'web'],
        ];
    }

    private function summarizeService(array $service): array
    {
        $lines = [];
        $lines[] = "Service: {$service['name']} ({$service['namespace']})";
        $lines[] = "Methods (" . count($service['methods'] ?? []) . "): " . implode(', ', $service['methods'] ?? []);

        if (!empty($service['dependencies'])) {
            $deps = array_map(fn($d) => "{$d['type']} \${$d['name']}", $service['dependencies']);
            $lines[] = "Injected dependencies: " . implode(', ', $deps);
        }

        $refs = [];
        if (!empty($service['referenced_models'])) $refs[] = 'Models: ' . implode(', ', $this->shortNames($service['referenced_models']));
        if (!empty($service['referenced_repositories'])) $refs[] = 'Repositories: ' . implode(', ', $this->shortNames($service['referenced_repositories']));
        if (!empty($service['referenced_jobs'])) $refs[] = 'Jobs: ' . implode(', ', $this->shortNames($service['referenced_jobs']));
        if (!empty($service['referenced_events'])) $refs[] = 'Events: ' . implode(', ', $this->shortNames($service['referenced_events']));
        if (!empty($service['referenced_notifications'])) $refs[] = 'Notifications: ' . implode(', ', $this->shortNames($service['referenced_notifications']));

        if (!empty($refs)) {
            $lines[] = "References:";
            foreach ($refs as $ref) {
                $lines[] = "  - {$ref}";
            }
        }

        $lines[] = "Responsibilities: Contains business logic for the domain.";

        return [
            'class' => $service['name'],
            'type' => 'service',
            'namespace' => $service['namespace'],
            'path' => $service['path'],
            'summary' => implode("\n", array_slice($lines, 0, 15)),
            'tags' => ['service', 'business_logic'],
        ];
    }

    private function summarizeRepository(array $repo): array
    {
        $lines = [];
        $lines[] = "Repository: {$repo['name']} ({$repo['namespace']})";
        $lines[] = "Type: " . ($repo['type'] ?? 'class');

        if (!empty($repo['methods'])) {
            $lines[] = "Methods (" . count($repo['methods']) . "): " . implode(', ', $repo['methods']);
        }
        if (!empty($repo['referenced_models'])) {
            $lines[] = "Works with models: " . implode(', ', $this->shortNames($repo['referenced_models']));
        }
        if (!empty($repo['dependencies'])) {
            $deps = array_map(fn($d) => "{$d['type']} \${$d['name']}", $repo['dependencies']);
            $lines[] = "Dependencies: " . implode(', ', $deps);
        }

        $lines[] = "Responsibilities: Data access layer for database operations.";

        return [
            'class' => $repo['name'],
            'type' => 'repository',
            'namespace' => $repo['namespace'],
            'path' => $repo['path'],
            'summary' => implode("\n", array_slice($lines, 0, 15)),
            'tags' => ['repository', 'data_access'],
        ];
    }

    private function summarizeFormRequest(array $request): array
    {
        $lines = [];
        $lines[] = "Form Request: {$request['name']} ({$request['namespace']})";
        $rules = $request['rules'] ?? [];
        $lines[] = "Validation rules: " . count($rules) . " fields validated.";
        $lines[] = "Has authorize(): " . ($request['authorize'] ? 'Yes' : 'No');
        $lines[] = "Custom messages: " . ($request['messages'] ? 'Yes' : 'No');
        $lines[] = "Responsibilities: Validates incoming HTTP request data.";

        return [
            'class' => $request['name'],
            'type' => 'form_request',
            'namespace' => $request['namespace'],
            'path' => $request['path'],
            'summary' => implode("\n", array_slice($lines, 0, 15)),
            'tags' => ['form_request', 'validation'],
        ];
    }

    private function summarizePolicy(array $policy): array
    {
        $lines = [];
        $lines[] = "Policy: {$policy['name']} ({$policy['namespace']})";
        $lines[] = "Protects model: " . ($policy['model'] ?: 'Unknown');
        $lines[] = "Abilities (" . count($policy['abilities'] ?? []) . "): " . implode(', ', $policy['abilities'] ?? []);
        $lines[] = "Responsibilities: Authorization rules for the associated model.";

        return [
            'class' => $policy['name'],
            'type' => 'policy',
            'namespace' => $policy['namespace'],
            'path' => $policy['path'],
            'summary' => implode("\n", array_slice($lines, 0, 15)),
            'tags' => ['policy', 'authorization'],
        ];
    }

    private function summarizeEvent(array $event): array
    {
        $lines = [];
        $lines[] = "Event: {$event['name']} ({$event['namespace']})";
        $lines[] = "Broadcasts: " . ($event['should_broadcast'] ? 'Yes' : 'No');
        $lines[] = "Queued: " . ($event['should_queue'] ? 'Yes' : 'No');
        if (!empty($event['properties'])) {
            $lines[] = "Properties: " . implode(', ', array_map(fn($p) => $p['name'], $event['properties']));
        }
        $lines[] = "Responsibilities: Signals that something happened in the application.";

        return [
            'class' => $event['name'],
            'type' => 'event',
            'namespace' => $event['namespace'],
            'path' => $event['path'],
            'summary' => implode("\n", array_slice($lines, 0, 15)),
            'tags' => ['event', 'broadcast' => $event['should_broadcast']],
        ];
    }

    private function summarizeJob(array $job): array
    {
        $lines = [];
        $lines[] = "Job: {$job['name']} ({$job['namespace']})";
        $lines[] = "Queued: " . ($job['queued'] ? 'Yes (async)' : 'No (sync)');
        if ($job['unique']) $lines[] = "Unique: Yes (for {$job['unique_for']} seconds)";
        if (!empty($job['tags'])) $lines[] = "Tags: " . implode(', ', $job['tags']);
        if (!empty($job['handle_params'])) $lines[] = "Handle parameters: " . implode(', ', $job['handle_params']);
        $lines[] = "Responsibilities: Background task processing.";

        return [
            'class' => $job['name'],
            'type' => 'job',
            'namespace' => $job['namespace'],
            'path' => $job['path'],
            'summary' => implode("\n", array_slice($lines, 0, 15)),
            'tags' => ['job', $job['queued'] ? 'queued' : 'sync'],
        ];
    }

    private function summarizeNotification(array $notification): array
    {
        $lines = [];
        $lines[] = "Notification: {$notification['name']} ({$notification['namespace']})";
        $channels = $notification['channels'] ?? [];
        $lines[] = "Channels: " . (empty($channels) ? 'Not specified' : implode(', ', $channels));
        $lines[] = "Methods: " . implode(', ', $notification['methods'] ?? []);
        $lines[] = "Responsibilities: Sends notifications through configured channels.";

        return [
            'class' => $notification['name'],
            'type' => 'notification',
            'namespace' => $notification['namespace'],
            'path' => $notification['path'],
            'summary' => implode("\n", array_slice($lines, 0, 15)),
            'tags' => array_merge(['notification'], $channels),
        ];
    }

    private function shortNames(array $fullyQualifiedNames): array
    {
        return array_map(fn($name) => substr(strrchr($name, '\\') ?: $name, 1), $fullyQualifiedNames);
    }
}