<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Exporter;

use Coffesoft\LaravelBeacon\Context\Context;

/**
 * Exporter — Generates 15+ output files including AI tools and guides.
 */
class JsonExporter
{
    public function export(Context $context, string $path): void
    {
        $data = $context->all();
        $filename = basename($path);

        $exportData = match (true) {
            str_contains($filename, 'context') && str_contains($filename, '.json') => $this->buildFullContext($data),
            str_contains($filename, 'project-graph') => $this->buildProjectGraph($data),
            str_contains($filename, 'architecture') => $this->buildArchitecture($data),
            str_contains($filename, 'business-rules') => $this->buildBusinessRules($data),
            str_contains($filename, 'statistics') => $this->buildStatistics($data),
            str_contains($filename, 'packages') => $this->buildPackages($data),
            str_contains($filename, 'database') => $this->buildDatabase($data),
            str_contains($filename, 'routes') => $this->buildRoutes($data),
            str_contains($filename, 'ai-index') => $this->buildAIIndex($data),
            str_contains($filename, 'dependency-graph') => $this->buildDependencyGraph($data),
            str_contains($filename, 'features') => $this->buildFeatures($data),
            str_contains($filename, 'impact-map') => $this->buildImpactMap($data),
            str_contains($filename, 'entry-points') => $this->buildEntryPoints($data),
            str_contains($filename, 'workflows') => $this->buildWorkflows($data),
            default => $data,
        };

        $json = json_encode($exportData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        file_put_contents($path, $json);
    }

    public function exportAll(Context $context, string $directory): array
    {
        $data = $context->all();
        $files = [];

        $exports = [
            'context.json' => $this->buildFullContext($data),
            'project-graph.json' => $this->buildProjectGraph($data),
            'architecture.json' => $this->buildArchitecture($data),
            'business-rules.json' => $this->buildBusinessRules($data),
            'statistics.json' => $this->buildStatistics($data),
            'packages.json' => $this->buildPackages($data),
            'database.json' => $this->buildDatabase($data),
            'routes.json' => $this->buildRoutes($data),
            'ai-index.json' => $this->buildAIIndex($data),
            'dependency-graph.json' => $this->buildDependencyGraph($data),
            'features.json' => $this->buildFeatures($data),
            'impact-map.json' => $this->buildImpactMap($data),
            'entry-points.json' => $this->buildEntryPoints($data),
            'workflows.json' => $this->buildWorkflows($data),
        ];

        foreach ($exports as $filename => $content) {
            $path = $directory . '/' . $filename;
            file_put_contents(
                $path,
                json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
            );
            $files[] = $path;
        }

        return $files;
    }

    private function buildFullContext(array $data): array
    {
        return [
            'project' => [
                'name' => basename(base_path()),
                'framework' => $data['framework'] ?? [],
                'generated_at' => $data['generated_at'] ?? null,
                'beacon_version' => $data['beacon_version'] ?? '1.0.0',
            ],
            'summary' => [
                'models' => $data['models']['count'] ?? 0,
                'controllers' => $data['controllers']['count'] ?? 0,
                'routes' => $data['routes']['count'] ?? 0,
                'services' => $data['services']['count'] ?? 0,
                'repositories' => $data['repositories']['count'] ?? 0,
                'form_requests' => $data['form_requests']['count'] ?? 0,
                'policies' => $data['policies']['count'] ?? 0,
                'events' => $data['events']['count'] ?? 0,
                'jobs' => $data['jobs']['count'] ?? 0,
                'notifications' => $data['notifications']['count'] ?? 0,
                'enums' => $data['enums']['count'] ?? 0,
                'blade_views' => $data['blade']['count'] ?? 0,
                'packages' => $data['packages']['count'] ?? 0,
            ],
            'models' => $data['models'] ?? [],
            'controllers' => $data['controllers'] ?? [],
            'routes' => $data['routes'] ?? [],
            'services' => $data['services'] ?? [],
            'repositories' => $data['repositories'] ?? [],
            'form_requests' => $data['form_requests'] ?? [],
            'middleware' => $data['middleware'] ?? [],
            'policies' => $data['policies'] ?? [],
            'events' => $data['events'] ?? [],
            'jobs' => $data['jobs'] ?? [],
            'notifications' => $data['notifications'] ?? [],
            'mail' => $data['mail'] ?? [],
            'traits' => $data['traits'] ?? [],
            'enums' => $data['enums'] ?? [],
            'helpers' => $data['helpers'] ?? [],
            'livewire' => $data['livewire'] ?? [],
            'blade' => $data['blade'] ?? [],
            'api' => $data['api'] ?? [],
            'queue' => $data['queue'] ?? [],
            'storage' => $data['storage'] ?? [],
            'packages' => $data['packages'] ?? [],
            'modules' => $data['modules'] ?? [],
            'database' => $data['database'] ?? [],
            'business_rules' => $data['business_rules'] ?? [],
            'security' => $data['security'] ?? [],
            'performance' => $data['performance'] ?? [],
            'architecture' => $data['architecture'] ?? [],
            'project_graph' => $data['project_graph'] ?? [],
            'ai_summaries' => $data['ai_summaries'] ?? [],
            'statistics' => $data['statistics'] ?? [],
            'folder_tree' => $data['folder_tree'] ?? [],
            //
            'entry_points' => $data['entry_points'] ?? [],
            'workflows' => $data['workflows'] ?? [],
            'features' => $data['features'] ?? [],
            'dependency_graph' => $data['dependency_graph'] ?? [],
            'impact_map' => $data['impact_map'] ?? [],
            'cache_stats' => $data['cache_stats'] ?? [],
        ];
    }

    private function buildDependencyGraph(array $data): array
    {
        return [
            'dependency_graph' => $data['dependency_graph'] ?? [],
        ];
    }

    private function buildFeatures(array $data): array
    {
        return [
            'features' => $data['features'] ?? [],
            'workflows' => $data['workflows'] ?? [],
        ];
    }

    private function buildImpactMap(array $data): array
    {
        return [
            'impact_map' => $data['impact_map'] ?? [],
        ];
    }

    private function buildEntryPoints(array $data): array
    {
        return [
            'entry_points' => $data['entry_points'] ?? [],
        ];
    }

    private function buildWorkflows(array $data): array
    {
        return [
            'workflows' => $data['workflows'] ?? [],
        ];
    }

    private function buildProjectGraph(array $data): array
    {
        return [
            'project_graph' => $data['project_graph'] ?? [],
            'modules' => $data['modules'] ?? [],
            'route_groups' => $data['route_intelligence']['groups'] ?? [],
        ];
    }

    private function buildArchitecture(array $data): array
    {
        return ['architecture' => $data['architecture'] ?? []];
    }

    private function buildBusinessRules(array $data): array
    {
        return ['business_rules' => $data['business_rules'] ?? []];
    }

    private function buildStatistics(array $data): array
    {
        return ['statistics' => $data['statistics'] ?? []];
    }

    private function buildPackages(array $data): array
    {
        return ['packages' => $data['packages'] ?? []];
    }

    private function buildDatabase(array $data): array
    {
        return [
            'database' => $data['database'] ?? [],
            'database_intelligence' => $data['database_intelligence'] ?? [],
        ];
    }

    private function buildRoutes(array $data): array
    {
        return [
            'routes' => $data['routes'] ?? [],
            'route_intelligence' => $data['route_intelligence'] ?? [],
        ];
    }

    private function buildAIIndex(array $data): array
    {
        return [
            'project' => [
                'name' => basename(base_path()),
                'framework' => $data['framework'] ?? [],
                'statistics' => $data['statistics'] ?? [],
            ],
            'models' => $data['models'] ?? [],
            'controllers' => $data['controllers'] ?? [],
            'services' => $data['services'] ?? [],
            'repositories' => $data['repositories'] ?? [],
            'form_requests' => $data['form_requests'] ?? [],
            'policies' => $data['policies'] ?? [],
            'events' => $data['events'] ?? [],
            'jobs' => $data['jobs'] ?? [],
            'notifications' => $data['notifications'] ?? [],
            'mail' => $data['mail'] ?? [],
            'enums' => $data['enums'] ?? [],
            'livewire' => $data['livewire'] ?? [],
            'api' => $data['api'] ?? [],
            'business_rules' => $data['business_rules'] ?? [],
            'security' => $data['security'] ?? [],
            'architecture' => $data['architecture'] ?? [],
            'entry_points' => $data['entry_points'] ?? [],
            'workflows' => $data['workflows'] ?? [],
        ];
    }
}