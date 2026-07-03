<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Cache;

/**
 * Incremental scan cache for large Laravel projects.
 * Stores file hashes to detect changed files and skip unchanged ones.
 *
 * .cache/beacon/ — stored at project root to avoid cluttering storage.
 */
class ScanCache
{
    private string $cacheDir;

    private string $manifestPath;

    /** @var array<string, array<string, string>> */
    private array $manifest = [];

    public function __construct()
    {
        $this->cacheDir = base_path('.cache/beacon');
        $this->manifestPath = $this->cacheDir . '/manifest.json';
        $this->loadManifest();
    }

    /**
     * Detect which files have changed since the last scan.
     *
     * @param array<string> $files List of file paths to check
     * @return array{array<string>, array<string>} [changed_files, unchanged_files]
     */
    public function detectChanges(array $files): array
    {
        $changed = [];
        $unchanged = [];

        foreach ($files as $file) {
            if (!file_exists($file)) {
                continue;
            }

            $hash = $this->hashFile($file);
            $relativePath = $this->relativePath($file);

            if (!isset($this->manifest[$relativePath]) || $this->manifest[$relativePath] !== $hash) {
                $changed[] = $file;
            } else {
                $unchanged[] = $file;
            }
        }

        return [$changed, $unchanged];
    }

    /**
     * Record file hashes after a scan.
     *
     * @param array<string> $files List of file paths that were scanned
     */
    public function recordScan(array $files): void
    {
        foreach ($files as $file) {
            if (!file_exists($file)) continue;
            $relativePath = $this->relativePath($file);
            $this->manifest[$relativePath] = $this->hashFile($file);
        }

        $this->saveManifest();
    }

    /**
     * Get cached scan results for unchanged files.
     *
     * @param array<string> $files Unchanged files
     * @return array<string, mixed> Stored scan data for these files
     */
    public function getCachedForFiles(array $files): array
    {
        $data = [];

        foreach ($files as $file) {
            $relativePath = $this->relativePath($file);
            $cachePath = $this->cacheDir . '/files/' . $this->pathToCacheKey($relativePath) . '.json';

            if (file_exists($cachePath)) {
                $cached = json_decode(file_get_contents($cachePath), true);
                if ($cached) {
                    $data[] = $cached;
                }
            }
        }

        return $data;
    }

    /**
     * Cache scan results for a file.
     */
    public function cacheFileData(string $filePath, array $data): void
    {
        $relativePath = $this->relativePath($filePath);
        $cacheFile = $this->cacheDir . '/files/' . $this->pathToCacheKey($relativePath) . '.json';

        $dir = dirname($cacheFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($cacheFile, json_encode($data));

        // Update manifest
        $this->manifest[$relativePath] = $this->hashFile($filePath);
        $this->saveManifest();
    }

    /**
     * Get all cached file keys.
     *
     * @return array<string>
     */
    public function getCachedPaths(): array
    {
        return array_keys($this->manifest);
    }

    /**
     * Check if this is the first scan (no cache exists).
     */
    public function isFirstScan(): bool
    {
        return empty($this->manifest);
    }

    /**
     * Get cache statistics.
     *
     * @return array<string, mixed>
     */
    public function getStats(): array
    {
        $cacheSize = 0;
        $cacheFiles = 0;

        $filesDir = $this->cacheDir . '/files';
        if (is_dir($filesDir)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($filesDir));
            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $cacheSize += $file->getSize();
                    $cacheFiles++;
                }
            }
        }

        return [
            'cached_files' => count($this->manifest),
            'cache_file_count' => $cacheFiles,
            'cache_size_bytes' => $cacheSize,
            'cache_dir' => $this->cacheDir,
        ];
    }

    /**
     * Clear the entire cache.
     */
    public function clear(): void
    {
        if (is_dir($this->cacheDir)) {
            $this->removeDirectory($this->cacheDir);
        }
        $this->manifest = [];
    }

    private function loadManifest(): void
    {
        if (file_exists($this->manifestPath)) {
            $data = json_decode(file_get_contents($this->manifestPath), true);
            if (is_array($data)) {
                $this->manifest = $data;
            }
        }
    }

    private function saveManifest(): void
    {
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
        file_put_contents($this->manifestPath, json_encode($this->manifest, JSON_PRETTY_PRINT));
    }

    private function hashFile(string $path): string
    {
        return md5_file($path) ?: '';
    }

    private function relativePath(string $path): string
    {
        $base = base_path();
        if (str_starts_with($path, $base)) {
            return substr($path, strlen($base) + 1);
        }
        return $path;
    }

    private function pathToCacheKey(string $path): string
    {
        return str_replace(['/', '\\', '.'], ['_', '_', '_'], $path);
    }

    private function removeDirectory(string $dir): void
    {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}