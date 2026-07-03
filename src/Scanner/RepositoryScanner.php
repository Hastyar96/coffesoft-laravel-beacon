<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

/**
 * Scans app/Repositories and extracts repository pattern metadata.
 */
class RepositoryScanner
{
    public function __construct(private readonly FileReader $reader) {}

    public function scan(): array
    {
        $path = app_path('Repositories');
        $files = $this->reader->getPhpFiles($path);
        $items = [];

        foreach ($files as $file) {
            $contents = $this->reader->read($file->getPathname());
            $name = $this->reader->extractClassName($contents);
            if ($name === null) continue;

            $ns = $this->reader->extractNamespace($contents) ?? 'App\\Repositories';
            $methods = $this->reader->extractPublicMethods($contents);
            $uses = $this->reader->extractUses($contents);
            $deps = $this->reader->extractConstructorParams($contents);
            $isInterface = str_contains($file->getFilename(), 'Interface');

            $items[] = [
                'name' => $name,
                'namespace' => $ns,
                'path' => $file->getRelativePathname(),
                'type' => $isInterface ? 'interface' : 'implementation',
                'methods' => $methods,
                'dependencies' => $deps,
                'referenced_models' => array_values(array_filter($uses, fn($u) => str_contains($u, '\\Models\\'))),
            ];
        }

        return ['repositories' => ['count' => count($items), 'items' => $items]];
    }
}