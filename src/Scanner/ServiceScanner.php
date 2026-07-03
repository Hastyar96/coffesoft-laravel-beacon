<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

/**
 * Scans app/Services and extracts service metadata.
 */
class ServiceScanner
{
    public function __construct(private readonly FileReader $reader) {}

    public function scan(): array
    {
        $path = app_path('Services');
        $files = $this->reader->getPhpFiles($path);
        $items = [];

        foreach ($files as $file) {
            $contents = $this->reader->read($file->getPathname());
            $name = $this->reader->extractClassName($contents);
            if ($name === null) continue;

            $deps = $this->reader->extractConstructorParams($contents);
            $methods = $this->reader->extractPublicMethods($contents);
            $uses = $this->reader->extractUses($contents);

            $items[] = [
                'name' => $name,
                'namespace' => $this->reader->extractNamespace($contents) ?? 'App\\Services',
                'path' => $file->getRelativePathname(),
                'dependencies' => $deps,
                'methods' => $methods,
                'referenced_models' => $this->filterReferences($uses, 'Models'),
                'referenced_repositories' => $this->filterReferences($uses, 'Repositories'),
                'referenced_jobs' => $this->filterReferences($uses, 'Jobs'),
                'referenced_events' => $this->filterReferences($uses, 'Events'),
                'referenced_notifications' => $this->filterReferences($uses, 'Notifications'),
            ];
        }

        return ['services' => ['count' => count($items), 'items' => $items]];
    }

    private function filterReferences(array $uses, string $domain): array
    {
        return array_values(array_filter($uses, fn($u) => str_contains($u, "\\{$domain}\\")));
    }
}