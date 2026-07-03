<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans app/Notifications and detects notification channels and structure.
 */
class NotificationScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $path = app_path('Notifications');
        if (!is_dir($path)) {
            return ['notifications' => ['count' => 0, 'items' => []]];
        }

        $items = [];
        foreach ($this->reader->getPhpFiles($path) as $file) {
            $contents = $this->reader->read($file->getPathname());
            $parsed = $this->parser->parse($contents);
            if ($parsed['class_name'] === null) continue;

            $channels = $this->detectChannels($contents);
            $methods = array_map(fn($m) => $m['name'], $parsed['methods']);

            $items[] = [
                'name' => $parsed['class_name'],
                'namespace' => $parsed['namespace'] ?? 'App\\Notifications',
                'path' => $file->getRelativePathname(),
                'channels' => $channels,
                'methods' => $methods,
                'uses' => $parsed['uses'],
                'has_mail' => in_array('mail', $channels),
                'has_database' => in_array('database', $channels),
                'has_broadcast' => in_array('broadcast', $channels),
                'has_sms' => in_array('nexmo', $channels) || in_array('vonage', $channels),
                'has_slack' => in_array('slack', $channels),
            ];
        }

        return [
            'notifications' => [
                'count' => count($items),
                'items' => $items,
            ],
        ];
    }

    private function detectChannels(string $contents): array
    {
        $channels = [];

        // Detect via method names
        if (str_contains($contents, 'function via')) {
            if (preg_match('/function\s+via\s*\([^)]*\)\s*\{(.*?)\}/s', $contents, $m)) {
                $body = $m[1];
                if (str_contains($body, 'mail')) $channels[] = 'mail';
                if (str_contains($body, 'database')) $channels[] = 'database';
                if (str_contains($body, 'broadcast')) $channels[] = 'broadcast';
                if (str_contains($body, 'nexmo') || str_contains($body, 'vonage')) $channels[] = 'nexmo';
                if (str_contains($body, 'slack')) $channels[] = 'slack';
            }
        }

        // Detect via method presence
        if (str_contains($contents, 'function toMail')) $channels[] = 'mail';
        if (str_contains($contents, 'function toArray')) $channels[] = 'database';
        if (str_contains($contents, 'function toBroadcast')) $channels[] = 'broadcast';
        if (str_contains($contents, 'function toNexmo') || str_contains($contents, 'function toVonage')) $channels[] = 'nexmo';
        if (str_contains($contents, 'function toSlack')) $channels[] = 'slack';

        return array_values(array_unique($channels));
    }
}