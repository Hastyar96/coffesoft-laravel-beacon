<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

/**
 * Generates rich project statistics from all scanned data.
 */
class StatisticsScanner
{
    public function __construct(
        private readonly FileReader $reader,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        return [
            'statistics' => $this->gatherStatistics(),
        ];
    }

    /**
     * Gather comprehensive project statistics.
     *
     * @return array<string, mixed>
     */
    private function gatherStatistics(): array
    {
        $stats = [];

        // File counts by type
        $stats['total_php_files'] = $this->countPhpFiles(app_path());
        $stats['total_blade_files'] = $this->countBladeFiles(resource_path('views'));

        // Model statistics
        $stats['models'] = $this->getModelStats();
        $stats['controllers'] = $this->getControllerStats();
        $stats['services'] = $this->getServiceStats();
        $stats['repositories'] = $this->getRepositoryStats();
        $stats['requests'] = $this->getRequestStats();
        $stats['policies'] = $this->getPolicyStats();
        $stats['routes'] = $this->getRouteStats($this->reader);
        $stats['views'] = $this->getViewStats();
        $stats['blade_components'] = $this->getBladeComponentStats();
        $stats['livewire_components'] = $this->getLivewireComponentStats();
        $stats['jobs'] = $this->getJobStats();
        $stats['events'] = $this->getEventStats();
        $stats['listeners'] = $this->getListenerStats();
        $stats['notifications'] = $this->getNotificationStats();
        $stats['commands'] = $this->getCommandStats();
        $stats['traits'] = $this->getTraitStats();
        $stats['enums'] = $this->getEnumStats();
        $stats['helpers'] = $this->getHelperStats();
        $stats['packages'] = $this->getPackageStats();
        $stats['database_tables'] = $this->getDatabaseStats();

        // Average sizes
        $stats['average_controller_methods'] = $this->avgControllerMethods();
        $stats['average_model_methods'] = $this->avgModelMethods();

        // Largest classes and files
        $stats['largest_classes'] = $this->largestClasses();

        return $stats;
    }

    private function countPhpFiles(string $path): int
    {
        if (!is_dir($path)) return 0;
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if ($file->getExtension() === 'php') $count++;
        }
        return $count;
    }

    private function countBladeFiles(?string $path): int
    {
        if (!$path || !is_dir($path)) return 0;
        $count = 0;
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
        foreach ($iterator as $file) {
            if (str_ends_with($file->getFilename(), '.blade.php')) $count++;
        }
        return $count;
    }

    private function getModelStats(): int
    {
        $path = app_path('Models');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getControllerStats(): int
    {
        $path = app_path('Http/Controllers');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getServiceStats(): int
    {
        $path = app_path('Services');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getRepositoryStats(): int
    {
        $path = app_path('Repositories');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getRequestStats(): int
    {
        $path = app_path('Http/Requests');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getPolicyStats(): int
    {
        $path = app_path('Policies');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    /**
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    private function getRouteStats(FileReader $reader): int
    {
        $routePath = base_path('routes');
        if (!is_dir($routePath)) return 0;
        $count = 0;
        foreach (new \DirectoryIterator($routePath) as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') $count++;
        }
        return $count;
    }

    private function getViewStats(): int
    {
        return $this->countBladeFiles(resource_path('views'));
    }

    private function getBladeComponentStats(): int
    {
        $path = app_path('View/Components');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getLivewireComponentStats(): int
    {
        $paths = [app_path('Livewire'), app_path('Http/Livewire')];
        $count = 0;
        foreach ($paths as $path) {
            if (is_dir($path)) $count += count($this->reader->getPhpFiles($path));
        }
        return $count;
    }

    private function getJobStats(): int
    {
        $path = app_path('Jobs');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getEventStats(): int
    {
        $path = app_path('Events');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getListenerStats(): int
    {
        $path = app_path('Listeners');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getNotificationStats(): int
    {
        $path = app_path('Notifications');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getCommandStats(): int
    {
        $path = app_path('Console/Commands');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function getTraitStats(): int
    {
        $paths = [app_path('Traits'), app_path('Concerns')];
        $count = 0;
        foreach ($paths as $path) {
            if (is_dir($path)) $count += count($this->reader->getPhpFiles($path));
        }
        return $count;
    }

    private function getEnumStats(): int
    {
        $paths = [app_path('Enums'), app_path('Enum')];
        $count = 0;
        foreach ($paths as $path) {
            if (is_dir($path)) $count += count($this->reader->getPhpFiles($path));
        }
        return $count;
    }

    private function getHelperStats(): int
    {
        $path = app_path('Helpers');
        if (!is_dir($path)) return 0;
        $files = $this->reader->getPhpFiles($path);
        // Filter for files that define functions, not classes
        return count($files);
    }

    private function getPackageStats(): int
    {
        $composerPath = base_path('composer.json');
        if (!file_exists($composerPath)) return 0;
        $composer = json_decode(file_get_contents($composerPath), true);
        if (!$composer) return 0;
        return count($composer['require'] ?? []) + count($composer['require-dev'] ?? []) - 1; // -1 for php
    }

    private function getDatabaseStats(): int
    {
        $path = database_path('migrations');
        if (!is_dir($path)) return 0;
        return count($this->reader->getPhpFiles($path));
    }

    private function avgControllerMethods(): float
    {
        $ctrlPath = app_path('Http/Controllers');
        if (!is_dir($ctrlPath)) return 0;

        $totalMethods = 0;
        $totalFiles = 0;

        foreach ($this->reader->getPhpFiles($ctrlPath) as $file) {
            $contents = $file->getContents();
            preg_match_all('/public\s+function\s+(\w+)\s*\(/', $contents, $matches);
            $methods = array_filter($matches[1], fn($m) => !in_array($m, ['__construct']));
            $totalMethods += count($methods);
            $totalFiles++;
        }

        return $totalFiles > 0 ? round($totalMethods / $totalFiles, 1) : 0;
    }

    private function avgModelMethods(): float
    {
        $modelPath = app_path('Models');
        if (!is_dir($modelPath)) return 0;

        $totalMethods = 0;
        $totalFiles = 0;

        foreach ($this->reader->getPhpFiles($modelPath) as $file) {
            $contents = $file->getContents();
            preg_match_all('/public\s+function\s+(\w+)\s*\(/', $contents, $matches);
            $methods = array_filter($matches[1], fn($m) => !in_array($m, ['__construct']));
            $totalMethods += count($methods);
            $totalFiles++;
        }

        return $totalFiles > 0 ? round($totalMethods / $totalFiles, 1) : 0;
    }

    private function largestClasses(): array
    {
        $classes = [];
        $scanDirs = [
            app_path('Models'),
            app_path('Http/Controllers'),
            app_path('Services'),
        ];

        foreach ($scanDirs as $dir) {
            if (!is_dir($dir)) continue;
            foreach ($this->reader->getPhpFiles($dir) as $file) {
                $contents = $file->getContents();
                $lines = substr_count($contents, "\n") + 1;
                if ($lines > 100) {
                    $classes[] = [
                        'file' => $file->getRelativePathname(),
                        'lines' => $lines,
                    ];
                }
            }
        }

        usort($classes, fn($a, $b) => $b['lines'] <=> $a['lines']);
        return array_slice($classes, 0, 10);
    }
}