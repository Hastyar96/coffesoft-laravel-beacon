<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Analyzes scanned project data to detect logical modules
 * from routes, controllers, namespaces, and folder structure.
 */
class ModuleDetector
{
    /**
     * Detect modules from scanned project data.
     *
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function detect(array $data): array
    {
        $modules = [];
        $routes = $data['routes']['groups'] ?? [];
        $controllers = $data['controllers']['items'] ?? [];

        // Detect modules from route groups
        $routeBased = $this->detectFromRoutes($routes);
        foreach ($routeBased as $key => $module) {
            $modules[$key] = $module;
        }

        // Detect modules from controller namespace groups
        $controllerBased = $this->detectFromControllers($controllers);
        foreach ($controllerBased as $key => $module) {
            if (! isset($modules[$key])) {
                $modules[$key] = $module;
            } else {
                $modules[$key]['route_count'] += $module['route_count'];
            }
        }

        // Detect modules from folder structure
        $folderBased = $this->detectFromFolders();
        foreach ($folderBased as $key => $module) {
            if (! isset($modules[$key])) {
                $modules[$key] = $module;
            }
        }

        $controllerGroups = [];

        foreach ($controllers as $c) {
            $g = $c['group'] ?? 'root';
            if ($g !== 'root' && ! isset($controllerGroups[$g])) {
                $controllerGroups[$g] = 0;
            }
            if ($g !== 'root') {
                $controllerGroups[$g]++;
            }
        }

        return [
            'modules' => [
                'items' => $modules,
                'count' => count($modules),
            ],
            'controller_groups' => $controllerGroups,
            'total_modules' => count($modules),
        ];
    }

    /**
     * @param array<string, int> $routes
     * @return array<string, array<string, mixed>>
     */
    private function detectFromRoutes(array $routes): array
    {
        $modules = [];

        $knownPrefixes = [
            'admin' => 'Admin Panel',
            'api' => 'API',
            'coach' => 'Coach',
            'coach-workflow' => 'Coach',
            'trainee' => 'Trainee',
            'trainee-auth' => 'Trainee',
            'public' => 'Public',
            'web' => 'Web',
            'dashboard' => 'Dashboard',
            'auth' => 'Auth',
        ];

        foreach ($knownPrefixes as $prefix => $label) {
            if (isset($routes[$prefix]) && $routes[$prefix] > 0) {
                $key = strtolower(str_replace(' ', '_', $label));
                if (! isset($modules[$key])) {
                    $modules[$key] = [
                        'key' => $key,
                        'label' => $label,
                        'route_count' => 0,
                        'detected' => true,
                        'source' => 'routes',
                    ];
                }
                $modules[$key]['route_count'] += $routes[$prefix];
            }
        }

        return $modules;
    }

    /**
     * @param array<int, array<string, mixed>> $controllers
     * @return array<string, array<string, mixed>>
     */
    private function detectFromControllers(array $controllers): array
    {
        $modules = [];
        $groupCounts = [];

        foreach ($controllers as $c) {
            $ns = $c['namespace'] ?? '';
            $group = $c['group'] ?? 'root';

            // Detect from namespace segments
            $parts = explode('\\', $ns);
            foreach ($parts as $part) {
                $known = ['Admin', 'Api', 'Auth', 'Trainee'];
                if (in_array($part, $known)) {
                    $key = strtolower($part);
                    if (! isset($groupCounts[$key])) {
                        $groupCounts[$key] = 0;
                    }
                    $groupCounts[$key]++;
                }
            }

            // Detect from non-root groups
            if ($group !== 'root') {
                $parts = explode('/', $group);
                foreach ($parts as $part) {
                    $known = ['Admin', 'Api', 'Auth', 'Trainee'];
                    $ucPart = ucfirst($part);
                    if (in_array($ucPart, $known)) {
                        $key = strtolower($ucPart);
                        if (! isset($groupCounts[$key])) {
                            $groupCounts[$key] = 0;
                        }
                        $groupCounts[$key]++;
                    }
                }
            }
        }

        $labelMap = [
            'admin' => 'Admin Panel',
            'api' => 'API',
            'auth' => 'Auth',
            'trainee' => 'Trainee',
        ];

        foreach ($groupCounts as $key => $count) {
            if (! isset($modules[$key])) {
                $modules[$key] = [
                    'key' => $key,
                    'label' => $labelMap[$key] ?? ucfirst($key),
                    'route_count' => 0,
                    'detected' => true,
                    'source' => 'controllers',
                ];
            }
        }

        return $modules;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function detectFromFolders(): array
    {
        $modules = [];
        $knownModules = [
            'Admin' => 'Admin Panel',
            'Api' => 'API',
            'Auth' => 'Auth',
            'Trainee' => 'Trainee',
        ];

        // Check app/Http/Controllers subdirectories
        $path = app_path('Http/Controllers');
        if (is_dir($path)) {
            $dirs = scandir($path);
            foreach ($dirs as $dir) {
                if ($dir === '.' || $dir === '..') {
                    continue;
                }
                if (is_dir($path . '/' . $dir) && isset($knownModules[$dir])) {
                    $key = strtolower($dir);
                    if (! isset($modules[$key])) {
                        $modules[$key] = [
                            'key' => $key,
                            'label' => $knownModules[$dir],
                            'route_count' => 0,
                            'detected' => true,
                            'source' => 'folders',
                        ];
                    }
                }
            }
        }

        return $modules;
    }
}