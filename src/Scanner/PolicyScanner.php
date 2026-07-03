<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

class PolicyScanner
{
    public function __construct(private readonly FileReader $reader) {}

    public function scan(): array
    {
        $path = app_path('Policies');
        $files = $this->reader->getPhpFiles($path);
        $items = [];

        foreach ($files as $file) {
            $contents = $this->reader->read($file->getPathname());
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

            $items[] = [
                'name' => $name,
                'namespace' => $this->reader->extractNamespace($contents) ?? 'App\\Policies',
                'path' => $file->getRelativePathname(),
                'model' => $model,
                'abilities' => $methods,
            ];
        }

        return ['policies' => ['count' => count($items), 'items' => $items]];
    }
}