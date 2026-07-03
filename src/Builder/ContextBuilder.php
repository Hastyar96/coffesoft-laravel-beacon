<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Builder;

use Coffesoft\LaravelBeacon\Cache\ScanCache;
use Coffesoft\LaravelBeacon\Context\Context;
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

/**
 * v2.1 ContextBuilder — Orchestrates all scanners, intelligence, and cache.
 */
class ContextBuilder
{
    public function __construct(
        // Scanners
        private readonly ModelScanner $modelScanner,
        private readonly ControllerScanner $controllerScanner,
        private readonly RouteScanner $routeScanner,
        private readonly MigrationScanner $migrationScanner,
        private readonly DatabaseScanner $databaseScanner,
        private readonly StatisticsScanner $statisticsScanner,
        private readonly ConfigScanner $configScanner,
        private readonly ServiceScanner $serviceScanner,
        private readonly RepositoryScanner $repositoryScanner,
        private readonly FormRequestScanner $formRequestScanner,
        private readonly MiddlewareScanner $middlewareScanner,
        private readonly PolicyScanner $policyScanner,
        private readonly EventScanner $eventScanner,
        private readonly JobScanner $jobScanner,
        private readonly NotificationScanner $notificationScanner,
        private readonly MailScanner $mailScanner,
        private readonly TraitScanner $traitScanner,
        private readonly EnumScanner $enumScanner,
        private readonly HelperScanner $helperScanner,
        private readonly LivewireScanner $livewireScanner,
        private readonly BladeScanner $bladeScanner,
        private readonly APIScanner $apiScanner,
        private readonly QueueScanner $queueScanner,
        private readonly StorageScanner $storageScanner,
        private readonly PackageScanner $packageScanner,
        private readonly ModuleDetector $moduleDetector,
        // Core intelligence engines
        private readonly ArchitectureDetector $architectureDetector,
        private readonly SecurityAnalyzer $securityAnalyzer,
        private readonly PerformanceAnalyzer $performanceAnalyzer,
        private readonly BusinessRuleDetector $businessRuleDetector,
        private readonly RelationshipGraph $relationshipGraph,
        private readonly AISummarizer $aiSummarizer,
        private readonly DatabaseIntelligence $databaseIntelligence,
        private readonly RouteIntelligence $routeIntelligence,
        private readonly FolderTreeGenerator $folderTreeGenerator,
        // v2.1 intelligence engines
        private readonly AiContextCompressor $aiContextCompressor,
        private readonly WorkflowDetector $workflowDetector,
        private readonly EntryPointDetector $entryPointDetector,
        private readonly DependencyGraphGenerator $dependencyGraphGenerator,
        private readonly FeatureMapGenerator $featureMapGenerator,
        private readonly DeveloperOnboarding $developerOnboarding,
        private readonly ImpactMapGenerator $impactMapGenerator,
        private readonly AiPromptPack $aiPromptPack,
        // Cache
        private readonly ScanCache $scanCache,
    ) {}

    /**
     * Build a fully populated Context object.
     */
    public function build(): Context
    {
        $context = new Context();
        $isIncremental = !$this->scanCache->isFirstScan();

        // Framework basics
        $context->merge([
            'framework' => [
                'name' => 'Laravel',
                'version' => app()->version(),
                'php_version' => PHP_VERSION,
            ],
            'incremental_scan' => $isIncremental,
            'cache_stats' => $this->scanCache->getStats(),
        ]);

        // Phase 1: Scan all project components
        $context->merge($this->modelScanner->scan());
        $context->merge($this->controllerScanner->scan());
        $context->merge($this->routeScanner->scan());
        $context->merge($this->migrationScanner->scan());
        $context->merge($this->databaseScanner->scan());
        $context->merge($this->statisticsScanner->scan());
        $context->merge($this->configScanner->scan());
        $context->merge($this->serviceScanner->scan());
        $context->merge($this->repositoryScanner->scan());
        $context->merge($this->formRequestScanner->scan());
        $context->merge($this->middlewareScanner->scan());
        $context->merge($this->policyScanner->scan());
        $context->merge($this->eventScanner->scan());
        $context->merge($this->jobScanner->scan());
        $context->merge($this->notificationScanner->scan());
        $context->merge($this->mailScanner->scan());
        $context->merge($this->traitScanner->scan());
        $context->merge($this->enumScanner->scan());
        $context->merge($this->helperScanner->scan());
        $context->merge($this->livewireScanner->scan());
        $context->merge($this->bladeScanner->scan());
        $context->merge($this->apiScanner->scan());
        $context->merge($this->queueScanner->scan());
        $context->merge($this->storageScanner->scan());
        $context->merge($this->packageScanner->scan());

        // Phase 2: Module detection
        $modules = $this->moduleDetector->detect($context->all());
        $context->merge($modules);

        // Phase 3: Core intelligence analysis
        $context->merge($this->architectureDetector->detect($context->all()));
        $context->merge($this->securityAnalyzer->analyze($context->all()));
        $context->merge($this->performanceAnalyzer->analyze($context->all()));
        $context->merge($this->businessRuleDetector->detect($context->all()));

        // Phase 4: Relationship graph
        $context->merge($this->relationshipGraph->generate($context->all()));

        // Phase 5: AI summaries
        $context->merge($this->aiSummarizer->generate($context->all()));

        // Phase 6: Specialized intelligence
        $context->merge($this->databaseIntelligence->analyze($context->all()));
        $context->merge($this->routeIntelligence->analyze($context->all()));

        // Phase 7: Folder tree
        $context->merge($this->folderTreeGenerator->generate());

        // Phase 8: v2.1 AI Context Compression
        $context->merge($this->aiContextCompressor->generate($context->all()));

        // Phase 9: Workflow Detection
        $context->merge($this->workflowDetector->detect($context->all()));

        // Phase 10: Entry Point Detection
        $context->merge($this->entryPointDetector->detect($context->all()));

        // Phase 11: Dependency Graph
        $context->merge($this->dependencyGraphGenerator->generate($context->all()));

        // Phase 12: Feature Map
        $context->merge($this->featureMapGenerator->generate($context->all()));

        // Phase 13: Developer Onboarding Guide
        $context->merge($this->developerOnboarding->generate($context->all()));

        // Phase 14: Change Impact Map
        $context->merge($this->impactMapGenerator->generate($context->all()));

        // Phase 15: AI Prompt Pack
        $context->merge($this->aiPromptPack->generate($context->all()));

        // Record scanned files for incremental cache
        $this->recordScannedFiles();

        // Timestamp
        $context->set('generated_at', now()->toIso8601String());
        $context->set('beacon_version', '2.1.0');

        return $context;
    }

    /**
     * Record all scanned PHP files for incremental caching.
     */
    private function recordScannedFiles(): void
    {
        $files = [];
        $dirs = [
            app_path('Models'),
            app_path('Http/Controllers'),
            app_path('Services'),
            app_path('Repositories'),
            app_path('Http/Requests'),
            app_path('Policies'),
            app_path('Events'),
            app_path('Listeners'),
            app_path('Jobs'),
            app_path('Notifications'),
            app_path('Mail'),
            app_path('Livewire'),
            app_path('Http/Livewire'),
            app_path('Traits'),
            app_path('Concerns'),
            app_path('Enums'),
            app_path('Enum'),
            app_path('Helpers'),
            app_path('Http/Resources'),
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        $this->scanCache->recordScan($files);
    }
}