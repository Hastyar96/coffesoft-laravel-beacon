<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Scanner that discovers controllers in app/Http/Controllers.
 *
 * Recursively scans the directory and extracts basic
 * class metadata without using reflection.
 */
class ControllerScanner implements Scanner
{
    /**
     * Scan app/Http/Controllers and return controller metadata.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $path = app_path('Http/Controllers');

        if (! is_dir($path)) {
            return [
                'controllers' => [
                    'count' => 0,
                    'items' => [],
                ],
            ];
        }

        $files = File::allFiles($path);

        $items = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $contents = $file->getContents();

            $name = $this->extractClassName($contents);
            $namespace = $this->extractNamespace($contents);

            if ($name === null) {
                continue;
            }

            $items[] = [
                'name' => $name,
                'namespace' => $namespace ?? $this->inferNamespace($file),
                'path' => $file->getRelativePathname(),
            ];
        }

        return [
            'controllers' => [
                'count' => count($items),
                'items' => $items,
            ],
        ];
    }

    /**
     * Extract the class name from PHP file contents.
     */
    private function extractClassName(string $contents): ?string
    {
        if (preg_match('/^class\s+(\w+)/m', $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract the namespace from PHP file contents.
     */
    private function extractNamespace(string $contents): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Infer the namespace based on the relative file path.
     */
    private function inferNamespace(SplFileInfo $file): string
    {
        $relative = $file->getRelativePath();

        if ($relative === '') {
            return 'App\\Http\\Controllers';
        }

        return 'App\\Http\\Controllers\\' . str_replace('/', '\\', $relative);
    }
}