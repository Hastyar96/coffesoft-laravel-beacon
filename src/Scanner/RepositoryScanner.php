<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

/**
 * Scans Repository classes.
 * ONLY uses static source code parsing - no class instantiation, no autoloading.
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
            $contents = $this->reader->read($file['pathname']);
            if ($contents === '') continue;
            $name = $this->reader->extractClassName($contents);
            if ($name === null) continue;

            $uses = $this->reader->extractUses($contents);
            $methods = $this->reader->extractPublicMethods($contents);

            $model = '';
            foreach ($uses as $u) {
                if (str_contains($u, '\\Models\\')) {
                    $parts = explode('\\', $u);
                    $model = end($parts);
                    break;
                }
            }

            // Detect if this is an interface or class
            $type = 'implementation';
            if (preg_match('/^interface\s+\w+/m', $contents)) {
                $type = 'interface';
            } elseif (preg_match('/^abstract\s+class\s+\w+/m', $contents)) {
                $type = 'abstract';
            }

            $items[] = [
                'name' => $name,
                'namespace' => $this->reader->extractNamespace($contents) ?? 'App\\Repositories',
                'path' => $file['relative_path'],
                'type' => $type,
                'model' => $model,
                'methods' => $methods,
                'method_count' => count($methods),
            ];
        }

        return ['repositories' => ['count' => count($items), 'items' => $items]];
    }
}