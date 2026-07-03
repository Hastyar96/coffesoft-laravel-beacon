<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner that detects Laravel project path structure.
 *
 * Returns common Laravel directory paths without
 * scanning models, controllers, migrations, or parsing files.
 */
class LaravelScanner implements Scanner
{
    /**
     * Scan the Laravel project and return path information.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $basePath = base_path();

        return [
            'paths' => [
                'app' => app_path(),
                'routes' => base_path('routes'),
                'config' => config_path(),
                'database' => database_path(),
                'resources' => resource_path(),
                'composer_json' => $basePath . DIRECTORY_SEPARATOR . 'composer.json',
                'package_json' => $basePath . DIRECTORY_SEPARATOR . 'package.json',
            ],
        ];
    }
}
