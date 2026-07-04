<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates a clean architecture tree showing only meaningful directories.
 */
class FolderTreeGenerator
{
    /**
     * @return array<string, mixed>
     */
    public function generate(): array
    {
        $base = base_path();
        $tree = [
            'name' => basename($base),
            'path' => '',
            'type' => 'directory',
            'children' => [],
        ];

        // Key directories to analyze
        $directories = [
            'app' => [
                'Console',
                'Exceptions',
                'Http' => ['Controllers', 'Middleware', 'Requests', 'Resources'],
                'Jobs',
                'Listeners',
                'Mail',
                'Models',
                'Notifications',
                'Policies',
                'Providers',
                'Repositories',
                'Rules',
                'Services',
                'Traits',
                'Enums',
                'Events',
                'Helpers',
                'Livewire',
            ],
            'bootstrap',
            'config',
            'database' => ['factories', 'migrations', 'seeders'],
            'public',
            'resources' => ['views', 'lang', 'css', 'js'],
            'routes',
            'storage' => ['app', 'framework', 'logs'],
            'tests' => ['Feature', 'Unit'],
        ];

        $tree['children'][] = $this->buildDirectory($base, $directories, '');

        return [
            'folder_tree' => [
                'root' => $tree,
            ],
        ];
    }

    /**
     * @param array<string, mixed>|string $definition
     * @return array<string, mixed>
     */
    private function buildDirectory(string $basePath, array|string $definition, string $prefix): array
    {
        if (is_string($definition)) {
            $path = $prefix . '/' . $definition;
            $fullPath = $basePath . $path;

            return [
                'name' => $definition,
                'path' => ltrim($path, '/'),
                'type' => is_dir($fullPath) ? 'directory' : 'missing',
                'exists' => is_dir($fullPath),
                'children' => [],
            ];
        }

        $name = (array_keys($definition) !== []) ? array_keys($definition)[0] : '';
        $children = [];

        if ($name !== '') {
            $prefix = $prefix . '/' . $name;
        }

        $dirs = $definition[$name] ?? $definition;

        foreach ($dirs as $key => $value) {
            if (is_array($value)) {
                $children[] = $this->buildDirectory($basePath, [$key => $value], $prefix);
            } elseif (is_string($value)) {
                $children[] = $this->buildDirectory($basePath, $value, $prefix);
            } elseif (is_string($key)) {
                $children[] = $this->buildDirectory($basePath, [$key => $value], $prefix);
            } else {
                $children[] = $this->buildDirectory($basePath, $value, $prefix);
            }
        }

        $fullPath = $basePath . ($name ? '/' . $name : '');

        return [
            'name' => $name ?: basename($basePath),
            'path' => ltrim($prefix, '/'),
            'type' => 'directory',
            'exists' => is_dir($fullPath),
            'children' => $children,
        ];
    }
}