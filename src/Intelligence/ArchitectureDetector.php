<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Automatically detects the project's architecture style.
 *
 * Analyzes patterns in the codebase to determine if it uses MVC,
 * Repository Pattern, Service Layer, Action Pattern, DDD, Modular, etc.
 */
class ArchitectureDetector
{
    /**
     * Detect architecture style from scanned data.
     *
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function detect(array $data): array
    {
        $architectures = [];
        $reasons = [];

        // Check for Repository Pattern
        if ($this->hasRepositoryPattern($data)) {
            $architectures[] = 'Repository Pattern';
            $reasons['Repository Pattern'] = 'Interfaces and implementations found in app/Repositories with model references';
        }

        // Check for Service Layer
        if ($this->hasServiceLayer($data)) {
            $architectures[] = 'Service Layer';
            $reasons['Service Layer'] = 'Business logic encapsulated in app/Services with injected dependencies';
        }

        // Check for Action Pattern
        if ($this->hasActionPattern($data)) {
            $architectures[] = 'Action Pattern';
            $reasons['Action Pattern'] = 'Single-action classes found (__invoke methods in dedicated classes)';
        }

        // Check for DDD (Domain-Driven Design)
        if ($this->hasDDD($data)) {
            $architectures[] = 'DDD';
            $reasons['DDD'] = 'Domain-centric directory structure with entities, value objects, and domain services';
        }

        // Check for Modular architecture
        if ($this->hasModular($data)) {
            $architectures[] = 'Modular';
            $reasons['Modular'] = 'Self-contained modules detected with their own routes, controllers, and views';
        }

        // Check for Single Module
        if ($this->isSingleModule($data)) {
            $architectures[] = 'Single Module';
            $reasons['Single Module'] = 'All functionality within a single module or directory namespace';
        }

        // Check for API First
        if ($this->isApiFirst($data)) {
            $architectures[] = 'API First';
            $reasons['API First'] = 'API routes, resources, and controllers form the primary interface';
        }

        // Check for MVC (always present in Laravel)
        $architectures[] = 'MVC';
        $reasons['MVC'] = 'Laravel\'s convention-over-configuration MVC structure is the foundation';

        // Determine primary architecture
        $primary = 'MVC'; // Always at least MVC
        $secondary = [];

        // Remove MVC from the list for determining non-default architectures
        $nonDefault = array_filter($architectures, fn($a) => $a !== 'MVC');

        if (!empty($nonDefault)) {
            foreach ($nonDefault as $arch) {
                $secondary[] = $arch;
            }
        }

        $hybrid = count($secondary) > 1;

        return [
            'architecture' => [
                'primary' => $primary,
                'secondary' => array_values($secondary),
                'is_hybrid' => $hybrid,
                'detected_architectures' => $architectures,
                'explanations' => $reasons,
            ],
        ];
    }

    private function hasRepositoryPattern(array $data): bool
    {
        $repos = $data['repositories']['items'] ?? [];
        $hasInterface = false;
        $hasImplementation = false;

        foreach ($repos as $repo) {
            $type = $repo['type'] ?? '';
            if ($type === 'interface') $hasInterface = true;
            if ($type === 'implementation') $hasImplementation = true;
        }

        return $hasInterface && $hasImplementation;
    }

    private function hasServiceLayer(array $data): bool
    {
        return ($data['services']['count'] ?? 0) >= 2;
    }

    private function hasActionPattern(array $data): bool
    {
        $controllers = $data['controllers']['items'] ?? [];
        foreach ($controllers as $ctrl) {
            if (in_array('__invoke', $ctrl['methods'] ?? []) && count($ctrl['methods'] ?? []) <= 2) {
                return true;
            }
        }
        return false;
    }

    private function hasDDD(array $data): bool
    {
        $paths = [
            app_path('Domain'),
            app_path('Application'),
            app_path('Infrastructure'),
            app_path('Entities'),
            app_path('ValueObjects'),
        ];

        foreach ($paths as $path) {
            if (is_dir($path)) return true;
        }

        return false;
    }

    private function hasModular(array $data): bool
    {
        return ($data['modules']['count'] ?? 0) >= 2;
    }

    private function isSingleModule(array $data): bool
    {
        return ($data['modules']['count'] ?? 0) === 1;
    }

    private function isApiFirst(array $data): bool
    {
        $routes = $data['routes']['items'] ?? [];
        $apiCount = 0;
        $webCount = 0;

        foreach ($routes as $route) {
            if (str_starts_with($route['uri'] ?? '', 'api/') || ($route['prefix'] ?? '') === 'api') {
                $apiCount++;
            } elseif (($route['prefix'] ?? '') !== 'api' && ($route['prefix'] ?? '') !== 'admin') {
                $webCount++;
            }
        }

        return $apiCount > $webCount;
    }
}