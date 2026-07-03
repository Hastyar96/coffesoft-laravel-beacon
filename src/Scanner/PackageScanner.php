<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

/**
 * Reads composer.json and generates comprehensive package metadata.
 */
class PackageScanner
{
    public function scan(): array
    {
        $composerPath = base_path('composer.json');
        if (!file_exists($composerPath)) {
            return ['packages' => ['count' => 0, 'items' => []]];
        }

        $composer = json_decode(file_get_contents($composerPath), true);
        if (!$composer) {
            return ['packages' => ['count' => 0, 'items' => []]];
        }

        $require = $composer['require'] ?? [];
        $requireDev = $composer['require-dev'] ?? [];
        $allPackages = array_merge($require, $requireDev);

        $items = [];
        foreach ($allPackages as $name => $version) {
            // Skip PHP itself
            if ($name === 'php') continue;

            // Categorize packages
            $category = $this->categorizePackage($name);
            $isLaravelPackage = $this->isLaravelPackage($name);
            $isDevPackage = isset($requireDev[$name]);

            $items[] = [
                'name' => $name,
                'version' => $version,
                'category' => $category,
                'laravel_package' => $isLaravelPackage,
                'development_only' => $isDevPackage,
                'purpose' => $this->getPackagePurpose($name, $category),
            ];
        }

        // Sort by category then name
        usort($items, fn($a, $b) => [$a['category'], $a['name']] <=> [$b['category'], $b['name']]);

        return [
            'packages' => [
                'count' => count($items),
                'items' => $items,
            ],
        ];
    }

    private function categorizePackage(string $name): string
    {
        if ($name === 'laravel/framework' || $name === 'laravel/lumen-framework') {
            return 'framework';
        }

        // Laravel first-party
        if (str_starts_with($name, 'laravel/')) {
            $subPackages = [
                'breeze' => 'authentication',
                'cashier' => 'billing',
                'dusk' => 'testing',
                'envoy' => 'devops',
                'folio' => 'routing',
                'fortify' => 'authentication',
                'horizon' => 'queue',
                'jetstream' => 'authentication',
                'nova' => 'admin',
                'octane' => 'performance',
                'pennant' => 'feature_flags',
                'pint' => 'development',
                'prompts' => 'cli',
                'reverb' => 'websocket',
                'sage' => 'development',
                'sanctum' => 'authentication',
                'scout' => 'search',
                'serializable-closure' => 'utilities',
                'socialite' => 'authentication',
                'telescope' => 'debugging',
                'valet' => 'development',
                'vapor' => 'deployment',
            ];

            $shortName = str_replace('laravel/', '', $name);
            return $subPackages[$shortName] ?? 'laravel_package';
        }

        // Spatie packages
        if (str_starts_with($name, 'spatie/')) {
            return 'spatie';
        }

        // Testing
        if (str_starts_with($name, 'phpunit/') || str_starts_with($name, 'mockery/') ||
            str_starts_with($name, 'orchestra/') || str_starts_with($name, 'fakerphp/')) {
            return 'testing';
        }

        // Tools and utilities
        if (str_starts_with($name, 'barryvdh/') || str_starts_with($name, 'nunomaduro/') ||
            str_starts_with($name, 'beyondcode/') || str_starts_with($name, 'thedevdojo/')) {
            return 'development_tools';
        }

        // Debug
        if (str_starts_with($name, 'sentry/') || str_starts_with($name, 'bugsnag/') ||
            str_starts_with($name, 'flare/') || str_starts_with($name, 'filp/')) {
            return 'debugging';
        }

        // Database
        if (str_contains($name, 'doctrine') || str_contains($name, 'mongodb') ||
            str_contains($name, 'pgsql') || str_contains($name, 'mysql') ||
            $name === 'guzzlehttp/guzzle') {
            return 'database_networking';
        }

        // UI/Theme
        if ($name === 'laravel-frontend-presets' || str_starts_with($name, 'bootstrap/') ||
            $name === 'alpinejs/alpine' || str_starts_with($name, 'tailwindcss/')) {
            return 'ui_frontend';
        }

        // API
        if (str_contains($name, 'graphql') || $name === 'tymon/jwt-auth' ||
            $name === 'php-open-source-saver/jwt-auth' || str_contains($name, 'api')) {
            return 'api';
        }

        // Admin Panels
        if (str_contains($name, 'filament') || str_contains($name, 'backpack') ||
            str_contains($name, 'voyager') || str_contains($name, 'orchid')) {
            return 'admin_panel';
        }

        // Media
        if (str_contains($name, 'intervention') || str_contains($name, 'glide') ||
            str_contains($name, 'image')) {
            return 'media';
        }

        // Queue/Jobs
        if (str_contains($name, 'rabbitmq') || str_contains($name, 'sqs') ||
            str_contains($name, 'beanstalkd')) {
            return 'queue';
        }

        // Search
        if (str_contains($name, 'algolia') || str_contains($name, 'meilisearch') ||
            str_contains($name, 'elasticsearch') || str_contains($name, 'tntsearch')) {
            return 'search';
        }

        // Localization
        if (str_contains($name, 'translat') || str_contains($name, 'localization')) {
            return 'localization';
        }

        // Permission/Roles
        if (str_contains($name, 'permission') || str_contains($name, 'acl') ||
            str_contains($name, 'role')) {
            return 'authorization';
        }

        // Notifications/Real-time
        if (str_contains($name, 'pusher') || str_contains($name, 'websocket') ||
            str_contains($name, 'reverb')) {
            return 'realtime';
        }

        // Export
        if (str_contains($name, 'excel') || str_contains($name, 'dompdf') ||
            str_contains($name, 'mpdf') || str_contains($name, 'tcpdf') ||
            str_contains($name, 'snappy')) {
            return 'export_pdf';
        }

        // Payments
        if (str_contains($name, 'stripe') || str_contains($name, 'paypal') ||
            str_contains($name, 'braintree') || str_contains($name, 'mollie') ||
            $name === 'moneyphp/money') {
            return 'payments';
        }

        // Debug bar
        if ($name === 'barryvdh/laravel-debugbar') {
            return 'debugging';
        }

        return 'utilities';
    }

    private function isLaravelPackage(string $name): bool
    {
        // Check if the package name suggests it's Laravel-specific
        if (str_starts_with($name, 'laravel/')) return true;
        if (str_contains($name, 'laravel-') || str_contains($name, '-laravel')) return true;

        // Common Laravel package indicators
        $laravelKeywords = ['laravel', 'lumen', 'livewire', 'jetstream', 'fortify',
                            'horizon', 'telescope', 'nova', 'scout', 'sanctum',
                            'socialite', 'cashier', 'envoy', 'dusk', 'pennant',
                            'folio', 'octane'];

        foreach ($laravelKeywords as $keyword) {
            if (str_contains($name, $keyword)) return true;
        }

        return false;
    }

    private function getPackagePurpose(string $name, string $category): string
    {
        $sharedPurposes = [
            'laravel/framework' => 'Core Laravel framework providing the foundation of the application',
            'livewire/livewire' => 'Full-stack framework for building dynamic interfaces with PHP',
            'spatie/laravel-permission' => 'Role and permission management for Laravel',
            'spatie/laravel-medialibrary' => 'Media library management for Eloquent models',
            'spatie/laravel-translatable' => 'Multi-language support for Eloquent models',
            'spatie/laravel-backup' => 'Backup solution for the application',
            'spatie/laravel-activitylog' => 'Activity logging for Eloquent models',
            'spatie/laravel-ignition' => 'Improved error pages and debugging',
            'spatie/laravel-sitemap' => 'Sitemap generation for SEO',
            'spatie/laravel-sluggable' => 'URL slug generation for models',
            'spatie/laravel-tags' => 'Tagging system for Eloquent models',
            'spatie/laravel-cookie-consent' => 'GDPR cookie consent management',
            'spatie/laravel-health' => 'Application health monitoring',
            'spatie/laravel-query-builder' => 'API query builder for filtering and sorting',
            'spatie/laravel-data' => 'Data transfer objects for Laravel',
            'barryvdh/laravel-debugbar' => 'Debug bar for development troubleshooting',
            'barryvdh/laravel-ide-helper' => 'IDE autocomplete helpers for facades and models',
            'barryvdh/laravel-dompdf' => 'PDF generation from HTML views',
            'barryvdh/laravel-cors' => 'Cross-Origin Resource Sharing support',
            'nunomaduro/collision' => 'Improved error handling for CLI commands',
            'nunomaduro/termwind' => 'Terminal styling for Artisan commands',
            'orchestra/testbench' => 'Testing environment for package development',
            'laravel/sanctum' => 'API token authentication for SPAs and mobile apps',
            'laravel/passport' => 'OAuth2 authentication server',
            'laravel/scout' => 'Full-text search across Eloquent models',
            'laravel/horizon' => 'Queue monitoring and management dashboard',
            'laravel/telescope' => 'Application debugging and monitoring tool',
            'laravel/socialite' => 'Social OAuth authentication providers',
            'laravel/cashier' => 'Subscription billing with Stripe or Paddle',
            'laravel/jetstream' => 'Application scaffolding with Livewire or Inertia',
            'laravel/fortify' => 'Authentication system backend implementation',
            'laravel/breeze' => 'Minimal authentication scaffolding',
            'laravel/octane' => 'Application performance optimization with Swoole/RR',
            'laravel/pennant' => 'Feature flag management',
            'laravel/reverb' => 'Real-time WebSocket communication',
            'laravel/pint' => 'PHP code style fixer',
            'laravel/serializable-closure' => 'Serializable closures for queue jobs',
            'sentry/sentry-laravel' => 'Error tracking and performance monitoring',
            'tymon/jwt-auth' => 'JWT (JSON Web Token) authentication',
            'php-open-source-saver/jwt-auth' => 'JWT authentication (maintained fork)',
            'guzzlehttp/guzzle' => 'HTTP client for API requests',
            'intervention/image' => 'Image manipulation and handling',
            'filament/filament' => 'Admin panel and form builder framework',
        ];

        return $sharedPurposes[$name] ?? match ($category) {
            'testing' => 'Testing and quality assurance',
            'debugging' => 'Debugging and error tracking',
            'development_tools' => 'Development productivity tools',
            'api' => 'API functionality and integration',
            'authentication' => 'Authentication and authorization',
            'admin_panel' => 'Administrative dashboard interface',
            'media' => 'Media and file management',
            'ui_frontend' => 'User interface and frontend assets',
            'payments' => 'Payment processing and billing',
            'realtime' => 'Real-time updates and WebSocket communication',
            'queue' => 'Queue and job processing',
            'search' => 'Search functionality',
            'export_pdf' => 'PDF and document export',
            'localization' => 'Multi-language and localization support',
            'database_networking' => 'Database drivers and networking',
            'authorization' => 'Roles, permissions, and access control',
            default => 'General utility and helper functions',
        };
    }
}