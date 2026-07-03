<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

/**
 * Scans queue configuration, drivers, and connection setup.
 */
class QueueScanner
{
    public function scan(): array
    {
        $config = $this->readQueueConfig();
        $failedJobs = $this->readFailedJobsConfig();
        $connections = $this->detectConnections($config);
        $horizon = $this->detectHorizon();

        return [
            'queue' => [
                'default_driver' => $config['default'] ?? null,
                'connections' => $connections,
                'failed_jobs' => $failedJobs,
                'horizon_installed' => $horizon,
            ],
        ];
    }

    private function readQueueConfig(): array
    {
        $configPath = config_path('queue.php');
        if (!file_exists($configPath)) {
            return [];
        }

        // Use Laravel's config if available, otherwise parse manually
        $contents = file_get_contents($configPath);

        $config = [];
        if (preg_match('/\'default\'\s*=>\s*env\([\'"]QUEUE_CONNECTION[\'"],\s*[\'"](\w+)[\'"]\)/', $contents, $m)) {
            $config['default'] = $m[1];
        } elseif (preg_match('/\'default\'\s*=>\s*[\'"](\w+)[\'"]/', $contents, $m)) {
            $config['default'] = $m[1];
        }

        return $config;
    }

    private function detectConnections(array $config): array
    {
        $connections = [];

        $configPath = config_path('queue.php');
        if (!file_exists($configPath)) return $connections;

        $contents = file_get_contents($configPath);

        // Detect connection types
        $types = ['sync', 'database', 'redis', 'sqs', 'beanstalkd', 'rabbitmq', 'iron', 'null'];
        foreach ($types as $type) {
            if (preg_match('/\'connection\'\s*=>\s*[\'"]' . $type . '[\'"]/', $contents)) {
                $connections[] = $type;
            } elseif (str_contains($contents, "'{$type}'")) {
                // Check if it's a connection key
                if (preg_match("/'{$type}'\s*=>\s*\[/", $contents)) {
                    $connections[] = $type;
                }
            }
        }

        return array_values(array_unique($connections));
    }

    private function readFailedJobsConfig(): array
    {
        $configPath = config_path('queue.php');
        if (!file_exists($configPath)) return [];

        $contents = file_get_contents($configPath);
        $failed = [];

        if (preg_match("/'failed'\s*=>\s*\[(.*?)\]/s", $contents, $m)) {
            $failedConfig = $m[1];
            if (preg_match("/'driver'\s*=>\s*env\([\"']FAILED_QUEUE_DRIVER[\"'],\s*[\"'](\w+)[\"']\)/", $failedConfig, $mm)) {
                $failed['driver'] = $mm[1];
            } elseif (preg_match("/'driver'\s*=>\s*[\"'](\w+)[\"']/", $failedConfig, $mm)) {
                $failed['driver'] = $mm[1];
            }
            if (preg_match("/'table'\s*=>\s*[\"'](\w+)[\"']/", $failedConfig, $mm)) {
                $failed['table'] = $mm[1];
            }
        }

        return $failed;
    }

    private function detectHorizon(): bool
    {
        $composerPath = base_path('composer.json');
        if (!file_exists($composerPath)) return false;

        $composer = json_decode(file_get_contents($composerPath), true);
        if (!$composer) return false;

        $allDeps = array_merge(
            $composer['require'] ?? [],
            $composer['require-dev'] ?? []
        );

        return isset($allDeps['laravel/horizon'])
            || isset($allDeps['laravel/nova-horizon']);
    }
}