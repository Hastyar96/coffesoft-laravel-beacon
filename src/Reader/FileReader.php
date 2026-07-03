<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Reader;

use Illuminate\Support\Facades\File;

/**
 * Stream-based file reader for efficient source code scanning.
 * Reads files only when needed, keeps memory usage low.
 */
class FileReader
{
    /**
     * Get all PHP files from a directory recursively.
     *
     * @return array<int, \Symfony\Component\Finder\SplFileInfo>
     */
    public function getPhpFiles(string $path): array
    {
        if (! is_dir($path)) {
            return [];
        }

        $result = [];
        $files = File::allFiles($path);

        foreach ($files as $file) {
            if ($file->getExtension() === 'php') {
                $result[] = $file;
            }
        }

        return $result;
    }

    /**
     * Read file contents safely.
     */
    public function read(string $path): string
    {
        if (! file_exists($path)) {
            return '';
        }

        $contents = file_get_contents($path);

        return $contents !== false ? $contents : '';
    }

    /**
     * Extract class name from PHP contents.
     */
    public function extractClassName(string $contents): ?string
    {
        if (preg_match('/^class\s+(\w+)/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract namespace from PHP contents.
     */
    public function extractNamespace(string $contents): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Extract use statements.
     */
    public function extractUses(string $contents): array
    {
        $uses = [];
        if (preg_match_all('/^use\s+([^;]+);/m', $contents, $matches)) {
            foreach ($matches[1] as $use) {
                $uses[] = trim($use);
            }
        }
        return $uses;
    }

    /**
     * Extract public methods.
     */
    public function extractPublicMethods(string $contents): array
    {
        preg_match_all('/public\s+function\s+(\w+)\s*\(/', $contents, $matches);
        $methods = [];
        foreach ($matches[1] as $m) {
            if (! in_array($m, ['__construct', '__invoke', '__call'])) {
                $methods[] = $m;
            }
        }
        return $methods;
    }

    /**
     * Extract constructor parameters (type-hinted dependencies).
     */
    public function extractConstructorParams(string $contents): array
    {
        $params = [];

        if (preg_match('/function\s+__construct\s*\(([^)]*)\)/s', $contents, $matches)) {
            $args = explode(',', $matches[1]);
            foreach ($args as $arg) {
                $arg = trim($arg);
                if ($arg === '') continue;

                if (preg_match('/(\w+(?:\\\\\w+)*)\s+\$(\w+)/', $arg, $m)) {
                    $params[] = [
                        'type' => $m[1],
                        'name' => $m[2],
                    ];
                }
            }
        }

        return $params;
    }
}