<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

/**
 * Scans Blade templates for components, layouts, sections, and view inheritance.
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

        $files = $this->reader->getPhpFiles($viewPath);

        $layouts = [];
        $components = [];
        $views = [];

        foreach ($files as $file) {
            $contents = $file->getContents();
            $relativePath = $file->getRelativePathname();
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

            if (str_contains($viewName, 'layouts')) {
                $layouts[] = $viewData;
            } elseif (str_contains($viewName, 'components')) {
                $components[] = $viewData;
            } else {
                $views[] = $viewData;
            }
        }

        $anonymousComponents = $this->findAnonymousComponents($viewPath);

        return [
            'blade' => [
                'count' => count($files),
                'layouts' => $layouts,
                'components' => array_merge($components, $anonymousComponents),
                'views' => $views,
            ],
        ];
    }

    private function pathToViewName(string $path): string
    {
        $name = str_replace(['.blade.php', '/'], ['', '.'], $path);
        return $name;
    }

    private function extractExtends(string $contents): ?string
    {
        if (preg_match('/@extends\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractSections(string $contents): array
    {
        $sections = [];
        if (preg_match_all('/@section\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches)) {
            $sections = $matches[1];
        }
        return array_values(array_unique($sections));
    }

    private function extractIncludes(string $contents): array
    {
        $includes = [];
        $patterns = [
            '/@include\s*\(\s*[\'"]([^\'"]+)[\'"]/',
            '/@includeIf\s*\(\s*[\'"]([^\'"]+)[\'"]/',
            '/@includeWhen\s*\([^,]+,\s*[\'"]([^\'"]+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $contents, $matches)) {
                $includes = array_merge($includes, $matches[1]);
            }
        }

        return array_values(array_unique($includes));
    }

    private function extractComponents(string $contents): array
    {
        $components = [];

        // <x- components (class-based and anonymous)
        if (preg_match_all('/<x-([\w.-]+)/', $contents, $matches)) {
            foreach ($matches[1] as $comp) {
                $components[] = $comp;
            }
        }

        // @component directive
        if (preg_match_all('/@component\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches)) {
            foreach ($matches[1] as $comp) {
                $components[] = $comp;
            }
        }

        return array_values(array_unique($components));
    }

    private function extractStacks(string $contents): array
    {
        $stacks = [];
        if (preg_match_all('/@stack\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches)) {
            $stacks = $matches[1];
        }
        return array_values(array_unique($stacks));
    }

    private function extractPushes(string $contents): array
    {
        $pushes = [];
        if (preg_match_all('/@push\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches)) {
            $pushes = $matches[1];
        }
        return array_values(array_unique($pushes));
    }

    private function extractSlots(string $contents): array
    {
        $slots = [];

        // Named slots: <x-slot name="..."
        if (preg_match_all('/<x-slot\s+name\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches)) {
            $slots = array_merge($slots, $matches[1]);
        }
        // @slot directives
        if (preg_match_all('/@slot\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $matches)) {
            $slots = array_merge($slots, $matches[1]);
        }

        return array_values(array_unique($slots));
    }

    private function extractProps(string $contents): array
    {
        $props = [];

        // @props(['...'])
        if (preg_match('/@props\(\[([^\]]+)\]\)/', $contents, $m)) {
            preg_match_all('/[\'"]([^\'"]+)[\'"]/', $m[1], $matches);
            $props = $matches[1];
        }

        // {{ $... }} variables (simplistic detection)
        if (preg_match_all('/\{\{\s*\$(\w+)\s*\}\}/', $contents, $matches)) {
            $vars = array_diff($matches[1], ['slot', 'attributes']);
            $props = array_merge($props, $vars);
        }

        return array_values(array_unique($props));
    }

    private function findAnonymousComponents(string $viewPath): array
    {
        $components = [];

        // Anonymous components are in resources/views/components/
        $anonPath = $viewPath . '/components';
        if (!is_dir($anonPath)) return $components;

        $files = $this->reader->getPhpFiles($anonPath);
        foreach ($files as $file) {
            $relativePath = $file->getRelativePathname();
            $components[] = [
                'name' => $this->pathToViewName('components.' . $relativePath),
                'path' => $relativePath,
                'anonymous' => true,
                'extends' => null,
                'sections' => [],
                'includes' => $this->extractIncludes($file->getContents()),
                'components' => $this->extractComponents($file->getContents()),
            ];
        }

        return $components;
    }
}