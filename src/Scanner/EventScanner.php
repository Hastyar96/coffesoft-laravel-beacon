<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans app/Events and detects events, listeners, dispatchers, and subscribers.
 */
class EventScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $events = $this->scanEvents();
        $listeners = $this->scanListeners();
        $subscribers = $this->scanSubscribers();
        $dispatchers = $this->scanDispatchers();

        return [
            'events' => [
                'count' => count($events),
                'items' => $events,
                'listeners' => $listeners,
                'subscribers' => $subscribers,
                'dispatchers' => $dispatchers,
            ],
        ];
    }

    private function scanEvents(): array
    {
        $path = app_path('Events');
        if (!is_dir($path)) return [];

        $items = [];
        foreach ($this->reader->getPhpFiles($path) as $file) {
            $contents = $this->reader->read($file->getPathname());
            $parsed = $this->parser->parse($contents);
            if ($parsed['class_name'] === null) continue;

            $items[] = [
                'name' => $parsed['class_name'],
                'namespace' => $parsed['namespace'] ?? 'App\\Events',
                'path' => $file->getRelativePathname(),
                'properties' => $parsed['properties'],
                'methods' => array_map(fn($m) => $m['name'], $parsed['methods']),
                'implements' => $parsed['interfaces'],
                'should_broadcast' => $this->implementsBroadcast($parsed['interfaces']),
                'should_queue' => $this->shouldQueue($contents),
            ];
        }

        return $items;
    }

    private function scanListeners(): array
    {
        $path = app_path('Listeners');
        if (!is_dir($path)) return [];

        $items = [];
        foreach ($this->reader->getPhpFiles($path) as $file) {
            $contents = $this->reader->read($file->getPathname());
            $parsed = $this->parser->parse($contents);
            if ($parsed['class_name'] === null) continue;

            $name = $parsed['class_name'];
            $uses = $parsed['uses'];

            // Detect which event this listener handles from handle() method signature
            $handlesEvent = null;
            foreach ($parsed['methods'] as $method) {
                if ($method['name'] === 'handle') {
                    foreach ($method['params'] as $param) {
                        if ($param['type'] && str_contains($param['type'], '\\')) {
                            $parts = explode('\\', $param['type']);
                            $handlesEvent = end($parts);
                        }
                    }
                    break;
                }
            }

            $items[] = [
                'name' => $name,
                'namespace' => $parsed['namespace'] ?? 'App\\Listeners',
                'path' => $file->getRelativePathname(),
                'handles' => $handlesEvent,
                'queued' => $this->implementsInterface($contents, 'ShouldQueue'),
                'methods' => array_map(fn($m) => $m['name'], $parsed['methods']),
            ];
        }

        return $items;
    }

    private function scanSubscribers(): array
    {
        $path = app_path('Listeners');
        if (!is_dir($path)) return [];

        $items = [];
        foreach ($this->reader->getPhpFiles($path) as $file) {
            $contents = $this->reader->read($file->getPathname());
            if (str_contains($contents, 'subscribe(')) {
                $parsed = $this->parser->parse($contents);
                if ($parsed['class_name'] === null) continue;

                $items[] = [
                    'name' => $parsed['class_name'],
                    'namespace' => $parsed['namespace'] ?? 'App\\Listeners',
                    'path' => $file->getRelativePathname(),
                    'methods' => array_map(fn($m) => $m['name'], $parsed['methods']),
                ];
            }
        }

        return $items;
    }

    private function scanDispatchers(): array
    {
        // Scan controllers and services for event dispatch calls
        $dispatchers = [];
        $paths = [
            app_path('Http/Controllers'),
            app_path('Services'),
        ];

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            foreach ($this->reader->getPhpFiles($path) as $file) {
                $contents = $this->reader->read($file->getPathname());
                $parsed = $this->parser->parse($contents);
                if ($parsed['class_name'] === null) continue;

                // Detect dispatch() calls
                $events = [];
                if (preg_match_all('/\bevent\s*\(\s*new\s+(\w+Event)/', $contents, $m)) {
                    foreach ($m[1] as $ev) {
                        if (!in_array($ev, $events)) $events[] = $ev;
                    }
                }
                if (preg_match_all('/\bdispatch\s*\(\s*new\s+(\w+Event)/', $contents, $m)) {
                    foreach ($m[1] as $ev) {
                        if (!in_array($ev, $events)) $events[] = $ev;
                    }
                }
                if (preg_match_all('/\b([\w]+Event)::dispatch/', $contents, $m)) {
                    foreach ($m[1] as $ev) {
                        if (!in_array($ev, $events)) $events[] = $ev;
                    }
                }

                if (!empty($events)) {
                    $dispatchers[] = [
                        'class' => $parsed['class_name'],
                        'namespace' => $parsed['namespace'],
                        'dispatches' => $events,
                    ];
                }
            }
        }

        return $dispatchers;
    }

    private function implementsBroadcast(array $interfaces): bool
    {
        foreach ($interfaces as $iface) {
            if (str_contains($iface, 'ShouldBroadcast') || str_contains($iface, 'ShouldBroadcastNow')) {
                return true;
            }
        }
        return false;
    }

    private function shouldQueue(string $contents): bool
    {
        return str_contains($contents, 'ShouldQueue');
    }

    private function implementsInterface(string $contents, string $interface): bool
    {
        return str_contains($contents, $interface);
    }
}