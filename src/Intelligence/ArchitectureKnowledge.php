<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Architecture Knowledge Detection
 *
 * Returns confidence scores for: MVC, Repository Pattern, Service Layer,
 * DDD, CQRS, Modules, API First, Event Driven, Layered Architecture, Hexagonal.
 */
class ArchitectureKnowledge
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function detect(array $data): array
    {
        $patterns = [];

        // MVC — always present in Laravel
        $patterns[] = [
            'name' => 'MVC',
            'confidence' => 95,
            'reason' => 'Laravel\'s foundation: Models, Views, Controllers directory structure',
            'evidence' => $this->getMvcEvidence($data),
        ];

        // Repository Pattern
        $repoEvidence = $this->getRepositoryEvidence($data);
        $patterns[] = [
            'name' => 'Repository Pattern',
            'confidence' => $repoEvidence['confidence'],
            'reason' => $repoEvidence['reason'],
            'evidence' => $repoEvidence['details'],
        ];

        // Service Layer
        $svcEvidence = $this->getServiceLayerEvidence($data);
        $patterns[] = [
            'name' => 'Service Layer',
            'confidence' => $svcEvidence['confidence'],
            'reason' => $svcEvidence['reason'],
            'evidence' => $svcEvidence['details'],
        ];

        // DDD
        $dddEvidence = $this->getDddEvidence($data);
        $patterns[] = [
            'name' => 'Domain-Driven Design (DDD)',
            'confidence' => $dddEvidence['confidence'],
            'reason' => $dddEvidence['reason'],
            'evidence' => $dddEvidence['details'],
        ];

        // CQRS
        $patterns[] = [
            'name' => 'CQRS',
            'confidence' => $this->detectCqrs($data),
            'reason' => $this->detectCqrs($data) > 0 ? 'Separate read/write operations detected' : 'No separate read/write models detected',
            'evidence' => [],
        ];

        // Modular
        $modEvidence = $this->getModularEvidence($data);
        $patterns[] = [
            'name' => 'Modular Architecture',
            'confidence' => $modEvidence['confidence'],
            'reason' => $modEvidence['reason'],
            'evidence' => $modEvidence['details'],
        ];

        // API First
        $apiEvidence = $this->getApiFirstEvidence($data);
        $patterns[] = [
            'name' => 'API First',
            'confidence' => $apiEvidence['confidence'],
            'reason' => $apiEvidence['reason'],
            'evidence' => $apiEvidence['details'],
        ];

        // Event Driven
        $eventEvidence = $this->getEventDrivenEvidence($data);
        $patterns[] = [
            'name' => 'Event-Driven Architecture',
            'confidence' => $eventEvidence['confidence'],
            'reason' => $eventEvidence['reason'],
            'evidence' => $eventEvidence['details'],
        ];

        // Layered
        $patterns[] = [
            'name' => 'Layered Architecture',
            'confidence' => $this->detectLayered($data),
            'reason' => $this->detectLayered($data) > 0 ? 'Multiple distinct layers detected (Controllers, Services, Repositories)' : 'Standard Laravel layers present',
            'evidence' => [],
        ];

        // Hexagonal / Ports and Adapters
        $hexEvidence = $this->getHexagonalEvidence($data);
        $patterns[] = [
            'name' => 'Hexagonal (Ports and Adapters)',
            'confidence' => $hexEvidence['confidence'],
            'reason' => $hexEvidence['reason'],
            'evidence' => $hexEvidence['details'],
        ];

        // Sort by confidence descending
        usort($patterns, fn($a, $b) => $b['confidence'] <=> $a['confidence']);

        // Primary architecture
        $primary = $patterns[0]['name'] ?? 'MVC';

        return [
            'architecture_knowledge' => [
                'primary' => $primary,
                'patterns' => $patterns,
                'pattern_count' => count($patterns),
                'confidence' => 85,
            ],
        ];
    }

    private function getMvcEvidence(array $data): array
    {
        return [
            'models' => ($data['models']['count'] ?? 0) > 0,
            'views' => ($data['blade']['count'] ?? 0) > 0,
            'controllers' => ($data['controllers']['count'] ?? 0) > 0,
        ];
    }

    private function getRepositoryEvidence(array $data): array
    {
        $items = $data['repositories']['items'] ?? [];
        $hasInterface = false;
        $hasImplementation = false;

        foreach ($items as $r) {
            $type = $r['type'] ?? '';
            if ($type === 'interface') $hasInterface = true;
            if ($type === 'implementation') $hasImplementation = true;
        }

        if ($hasInterface && $hasImplementation) {
            $confidence = 90;
            $reason = 'Repository pattern with interfaces and implementations detected';
        } elseif (count($items) > 0) {
            $confidence = 50;
            $reason = 'Repository files exist but no interface/implementation separation';
        } else {
            $confidence = 0;
            $reason = 'No repository pattern detected';
        }

        return [
            'confidence' => $confidence,
            'reason' => $reason,
            'details' => ['interfaces' => $hasInterface, 'implementations' => $hasImplementation, 'count' => count($items)],
        ];
    }

    private function getServiceLayerEvidence(array $data): array
    {
        $count = $data['services']['count'] ?? 0;
        if ($count >= 5) return ['confidence' => 95, 'reason' => "Robust service layer with {$count} service classes", 'details' => ['count' => $count]];
        if ($count >= 2) return ['confidence' => 75, 'reason' => "Service layer detected with {$count} services", 'details' => ['count' => $count]];
        if ($count >= 1) return ['confidence' => 40, 'reason' => 'Minimal service layer (1 service)', 'details' => ['count' => $count]];
        return ['confidence' => 0, 'reason' => 'No service layer detected', 'details' => ['count' => 0]];
    }

    private function getDddEvidence(array $data): array
    {
        $score = 0;
        $details = [];

        if (is_dir(app_path('Domain')) || is_dir(app_path('../domain'))) {
            $score += 40;
            $details[] = 'Domain directory exists';
        }
        if (is_dir(app_path('Application'))) {
            $score += 20;
            $details[] = 'Application directory exists';
        }
        if (is_dir(app_path('Infrastructure'))) {
            $score += 20;
            $details[] = 'Infrastructure directory exists';
        }
        if (is_dir(app_path('Entities')) || is_dir(app_path('ValueObjects'))) {
            $score += 10;
            $details[] = 'Entities/Value Objects directory exists';
        }
        if (is_dir(app_path('Repositories'))) {
            $score += 10;
            $details[] = 'Repositories directory exists (common in DDD)';
        }

        return [
            'confidence' => $score,
            'reason' => $score > 0 ? 'Domain-driven directory structure detected' : 'No DDD structure detected',
            'details' => $details,
        ];
    }

    private function detectCqrs(array $data): int
    {
        // Check for separate read/write classes
        $hasRead = false;
        $hasWrite = false;

        foreach ($data['services']['items'] ?? [] as $s) {
            $name = strtolower($s['name']);
            if (str_contains($name, 'read') || str_contains($name, 'query')) $hasRead = true;
            if (str_contains($name, 'write') || str_contains($name, 'command')) $hasWrite = true;
        }

        if ($hasRead && $hasWrite) return 70;
        if ($hasRead || $hasWrite) return 30;
        return 0;
    }

    private function getModularEvidence(array $data): array
    {
        $score = 0;
        $details = [];

        if (is_dir(base_path('Modules'))) {
            $score += 50;
            $details[] = 'Modules directory (nwidart style)';
        }
        if (is_dir(app_path('../Modules'))) {
            $score += 40;
            $details[] = 'Modules in app root';
        }

        $routeGroups = $data['route_intelligence']['groups'] ?? [];
        if (count($routeGroups) >= 3) {
            $score += 10;
            $details[] = count($routeGroups) . ' route groups detected';
        }

        return [
            'confidence' => $score,
            'reason' => $score > 0 ? 'Modular architecture detected' : 'No modular architecture detected',
            'details' => $details,
        ];
    }

    private function getApiFirstEvidence(array $data): array
    {
        $apiRoutes = array_filter($data['routes']['items'] ?? [], fn($r) => str_starts_with($r['uri'] ?? '', 'api'));
        $webRoutes = array_filter($data['routes']['items'] ?? [], fn($r) => !str_starts_with($r['uri'] ?? '', 'api') && !str_starts_with($r['uri'] ?? '', 'admin'));

        $apiCount = count($apiRoutes);
        $webCount = count($webRoutes);

        $score = 0;
        $details = [];

        if ($apiCount > $webCount) {
            $score = 70;
            $details[] = "More API routes ({$apiCount}) than web routes ({$webCount})";
        } elseif ($apiCount > 0) {
            $score = 30;
            $details[] = "API routes exist ({$apiCount}) but web routes dominate";
        }

        if (!empty($data['api']['resources'])) {
            $score += 20;
            $details[] = 'API Resources exist';
        }

        return [
            'confidence' => min($score, 90),
            'reason' => $score > 0 ? 'API-first approach detected' : 'Web-first approach detected',
            'details' => $details,
        ];
    }

    private function getEventDrivenEvidence(array $data): array
    {
        $score = 0;
        $details = [];
        $eventCount = $data['events']['count'] ?? 0;
        $listenerCount = count($data['events']['listeners'] ?? []);

        if ($eventCount > 0) {
            $score += 30;
            $details[] = "{$eventCount} events defined";
        }
        if ($listenerCount > 0) {
            $score += 30;
            $details[] = "{$listenerCount} listeners defined";
        }
        if (count($data['events']['subscribers'] ?? []) > 0) {
            $score += 20;
            $details[] = 'Event subscribers detected';
        }
        if (count($data['events']['dispatchers'] ?? []) > 0) {
            $score += 10;
            $details[] = 'Events dispatched from controllers/services';
        }

        return [
            'confidence' => $score,
            'reason' => $score > 0 ? 'Event-driven architecture detected' : 'No event-driven architecture detected',
            'details' => $details,
        ];
    }

    private function detectLayered(array $data): int
    {
        $score = 0;
        if (($data['controllers']['count'] ?? 0) > 0) $score += 20;
        if (($data['services']['count'] ?? 0) > 0) $score += 20;
        if (($data['repositories']['count'] ?? 0) > 0) $score += 20;
        if (count($data['form_requests']['items'] ?? []) > 0) $score += 15;
        if (count($data['policies']['items'] ?? []) > 0) $score += 15;
        if (($data['events']['count'] ?? 0) > 0) $score += 10;
        return $score;
    }

    private function getHexagonalEvidence(array $data): array
    {
        $score = 0;
        $details = [];

        // Check for repository interfaces (ports)
        $repos = $data['repositories']['items'] ?? [];
        $hasInterface = false;
        foreach ($repos as $r) {
            if ($r['type'] === 'interface') {
                $hasInterface = true;
                break;
            }
        }

        if ($hasInterface) {
            $score += 40;
            $details[] = 'Repository interfaces (ports) detected';
        }

        // Check for service interfaces
        foreach ($data['services']['items'] ?? [] as $s) {
            if (!empty($s['interfaces_implemented'])) {
                $score += 20;
                $details[] = 'Services implementing interfaces';
                break;
            }
        }

        return [
            'confidence' => $score,
            'reason' => $score > 0 ? 'Hexagonal architecture indicators detected' : 'No hexagonal architecture detected',
            'details' => $details,
        ];
    }
}