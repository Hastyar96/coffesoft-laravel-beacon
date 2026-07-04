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
use Coffesoft\LaravelBeacon\Reader\MethodBodyAnalyzer;
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
 * ContextBuilder — Orchestrates all scanners, intelligence, and cache.
 *
 * FIX: Added resilience — a single malformed scanner result does NOT
 * abort the whole scan. Validation warnings are collected and reported
 * instead of silently corrupting data.
 */
class ContextBuilder
{
    /**
     * @var array<int, array{scanner: string, message: string}>
     */
    private array $scanErrors = [];

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
        //
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
                'version' => $this->getLaravelVersion(),
                'php_version' => PHP_VERSION,
            ],
            'incremental_scan' => $isIncremental,
            'cache_stats' => $this->scanCache->getStats(),
        ]);

        // Phase 1: Scan all project components (each wrapped in try/catch for isolation)
        $context = $this->scanPhase($context, [
            'ModelScanner' => fn() => $this->modelScanner->scan(),
            'ControllerScanner' => fn() => $this->controllerScanner->scan(),
            'RouteScanner' => fn() => $this->routeScanner->scan(),
            'MigrationScanner' => fn() => $this->migrationScanner->scan(),
            'DatabaseScanner' => fn() => $this->databaseScanner->scan(),
            'StatisticsScanner' => fn() => $this->statisticsScanner->scan(),
            'ConfigScanner' => fn() => $this->configScanner->scan(),
            'ServiceScanner' => fn() => $this->serviceScanner->scan(),
            'RepositoryScanner' => fn() => $this->repositoryScanner->scan(),
            'FormRequestScanner' => fn() => $this->formRequestScanner->scan(),
            'MiddlewareScanner' => fn() => $this->middlewareScanner->scan(),
            'PolicyScanner' => fn() => $this->policyScanner->scan(),
            'EventScanner' => fn() => $this->eventScanner->scan(),
            'JobScanner' => fn() => $this->jobScanner->scan(),
            'NotificationScanner' => fn() => $this->notificationScanner->scan(),
            'MailScanner' => fn() => $this->mailScanner->scan(),
            'TraitScanner' => fn() => $this->traitScanner->scan(),
            'EnumScanner' => fn() => $this->enumScanner->scan(),
            'HelperScanner' => fn() => $this->helperScanner->scan(),
            'LivewireScanner' => fn() => $this->livewireScanner->scan(),
            'BladeScanner' => fn() => $this->bladeScanner->scan(),
            'APIScanner' => fn() => $this->apiScanner->scan(),
            'QueueScanner' => fn() => $this->queueScanner->scan(),
            'StorageScanner' => fn() => $this->storageScanner->scan(),
            'PackageScanner' => fn() => $this->packageScanner->scan(),
        ]);

        // Phase 2: Module detection
        $context = $this->analyzePhase($context, [
            'ModuleDetector' => fn($data) => $this->moduleDetector->detect($data),
            'ArchitectureDetector' => fn($data) => $this->architectureDetector->detect($data),
            'SecurityAnalyzer' => fn($data) => $this->securityAnalyzer->analyze($data),
            'PerformanceAnalyzer' => fn($data) => $this->performanceAnalyzer->analyze($data),
            'BusinessRuleDetector' => fn($data) => $this->businessRuleDetector->detect($data),
            'RelationshipGraph' => fn($data) => $this->relationshipGraph->generate($data),
            'AISummarizer' => fn($data) => $this->aiSummarizer->generate($data),
            'DatabaseIntelligence' => fn($data) => $this->databaseIntelligence->analyze($data),
            'RouteIntelligence' => fn($data) => $this->routeIntelligence->analyze($data),
            'FolderTreeGenerator' => fn($data) => $this->folderTreeGenerator->generate(),
            'AiContextCompressor' => fn($data) => $this->aiContextCompressor->generate($data),
            'WorkflowDetector' => fn($data) => $this->workflowDetector->detect($data),
            'EntryPointDetector' => fn($data) => $this->entryPointDetector->detect($data),
            'DependencyGraphGenerator' => fn($data) => $this->dependencyGraphGenerator->generate($data),
            'FeatureMapGenerator' => fn($data) => $this->featureMapGenerator->generate($data),
            'DeveloperOnboarding' => fn($data) => $this->developerOnboarding->generate($data),
            'ImpactMapGenerator' => fn($data) => $this->impactMapGenerator->generate($data),
            'AiPromptPack' => fn($data) => $this->aiPromptPack->generate($data),
        ]);

        // Return scan errors as a validation entry
        if (!empty($this->scanErrors)) {
            $context->set('_scan_errors', $this->scanErrors);
        }

        // Record scanned files for incremental cache
        $this->recordScannedFiles();

        // Timestamp
        $context->set('generated_at', date('c'));
        $context->set('beacon_version', '1.0.0');

        return $context;
    }

    /**
     * Run multiple scanner functions with isolated error handling.
     * Each scanner failure is recorded but does NOT abort the scan.
     *
     * @param array<string, callable> $scanners
     */
    private function scanPhase(Context $context, array $scanners): Context
    {
        foreach ($scanners as $name => $scanner) {
            try {
                $result = $scanner();
                if (is_array($result) && !empty($result)) {
                    $context->merge($result);
                }
            } catch (\Error $e) {
                // Class not found errors (e.g. HasApiTokens) should be caught here
                $this->recordError($name, $e->getMessage());
            } catch (\Throwable $e) {
                $this->recordError($name, $e->getMessage());
            }
        }
        return $context;
    }

    /**
     * Run multiple intelligence analysis functions with isolated error handling.
     *
     * @param array<string, callable> $analyzers
     */
    private function analyzePhase(Context $context, array $analyzers): Context
    {
        $data = $context->all();
        foreach ($analyzers as $name => $analyzer) {
            try {
                $result = $analyzer($data);
                if (is_array($result) && !empty($result)) {
                    $context->merge($result);
                }
            } catch (\Error $e) {
                $this->recordError($name, $e->getMessage());
            } catch (\Throwable $e) {
                $this->recordError($name, $e->getMessage());
            }
            
            // Refresh data for next analyzer
            $data = $context->all();
        }
        return $context;
    }

    /**
     * Record a scanner/analyzer error for diagnostics.
     */
    private function recordError(string $scanner, string $message): void
    {
        $this->scanErrors[] = [
            'scanner' => $scanner,
            'message' => $message,
        ];
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

    private function getLaravelVersion(): string
    {
        try {
            return app()->version();
        } catch (\Throwable) {
            return '(unknown)';
        }
    }
}