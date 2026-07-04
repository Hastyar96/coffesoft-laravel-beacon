<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Service Scanner
 *
 * Detects services by scanning source files only.
 * No application class instantiation, no autoloading.
 */
class ServiceScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $paths = $this->getServicePaths();
        $items = [];
        $allFiles = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            $files = $this->reader->getPhpFiles($path);
            $allFiles[$path] = $files;

            foreach ($files as $file) {
                $contents = $this->reader->read($file['pathname']);
                if ($contents === '') continue;
                $parsed = $this->parser->parse($contents);
                $name = $parsed['class_name'] ?? $this->reader->extractClassName($contents);
                if ($name === null) continue;

                $ns = $parsed['namespace'] ?? $this->reader->extractNamespace($contents) ?? $this->inferNamespace($path);
                $uses = $parsed['uses'] ?: $this->reader->extractUses($contents);
                $methods = $parsed['methods'] ?: [];
                $methodNames = array_map(fn($m) => $m['name'], $methods);
                $deps = [];

                foreach ($methods as $m) {
                    if ($m['name'] === '__construct') {
                        $deps = $m['params'];
                        break;
                    }
                }

                if (empty($deps)) {
                    $deps = $this->reader->extractConstructorParams($contents);
                }

                $responsibilities = $this->detectResponsibilities($methodNames);
                $type = $this->detectServiceType($path);

                $items[] = [
                    'name' => $name,
                    'namespace' => $ns,
                    'path' => $file['relative_path'],
                    'type' => $type,
                    'dependencies' => $deps,
                    'methods' => $methodNames,
                    'method_count' => count($methodNames),
                    'referenced_models' => $this->filterReferences($uses, 'Models'),
                    'referenced_repositories' => $this->filterReferences($uses, 'Repositories'),
                    'referenced_jobs' => $this->filterReferences($uses, 'Jobs'),
                    'referenced_events' => $this->filterReferences($uses, 'Events'),
                    'referenced_notifications' => $this->filterReferences($uses, 'Notifications'),
                    'responsibilities' => $responsibilities,
                    'traits_used' => $parsed['traits'] ?? [],
                    'interfaces_implemented' => $parsed['interfaces'] ?? [],
                    'extended_class' => $parsed['parent'],
                    'line_count' => $parsed['line_count'] ?? 0,
                    'confidence' => 85,
                ];
            }
        }

        return [
            'services' => [
                'count' => count($items),
                'items' => $items,
                'locations' => array_keys($allFiles),
                'confidence' => 85,
            ],
        ];
    }

    private function getServicePaths(): array
    {
        $paths = [];

        $paths[] = app_path('Services');
        $paths[] = app_path('Actions');
        $paths[] = app_path('UseCases');
        $paths[] = app_path('Use Cases');
        $paths[] = app_path('Application/Services');
        $paths[] = app_path('App/Application/Services');

        $domainPath = app_path('../domain');
        if (is_dir($domainPath)) {
            $iterator = new \DirectoryIterator($domainPath);
            foreach ($iterator as $dir) {
                if ($dir->isDir() && !$dir->isDot()) {
                    $svcPath = $dir->getPathname() . '/Services';
                    if (is_dir($svcPath)) $paths[] = $svcPath;
                }
            }
        }

        $modulesPath = app_path('../Modules');
        if (is_dir($modulesPath)) {
            $iterator = new \DirectoryIterator($modulesPath);
            foreach ($iterator as $dir) {
                if ($dir->isDir() && !$dir->isDot()) {
                    $svcPath = $dir->getPathname() . '/Services';
                    if (is_dir($svcPath)) $paths[] = $svcPath;
                }
            }
        }

        $modulesPath = base_path('Modules');
        if (is_dir($modulesPath)) {
            $iterator = new \DirectoryIterator($modulesPath);
            foreach ($iterator as $dir) {
                if ($dir->isDir() && !$dir->isDot()) {
                    $svcPath = $dir->getPathname() . '/Services';
                    if (is_dir($svcPath)) $paths[] = $svcPath;
                }
            }
        }

        return $paths;
    }

    private function detectServiceType(string $path): string
    {
        if (str_contains($path, '/Services')) return 'service';
        if (str_contains($path, '/Actions')) return 'action';
        if (str_contains($path, '/UseCases') || str_contains($path, '/Use Cases')) return 'use_case';
        if (str_contains($path, '/Application/Services')) return 'application_service';
        if (str_contains($path, '/Domain/')) return 'domain_service';
        if (str_contains($path, '/Modules/')) return 'module_service';
        return 'service';
    }

    private function inferNamespace(string $path): string
    {
        $base = base_path();
        $relative = str_replace($base, '', $path);
        $relative = ltrim($relative, '/');
        $parts = explode('/', $relative);

        $ns = [];
        foreach ($parts as $part) {
            if (in_array($part, ['app', 'src', 'Modules', 'domain'])) {
                if ($part === 'app') $ns[] = 'App';
                elseif ($part === 'Modules') $ns[] = 'Modules';
                elseif ($part === 'domain') $ns[] = 'Domain';
                else $ns[] = ucfirst($part);
            } elseif ($part !== 'Services' && $part !== 'Actions' && $part !== 'UseCases') {
                $ns[] = $part;
            }
        }
        $ns[] = 'Services';

        return implode('\\', $ns);
    }

    private function detectResponsibilities(array $methods): array
    {
        $responsibilities = [];
        $keywords = [
            'create' => ['create', 'store', 'add', 'register', 'new'],
            'read' => ['find', 'get', 'list', 'show', 'search', 'fetch'],
            'update' => ['update', 'edit', 'modify', 'change', 'set'],
            'delete' => ['delete', 'remove', 'destroy', 'clear'],
            'validate' => ['validate', 'check', 'verify', 'ensure'],
            'calculate' => ['calculate', 'compute', 'estimate', 'total'],
            'process' => ['process', 'handle', 'execute', 'run', 'perform'],
            'export' => ['export', 'import', 'generate', 'download', 'upload'],
            'notify' => ['notify', 'send', 'email', 'inform'],
        ];

        foreach ($methods as $method) {
            foreach ($keywords as $responsibility => $triggers) {
                foreach ($triggers as $trigger) {
                    if (str_starts_with($method, $trigger)) {
                        $responsibilities[$responsibility] = true;
                        break 2;
                    }
                }
            }
        }

        return array_keys($responsibilities);
    }

    private function filterReferences(array $uses, string $domain): array
    {
        return array_values(array_filter($uses, fn($u) => str_contains($u, "\\{$domain}\\")));
    }
}