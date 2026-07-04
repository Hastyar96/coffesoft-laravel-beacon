<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

/**
 * BladeScanner — scans Blade templates for layout inheritance,
 * components, sections, and view hierarchy.
 *
 * ONLY uses static source code parsing - no class instantiation, no autoloading.
 */
class BladeScanner
{
    public function __construct(
        private readonly FileReader $reader,
    ) {}

    public function scan(): array
    {
        $viewPath = resource_path('views');
        if (!is_dir($viewPath)) {
            return ['blade' => ['count' => 0, 'layouts' => [], 'components' => [], 'views' => []]];
        }

        $files = $this->getBladeFiles($viewPath);

        $layouts = [];
        $components = [];
        $views = [];

        foreach ($files as $file) {
            $contents = $this->reader->read($file['pathname']);
            if ($contents === '') continue;
            $relativePath = $file['relative_path'];
            $viewName = $this->pathToViewName($relativePath);

            $viewData = [
                'name' => $viewName,
                'path' => $relativePath,
                'extends' => $this->extractExtends($contents),
                'sections' => $this->extractSections($contents),
                'includes' => $this->extractIncludes($contents),
                'components' => $this->extractComponents($contents),
                'stacks' => $this->extractStacks($contents),
                'pushes' => $this->extractPushes($contents),
                'slots' => $this->extractSlots($contents),
                'props' => $this->extractProps($contents),
            ];

            $views[] = $viewData;

            if (!empty($viewData['extends'])) {
                $layouts[$viewData['extends']] = true;
            }
        }

        return [
            'blade' => [
                'count' => count($views),
                'layouts' => array_keys($layouts),
                'components' => $components,
                'views' => $views,
            ],
        ];
    }

    private function getBladeFiles(string $path): array
    {
        $result = [];
        $basePath = rtrim(realpath($path), '/');
        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'blade.php') {
                    $pathname = $file->getPathname();
                    $relativePath = str_replace($basePath . '/', '', $pathname);
                    $result[] = [
                        'pathname' => $pathname,
                        'relative_path' => $relativePath,
                        'filename' => $file->getFilename(),
                    ];
                }
            }
        } catch (\Throwable) {
            return [];
        }
        return $result;
    }

    private function pathToViewName(string $path): string
    {
        $name = preg_replace('/\.blade\.php$/', '', $path);
        $name = str_replace('/', '.', $name);
        $name = str_replace('\\', '.', $name);
        return $name;
    }

    private function extractExtends(string $contents): ?string
    {
        if (preg_match('/@extends\([\'"]([^\'"]+)[\'"]\)/', $contents, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractSections(string $contents): array
    {
        $sections = [];
        if (preg_match_all('/@section\([\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            $sections = array_unique($m[1]);
        }
        return array_values($sections);
    }

    private function extractIncludes(string $contents): array
    {
        $includes = [];
        if (preg_match_all('/@include(?:If|When|Unless)?\([\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            $includes = array_unique($m[1]);
        }
        if (preg_match_all('/@each\([\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            $includes = array_merge($includes, $m[1]);
        }
        return array_values(array_unique($includes));
    }

    private function extractComponents(string $contents): array
    {
        $components = [];

        // <x- prefix components
        if (preg_match_all('/<x-([a-zA-Z0-9_.-]+)/', $contents, $m)) {
            $components = array_unique($m[1]);
        }

        // @component directive
        if (preg_match_all('/@component\([\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            $components = array_merge($components, $m[1]);
        }

        // @livewire directive
        if (preg_match_all('/@livewire\([\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            $components = array_merge($components, $m[1]);
        }

        return array_values(array_unique($components));
    }

    private function extractStacks(string $contents): array
    {
        $stacks = [];
        if (preg_match_all('/@stack\([\'"]([^\'"]+)[\'"]\)/', $contents, $m)) {
            $stacks = array_unique($m[1]);
        }
        return array_values($stacks);
    }

    private function extractPushes(string $contents): array
    {
        $pushes = [];
        if (preg_match_all('/@push\([\'"]([^\'"]+)[\'"]\)/', $contents, $m)) {
            $pushes = array_unique($m[1]);
        }
        return array_values($pushes);
    }

    private function extractSlots(string $contents): array
    {
        $slots = [];
        if (preg_match_all('/@slot\([\'"]([^\'"]+)[\'"]\)/', $contents, $m)) {
            $slots = array_unique($m[1]);
        }
        return array_values($slots);
    }

    private function extractProps(string $contents): array
    {
        $props = [];
        // Extract from @props directive
        if (preg_match_all('/@props\(\[([^\]]*)\]\)/', $contents, $m)) {
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1], $p);
            $props = $p[1] ?? [];
        }
        // Extract from $attributes
        if (preg_match_all('/\$([a-z_]+)\b(?=\s*[=)])/i', $contents, $m)) {
            $props = array_merge($props, $m[1]);
        }
        return array_values(array_unique($props));
    }
}