<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Beacon Configuration
    |--------------------------------------------------------------------------
    |
    | This file configures the Laravel Beacon v2 AI Project Intelligence Engine.
    | All scanners, analyzers, and exporters are configurable here.
    |
    */

    'enabled' => true,

    'output_directory' => 'storage/app/beacon',

    'project_name' => null,

    /*
    |--------------------------------------------------------------------------
    | Scanner Configuration
    |--------------------------------------------------------------------------
    | Enable or disable specific scanners for performance tuning.
    */
    'scanners' => [
        'models' => true,
        'controllers' => true,
        'routes' => true,
        'migrations' => true,
        'database' => true,
        'config' => true,
        'services' => true,
        'repositories' => true,
        'form_requests' => true,
        'middleware' => true,
        'policies' => true,
        'events' => true,
        'jobs' => true,
        'notifications' => true,
        'mail' => true,
        'traits' => true,
        'enums' => true,
        'helpers' => true,
        'livewire' => true,
        'blade' => true,
        'api' => true,
        'queue' => true,
        'storage' => true,
        'packages' => true,
        'commands' => true,
        'modules' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Intelligence Engine Configuration
    |--------------------------------------------------------------------------
    */
    'intelligence' => [
        'architecture' => true,
        'security' => true,
        'performance' => true,
        'business_rules' => true,
        'relationships' => true,
        'ai_summaries' => true,
        'database_intelligence' => true,
        'route_intelligence' => true,
        'statistics' => true,
        'folder_tree' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    | Control which output files are generated.
    */
    'output' => [
        'context_md' => true,
        'context_json' => true,
        'project_graph' => true,
        'architecture' => true,
        'business_rules' => true,
        'statistics' => true,
        'packages' => true,
        'database' => true,
        'routes' => true,
        'ai_index' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Performance Tuning
    |--------------------------------------------------------------------------
    */
    'performance' => [
        'max_file_size' => 500000, // 500KB max per file
        'chunk_size' => 4096,      // Bytes per read chunk
        'parallel_scans' => false, // Future: parallel scanning
    ],
];