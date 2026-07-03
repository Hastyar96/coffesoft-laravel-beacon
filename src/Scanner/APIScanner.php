<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans API Resources, Controllers, Sanctum/Passport/JWT authentication.
 */
class APIScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $resources = $this->scanApiResources();
        $authentication = $this->detectAuthentication();
        $apiControllers = $this->scanApiControllers();

        return [
            'api' => [
                'resources' => $resources,
                'authentication' => $authentication,
                'controllers' => $apiControllers,
            ],
        ];
    }

    private function scanApiResources(): array
    {
        $paths = [
            app_path('Http/Resources'),
            app_path('Http/API/Resources'),
        ];

        $resources = [];
        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            foreach ($this->reader->getPhpFiles($path) as $file) {
                $contents = $file->getContents();
                $parsed = $this->parser->parse($contents);
                if ($parsed['class_name'] === null) continue;

                $isCollection = str_contains($parsed['parent'] ?? '', 'ResourceCollection')
                    || str_ends_with($parsed['class_name'], 'Collection');

                $resources[] = [
                    'name' => $parsed['class_name'],
                    'namespace' => $parsed['namespace'] ?? '',
                    'path' => $file->getRelativePathname(),
                    'type' => $isCollection ? 'collection' : 'resource',
                    'parent' => $parsed['parent'],
                    'methods' => array_map(fn($m) => $m['name'], $parsed['methods']),
                    'parent' => $parsed['parent'],
                ];
            }
        }

        return $resources;
    }

    private function detectAuthentication(): array
    {
        $auth = [
            'sanctum' => false,
            'passport' => false,
            'jwt' => false,
            'providers' => [],
        ];

        // Check composer.json for auth packages
        $composerPath = base_path('composer.json');
        if (file_exists($composerPath)) {
            $composer = json_decode(file_get_contents($composerPath), true);
            if ($composer) {
                $allDeps = array_merge(
                    $composer['require'] ?? [],
                    $composer['require-dev'] ?? []
                );
                $auth['sanctum'] = isset($allDeps['laravel/sanctum']);
                $auth['passport'] = isset($allDeps['laravel/passport']);
                $auth['jwt'] = isset($allDeps['tymon/jwt-auth'])
                    || isset($allDeps['php-open-source-saver/jwt-auth']);

                // Detect auth providers from config
                $configPath = config_path('auth.php');
                if (file_exists($configPath)) {
                    $config = file_get_contents($configPath);
                    if (preg_match_all('/\'provider\'\s*=>\s*[\'"]([^\'"]+)[\'"]/', $config, $m)) {
                        $auth['providers'] = array_values(array_unique($m[1]));
                    }
                }
            }
        }

        // Check for Sanctum HasApiTokens trait usage in User model
        $userModelPath = app_path('Models/User.php');
        if (file_exists($userModelPath)) {
            $userContents = file_get_contents($userModelPath);
            if (str_contains($userContents, 'HasApiTokens')) {
                $auth['sanctum'] = true;
            }
            if (str_contains($userContents, 'HasApiTokens') && !$auth['sanctum']) {
                // Passport also uses HasApiTokens in some versions
            }
        }

        return $auth;
    }

    private function scanApiControllers(): array
    {
        $apiPaths = [
            app_path('Http/Controllers/Api'),
            app_path('Http/Controllers/API'),
        ];

        $controllers = [];
        foreach ($apiPaths as $path) {
            if (!is_dir($path)) continue;
            foreach ($this->reader->getPhpFiles($path) as $file) {
                $contents = $file->getContents();
                $parsed = $this->parser->parse($contents);
                if ($parsed['class_name'] === null) continue;

                $controllers[] = [
                    'name' => $parsed['class_name'],
                    'namespace' => $parsed['namespace'] ?? '',
                    'path' => $file->getRelativePathname(),
                    'methods' => array_map(fn($m) => $m['name'], $parsed['methods']),
                    'extend' => $parsed['parent'],
                ];
            }
        }

        return $controllers;
    }
}