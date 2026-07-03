<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

/**
 * Scans storage configuration, disks, public storage, S3, and upload configurations.
 */
class StorageScanner
{
    public function scan(): array
    {
        $disks = $this->detectDisks();
        $uploads = $this->detectUploadPaths();
        $publicLinks = $this->detectPublicLinks();

        return [
            'storage' => [
                'disks' => $disks,
                'upload_paths' => $uploads,
                'public_links' => $publicLinks,
            ],
        ];
    }

    private function detectDisks(): array
    {
        $disks = [];
        $configPath = config_path('filesystems.php');
        if (!file_exists($configPath)) return $disks;

        $contents = file_get_contents($configPath);

        // Extract disk names and their drivers
        if (preg_match_all("/'(\w+)'\s*=>\s*\[\s*'driver'\s*=>\s*'(\w+)'/s", $contents, $matches)) {
            foreach ($matches[1] as $i => $name) {
                $disks[] = [
                    'name' => $name,
                    'driver' => $matches[2][$i],
                ];
            }
        }

        return $disks;
    }

    private function detectUploadPaths(): array
    {
        $uploads = [];

        // Scan for common upload-related code patterns
        $searchPaths = [
            app_path('Http/Controllers'),
            app_path('Services'),
            app_path('Models'),
        ];

        foreach ($searchPaths as $path) {
            if (!is_dir($path)) continue;

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path)
            );

            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') continue;

                $contents = file_get_contents($file->getPathname());
                $relativePath = str_replace(base_path() . '/', '', $file->getPathname());

                // Detect storage/upload operations
                $patterns = [
                    'store' => preg_match('/->store\s*\(/', $contents),
                    'storeAs' => preg_match('/->storeAs\s*\(/', $contents),
                    'putFile' => preg_match('/->putFile\s*\(/', $contents),
                    'putFileAs' => preg_match('/->putFileAs\s*\(/', $contents),
                    'move' => preg_match('/Storage::move\s*\(/', $contents) || preg_match('/\bmove\s*\(/', $contents),
                    'disk' => preg_match('/Storage::disk\s*\(/', $contents),
                ];

                $activePatterns = array_filter($patterns);
                if (!empty($activePatterns)) {
                    $uploads[] = [
                        'file' => $relativePath,
                        'operations' => array_keys($activePatterns),
                    ];
                }
            }
        }

        return $uploads;
    }

    private function detectPublicLinks(): array
    {
        $links = [];

        // Check if public/storage exists and is a symlink
        $publicStorage = public_path('storage');
        if (file_exists($publicStorage) && is_link($publicStorage)) {
            $target = readlink($publicStorage);
            $links[] = [
                'from' => 'public/storage',
                'to' => str_replace(base_path() . '/', '', $target),
            ];
        }

        // Check for any custom symlinks in public directory
        $publicDir = public_path();
        if (is_dir($publicDir)) {
            $iterator = new \FilesystemIterator($publicDir);
            foreach ($iterator as $file) {
                if ($file->isLink()) {
                    $linkPath = 'public/' . $file->getFilename();
                    $target = readlink($file->getPathname());
                    $links[] = [
                        'from' => $linkPath,
                        'to' => str_replace(base_path() . '/', '', $target),
                    ];
                }
            }
        }

        return $links;
    }
}