<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Providers;

use Coffesoft\LaravelBeacon\Builder\ContextBuilder;
use Coffesoft\LaravelBeacon\Cache\ScanCache;
use Coffesoft\LaravelBeacon\Console\BeaconExportCommand;
use Coffesoft\LaravelBeacon\Console\BeaconScanCommand;
use Coffesoft\LaravelBeacon\Intelligence\AiContextCompressor;
use Coffesoft\LaravelBeacon\Intelligence\AiPromptPack;
use Coffesoft\LaravelBeacon\Intelligence\ArchitectureDetector;
use Coffesoft\LaravelBeacon\Intelligence\AISummarizer;
use Coffesoft\LaravelBeacon\Intelligence\BusinessRuleDetector;
use Coffesoft\LaravelBeacon\Intelligence\DatabaseIntelligence;
use Coffesoft\LaravelBeacon\Intelligence\DependencyGraphGenerator;
use Coffesoft\LaravelBeacon\Intelligence\DeveloperOnboarding;
use Coffesoft\LaravelBeacon\Intelligence\EntryPointDetector;
use Coffesoft\LaravelBeacon\Intelligence\FeatureMapGenerator;
use Coffesoft\LaravelBeacon\Intelligence\FolderTreeGenerator;
use Coffesoft\LaravelBeacon\Intelligence\ImpactMapGenerator;
use Coffesoft\LaravelBeacon\Intelligence\ModuleDetector;
use Coffesoft\LaravelBeacon\Intelligence\PerformanceAnalyzer;
use Coffesoft\LaravelBeacon\Intelligence\RelationshipGraph;
use Coffesoft\LaravelBeacon\Intelligence\RouteIntelligence;
use Coffesoft\LaravelBeacon\Intelligence\SecurityAnalyzer;
use Coffesoft\LaravelBeacon\Intelligence\WorkflowDetector;
use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;
use Coffesoft\LaravelBeacon\Scanner\APIScanner;
use Coffesoft\LaravelBeacon\Scanner\BladeScanner;
use Coffesoft\LaravelBeacon\Scanner\ConfigScanner;
use Coffesoft\LaravelBeacon\Scanner\ControllerScanner;
use Coffesoft\LaravelBeacon\Scanner\DatabaseScanner;
use Coffesoft\LaravelBeacon\Scanner\EnumScanner;
use Coffesoft\LaravelBeacon\Scanner\EventScanner;
use Coffesoft\LaravelBeacon\Scanner\FormRequestScanner;
use Coffesoft\LaravelBeacon\Scanner\HelperScanner;
use Coffesoft\LaravelBeacon\Scanner\JobScanner;
use Coffesoft\LaravelBeacon\Scanner\LivewireScanner;
use Coffesoft\LaravelBeacon\Scanner\MailScanner;
use Coffesoft\LaravelBeacon\Scanner\MiddlewareScanner;
use Coffesoft\LaravelBeacon\Scanner\MigrationScanner;
use Coffesoft\LaravelBeacon\Scanner\ModelScanner;
use Coffesoft\LaravelBeacon\Scanner\NotificationScanner;
use Coffesoft\LaravelBeacon\Scanner\PackageScanner;
use Coffesoft\LaravelBeacon\Scanner\PolicyScanner;
use Coffesoft\LaravelBeacon\Scanner\QueueScanner;
use Coffesoft\LaravelBeacon\Scanner\RepositoryScanner;
use Coffesoft\LaravelBeacon\Scanner\RouteScanner;
use Coffesoft\LaravelBeacon\Scanner\ServiceScanner;
use Coffesoft\LaravelBeacon\Scanner\StatisticsScanner;
use Coffesoft\LaravelBeacon\Scanner\StorageScanner;
use Coffesoft\LaravelBeacon\Scanner\TraitScanner;
use Illuminate\Support\ServiceProvider;

class BeaconServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/beacon.php', 'beacon');

        // Register FileReader as singleton
        $this->app->singleton(FileReader::class);
        $this->app->singleton(PhpParser::class);

        // Register all scanners
        $this->app->singleton(ModelScanner::class);
        $this->app->singleton(ControllerScanner::class);
        $this->app->singleton(RouteScanner::class);
        $this->app->singleton(MigrationScanner::class);
        $this->app->singleton(DatabaseScanner::class);
        $this->app->singleton(StatisticsScanner::class);
        $this->app->singleton(ConfigScanner::class);
        $this->app->singleton(ServiceScanner::class);
        $this->app->singleton(RepositoryScanner::class);
        $this->app->singleton(FormRequestScanner::class);
        $this->app->singleton(MiddlewareScanner::class);
        $this->app->singleton(PolicyScanner::class);
        $this->app->singleton(EventScanner::class);
        $this->app->singleton(JobScanner::class);
        $this->app->singleton(NotificationScanner::class);
        $this->app->singleton(MailScanner::class);
        $this->app->singleton(TraitScanner::class);
        $this->app->singleton(EnumScanner::class);
        $this->app->singleton(HelperScanner::class);
        $this->app->singleton(LivewireScanner::class);
        $this->app->singleton(BladeScanner::class);
        $this->app->singleton(APIScanner::class);
        $this->app->singleton(QueueScanner::class);
        $this->app->singleton(StorageScanner::class);
        $this->app->singleton(PackageScanner::class);
        $this->app->singleton(ModuleDetector::class);

        // Register intelligence engines (v2)
        $this->app->singleton(ArchitectureDetector::class);
        $this->app->singleton(SecurityAnalyzer::class);
        $this->app->singleton(PerformanceAnalyzer::class);
        $this->app->singleton(BusinessRuleDetector::class);
        $this->app->singleton(RelationshipGraph::class);
        $this->app->singleton(AISummarizer::class);
        $this->app->singleton(DatabaseIntelligence::class);
        $this->app->singleton(RouteIntelligence::class);
        $this->app->singleton(FolderTreeGenerator::class);

        // Register v2.1 intelligence engines
        $this->app->singleton(AiContextCompressor::class);
        $this->app->singleton(WorkflowDetector::class);
        $this->app->singleton(EntryPointDetector::class);
        $this->app->singleton(DependencyGraphGenerator::class);
        $this->app->singleton(FeatureMapGenerator::class);
        $this->app->singleton(DeveloperOnboarding::class);
        $this->app->singleton(ImpactMapGenerator::class);
        $this->app->singleton(AiPromptPack::class);

        // Register cache
        $this->app->singleton(ScanCache::class);

        // Register ContextBuilder
        $this->app->singleton(ContextBuilder::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                BeaconScanCommand::class,
                BeaconExportCommand::class,
            ]);

            $this->publishes([
                __DIR__ . '/../../config/beacon.php' => config_path('beacon.php'),
            ], 'beacon-config');
        }
    }
}