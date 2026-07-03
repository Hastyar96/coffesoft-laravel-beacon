<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans for Livewire components, views, properties, events, and computed values.
 */
class LivewireScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $components = $this->findComponents();

        return [
            'livewire' => [
                'present' => !empty($components),
                'components' => $components,
            ],
        ];
    }

    private function findComponents(): array
    {
        $paths = [
            app_path('Livewire'),
            app_path('Http/Livewire'),
        ];

        $components = [];

        foreach ($paths as $path) {
            if (!is_dir($path)) continue;
            foreach ($this->reader->getPhpFiles($path) as $file) {
                $contents = $file->getContents();
                $parsed = $this->parser->parse($contents);
                if ($parsed['class_name'] === null) continue;

                // Verify it extends a Livewire component
                $isLivewire = str_contains($contents, 'extends Component')
                    || str_contains($parsed['parent'] ?? '', 'Component')
                    || str_contains($contents, 'Livewire\\Component');

                if (!$isLivewire) continue;

                // Detect view template
                $view = null;
                if (preg_match('/protected\s+\$view\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
                    $view = $m[1];
                } elseif (preg_match('/public\s+function\s+render\s*\(\s*\)/', $contents)) {
                    if (preg_match('/function\s+render\s*\(\s*\)\s*\{(.*?)\}/s', $contents, $m)) {
                        if (preg_match('/->view\s*\(\s*[\'"]([^\'"]+)[\'"]/', $m[1], $sm)) {
                            $view = $sm[1];
                        } elseif (preg_match('/view\s*\(\s*[\'"]([^\'"]+)[\'"]/', $m[1], $sm)) {
                            $view = $sm[1];
                        }
                    }
                }

                // Detect events emitted and listened
                $emits = [];
                $listens = [];

                if (preg_match_all('/\$this->emit\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
                    $emits = $m[1];
                }
                if (preg_match_all('/\$this->emitUp\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
                    $emits = array_merge($emits, $m[1]);
                }

                if (preg_match('/protected\s+\$listeners\s*=\s*\[([^\]]*)\]/s', $contents, $m)) {
                    preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1], $matches);
                    $listens = $matches[1];
                }

                // Detect computed properties
                $computeds = [];
                if (preg_match_all('/public\s+function\s+get(\w+)Property\s*\(/', $contents, $m)) {
                    foreach ($m[1] as $prop) {
                        $computeds[] = lcfirst($prop);
                    }
                }

                // Detect public properties
                $publicProps = [];
                foreach ($parsed['properties'] as $prop) {
                    if ($prop['visibility'] === 'public' && !$prop['static']) {
                        $publicProps[] = $prop['name'];
                    }
                }

                $components[] = [
                    'name' => $parsed['class_name'],
                    'namespace' => $parsed['namespace'] ?? '',
                    'path' => $file->getRelativePathname(),
                    'view' => $view,
                    'properties' => $publicProps,
                    'computed' => $computeds,
                    'emits' => array_values(array_unique($emits)),
                    'listens' => $listens,
                    'methods' => array_map(fn($m) => $m['name'], $parsed['methods']),
                ];
            }
        }

        return $components;
    }
}