<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Identifies project entry points for new developers.
 * Detects home page, dashboard, admin panel, authentication, API, commands, and scheduled tasks.
 */
class EntryPointDetector
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function detect(array $data): array
    {
        $entryPoints = [];

        // Home page
        $homeRoute = $this->findRoute($data, fn($r) => $r['uri'] === '/' || $r['uri'] === 'home');
        if ($homeRoute) {
            $entryPoints[] = [
                'name' => 'Home Page',
                'type' => 'web_route',
                'uri' => $homeRoute['uri'],
                'action' => $homeRoute['action'],
                'description' => 'Primary entry point for web visitors',
                'confidence' => 95,
            ];
        }

        // Dashboard
        $dashboardRoute = $this->findRoute($data, fn($r) => str_contains($r['uri'], 'dashboard'));
        if ($dashboardRoute) {
            $entryPoints[] = [
                'name' => 'Dashboard',
                'type' => 'web_route',
                'uri' => $dashboardRoute['uri'],
                'action' => $dashboardRoute['action'],
                'description' => 'Main dashboard for authenticated users',
                'confidence' => 90,
            ];
        }

        // Admin panel
        $adminRoutes = array_filter($data['routes']['items'] ?? [], fn($r) => str_starts_with($r['uri'] ?? '', 'admin'));
        if (!empty($adminRoutes)) {
            $entryPoints[] = [
                'name' => 'Admin Panel',
                'type' => 'admin_route',
                'uri' => '/admin/*',
                'routes_count' => count($adminRoutes),
                'description' => 'Administrative interface for managing the application',
                'confidence' => 95,
            ];
        }

        // Authentication
        $authRoutes = array_filter($data['routes']['items'] ?? [], fn($r) =>
            str_contains($r['uri'] ?? '', 'login') ||
            str_contains($r['uri'] ?? '', 'register') ||
            str_contains($r['uri'] ?? '', 'password') ||
            str_contains($r['uri'] ?? '', 'logout')
        );
        if (!empty($authRoutes)) {
            $entryPoints[] = [
                'name' => 'Authentication',
                'type' => 'auth_flow',
                'routes' => array_map(fn($r) => $r['uri'], $authRoutes),
                'description' => 'Login, registration, and password management',
                'confidence' => 95,
            ];
        }

        // API entry
        $apiRoutes = array_filter($data['routes']['items'] ?? [], fn($r) => str_starts_with($r['uri'] ?? '', 'api'));
        if (!empty($apiRoutes)) {
            $apiGroup = $data['route_intelligence']['groups']['api'] ?? [];
            $entryPoints[] = [
                'name' => 'API',
                'type' => 'api_route',
                'uri' => '/api/*',
                'routes_count' => count($apiRoutes),
                'controllers' => $apiGroup['controllers'] ?? [],
                'auth_method' => $this->detectApiAuth($data),
                'description' => 'REST API endpoints for external consumers',
                'confidence' => 95,
            ];
        }

        // Console commands
        $commands = $this->scanConsoleCommands();
        if (!empty($commands)) {
            $entryPoints[] = [
                'name' => 'Console Commands',
                'type' => 'artisan_command',
                'commands' => $commands,
                'description' => 'Artisan CLI commands for maintenance and operations',
                'confidence' => 85,
            ];
        }

        // Scheduled tasks (from Kernel)
        $scheduled = $this->detectScheduledTasks();
        if (!empty($scheduled)) {
            $entryPoints[] = [
                'name' => 'Scheduled Tasks',
                'type' => 'schedule',
                'tasks' => $scheduled,
                'description' => 'Cron-scheduled background tasks',
                'confidence' => 80,
            ];
        }

        return [
            'entry_points' => [
                'count' => count($entryPoints),
                'items' => $entryPoints,
                'recommended_start' => $this->getRecommendedStart($entryPoints, $data),
                'confidence' => 85,
            ],
        ];
    }

    private function findRoute(array $data, callable $predicate): ?array
    {
        foreach ($data['routes']['items'] ?? [] as $route) {
            if ($predicate($route)) return $route;
        }
        return null;
    }

    private function detectApiAuth(array $data): ?string
    {
        $auth = $data['api']['authentication'] ?? [];
        if ($auth['sanctum'] ?? false) return 'sanctum';
        if ($auth['passport'] ?? false) return 'passport';
        if ($auth['jwt'] ?? false) return 'jwt';
        return null;
    }

    private function scanConsoleCommands(): array
    {
        $commands = [];
        $path = app_path('Console/Commands');
        if (!is_dir($path)) return $commands;

        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());
            if (preg_match('/protected\s+\$signature\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
                $desc = '';
                if (preg_match('/protected\s+\$description\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $dm)) {
                    $desc = $dm[1];
                }
                $commands[] = [
                    'signature' => $m[1],
                    'description' => $desc ?: 'No description',
                ];
            }
        }

        return $commands;
    }

    private function detectScheduledTasks(): array
    {
        $tasks = [];
        $kernelPath = app_path('Console/Kernel.php');
        if (!file_exists($kernelPath)) return $tasks;

        $contents = file_get_contents($kernelPath);
        if (preg_match('/function\s+schedule\s*\([^)]*\)\s*\{(.*?)\}/s', $contents, $m)) {
            $scheduleBody = $m[1];

            if (preg_match_all('/->command\s*\(\s*[\'"]([^\'"]+)[\'"]/', $scheduleBody, $matches)) {
                foreach ($matches[1] as $cmd) {
                    $tasks[] = ['command' => $cmd, 'frequency' => 'scheduled'];
                }
            }
            if (preg_match_all('/->job\s*\(\s*([^,]+)/', $scheduleBody, $matches)) {
                foreach ($matches[1] as $job) {
                    $jobName = trim(str_replace(['new ', '(', ')'], '', $job));
                    $tasks[] = ['job' => $jobName, 'frequency' => 'scheduled'];
                }
            }
            if (preg_match_all('/->call\s*\(/', $scheduleBody)) {
                $tasks[] = ['type' => 'closure', 'frequency' => 'scheduled'];
            }
        }

        return $tasks;
    }

    private function getRecommendedStart(array $entryPoints, array $data): array
    {
        $recommendations = [];

        $recommendations[] = [
            'file' => 'routes/web.php',
            'reason' => 'All web routes are defined here — the starting point for HTTP requests',
        ];
        $recommendations[] = [
            'file' => 'routes/api.php',
            'reason' => 'API routes for external integrations',
        ];

        // Find first CRUD controller as an example
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            if ($ctrl['is_crud'] ?? false) {
                $recommendations[] = [
                    'file' => 'app/Http/Controllers/' . $ctrl['path'],
                    'reason' => "Example CRUD controller — understand the pattern used across the project",
                ];
                break;
            }
        }

        // Find first model
        if (!empty($data['models']['items'])) {
            $firstModel = $data['models']['items'][0];
            $recommendations[] = [
                'file' => 'app/Models/' . $firstModel['path'],
                'reason' => "Core model — understand the main entity structure",
            ];
        }

        $recommendations[] = [
            'file' => '.env',
            'reason' => 'Environment configuration (API keys, database, queue)',
        ];

        return $recommendations;
    }
}