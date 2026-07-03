<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans app/Jobs and detects queued/sync jobs and dispatch locations.
 */
class JobScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $path = app_path('Jobs');
        if (!is_dir($path)) {
            return ['jobs' => ['count' => 0, 'items' => [], 'dispatchers' => []]];
        }

        $items = [];
        foreach ($this->reader->getPhpFiles($path) as $file) {
            $contents = $this->reader->read($file->getPathname());
            $parsed = $this->parser->parse($contents);
            if ($parsed['class_name'] === null) continue;

            $shouldQueue = $this->implementsInterface($contents, 'ShouldQueue');
            $interactsWithQueue = str_contains($contents, 'InteractsWithQueue');
            $queueable = str_contains($contents, 'Queueable');
            $serializesModels = str_contains($contents, 'SerializesModels');

            // Detect middleware
            $middleware = [];
            if (preg_match_all('/function\s+middleware\s*\([^)]*\)\s*(?::\s*array)?\s*\{/', $contents, $m)) {
                $middleware[] = 'custom_middleware';
            }

            // Detect tags
            $tags = [];
            if (preg_match_all('/->tag\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
                $tags = $m[1];
            }

            // Detect unique locking
            $uniqueFor = null;
            if (preg_match('/uniqueFor\s*\((\d+)\)/', $contents, $m)) {
                $uniqueFor = (int)$m[1];
            }

            $handleMethod = null;
            foreach ($parsed['methods'] as $method) {
                if ($method['name'] === 'handle') {
                    $handleMethod = $method;
                    break;
                }
            }

            $items[] = [
                'name' => $parsed['class_name'],
                'namespace' => $parsed['namespace'] ?? 'App\\Jobs',
                'path' => $file->getRelativePathname(),
                'queued' => $shouldQueue,
                'sync' => !$shouldQueue,
                'unique' => $uniqueFor !== null,
                'unique_for' => $uniqueFor,
                'tags' => $tags,
                'middleware' => $middleware,
                'interacts_with_queue' => $interactsWithQueue,
                'serializes_models' => $serializesModels,
                'handle_params' => $handleMethod ? array_map(fn($p) => $p['name'], $handleMethod['params']) : [],
                'methods' => array_map(fn($m) => $m['name'], $parsed['methods']),
            ];
        }

        // Detect dispatch locations across the project
        $dispatchers = $this->detectDispatchLocations();

        return [
            'jobs' => [
                'count' => count($items),
                'items' => $items,
                'dispatchers' => $dispatchers,
            ],
        ];
    }

    private function detectDispatchLocations(): array
    {
        $dispatchers = [];
        $paths = [
            app_path('Http/Controllers'),
            app_path('Services'),
            app_path('Console/Commands'),
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            foreach ($this->reader->getPhpFiles($path) as $file) {
                $contents = $this->reader->read($file->getPathname());
                $parsed = $this->parser->parse($contents);
                if ($parsed['class_name'] === null) continue;

                $detected = [];
                // Pattern 1: JobClass::dispatch()
                if (preg_match_all('/(\w+)::dispatch\s*\(/', $contents, $m)) {
                    foreach ($m[1] as $job) {
                        if (!in_array($job, $detected)) $detected[] = $job;
                    }
                }
                // Pattern 2: dispatch(new JobClass(...))
                if (preg_match_all('/\bdispatch\s*\(\s*new\s+(\w+)/', $contents, $m)) {
                    foreach ($m[1] as $job) {
                        if (!in_array($job, $detected)) $detected[] = $job;
                    }
                }

                if (!empty($detected)) {
                    $dispatchers[] = [
                        'class' => $parsed['class_name'],
                        'namespace' => $parsed['namespace'],
                        'path' => $file->getRelativePathname(),
                        'dispatches' => $detected,
                    ];
                }
            }
        }

        return $dispatchers;
    }

    private function implementsInterface(string $contents, string $interface): bool
    {
        return str_contains($contents, $interface);
    }
}