<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Model Dependency Tracker++
 *
 * Tracks which controllers touch which models and which services depend on which models.
 * Builds a dependency heatmap showing tight coupling and high-risk dependencies.
 */
class ModelDependencyTracker
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function track(array $data): array
    {
        $heatmap = [];
        $maxHeat = 0;

        foreach ($data['models']['items'] ?? [] as $model) {
            $modelName = $model['name'];
            $dependents = [];

            // Controllers that touch this model
            foreach ($data['controllers']['items'] ?? [] as $c) {
                $ctrlModel = preg_replace('/Controller$/', '', $c['name']);
                if ($ctrlModel === $modelName) {
                    $dependents[] = ['type' => 'controller', 'name' => $c['name'], 'path' => $c['path'] ?? ''];
                }
            }

            // Services that reference this model
            foreach ($data['services']['items'] ?? [] as $s) {
                foreach ($s['referenced_models'] ?? [] as $ref) {
                    $parts = explode('\\', $ref);
                    if (end($parts) === $modelName) {
                        $dependents[] = ['type' => 'service', 'name' => $s['name'], 'path' => $s['path'] ?? ''];
                        break;
                    }
                }
            }

            // Repositories that reference this model
            foreach ($data['repositories']['items'] ?? [] as $r) {
                foreach ($r['referenced_models'] ?? [] as $ref) {
                    $parts = explode('\\', $ref);
                    if (end($parts) === $modelName) {
                        $dependents[] = ['type' => 'repository', 'name' => $r['name'], 'path' => $r['path'] ?? ''];
                        break;
                    }
                }
            }

            // Policies for this model
            foreach ($data['policies']['items'] ?? [] as $p) {
                if ($p['model'] === $modelName) {
                    $dependents[] = ['type' => 'policy', 'name' => $p['name'], 'path' => $p['path'] ?? ''];
                }
            }

            // Form requests for this model
            foreach ($data['form_requests']['items'] ?? [] as $req) {
                if (str_contains($req['name'], $modelName)) {
                    $dependents[] = ['type' => 'form_request', 'name' => $req['name'], 'path' => $req['path'] ?? ''];
                }
            }

            $totalDependents = count($dependents);
            $maxHeat = max($maxHeat, $totalDependents);

            $types = [];
            foreach ($dependents as $d) {
                $types[$d['type']] = ($types[$d['type']] ?? 0) + 1;
            }

            $heatmap[] = [
                'model' => $modelName,
                'total_dependents' => $totalDependents,
                'heat_score' => 0, // calculated below
                'dependents' => $dependents,
                'dependents_by_type' => $types,
                'dependency_types' => array_keys($types),
            ];
        }

        // Calculate normalized heat scores (0-100)
        foreach ($heatmap as &$entry) {
            $entry['heat_score'] = $maxHeat > 0
                ? (int) round(($entry['total_dependents'] / $maxHeat) * 100)
                : 0;
        }

        // Sort by heat score descending
        usort($heatmap, fn($a, $b) => $b['heat_score'] <=> $a['heat_score']);

        return [
            'model_dependencies' => [
                'heatmap' => $heatmap,
                'model_count' => count($heatmap),
                'most_dependent_model' => $heatmap[0]['model'] ?? null,
                'max_heat_score' => $maxHeat,
                'top_risk_models' => array_slice(array_filter($heatmap, fn($h) => $h['heat_score'] >= 60), 0, 5),
                'confidence' => 85,
            ],
        ];
    }
}