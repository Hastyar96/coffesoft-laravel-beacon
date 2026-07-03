<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Providers;

use Coffessoft\LaravelBeacon\Commands\ContextCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

/**
 * Laravel Beacon service provider.
 */
class BeaconServiceProvider extends PackageServiceProvider
{
    /**
     * Configure the package.
     */
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-beacon')
            ->hasConfigFile('beacon')
            ->hasCommand(ContextCommand::class);
    }
}