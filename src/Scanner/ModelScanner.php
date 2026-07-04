<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\PhpParser;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Improved ModelScanner — extracts proven model metadata including
 * relationships with target models, table, fills, guarded, casts,
 * scopes, accessors, mutators, traits, observers, and factory status.
 *
 * Relationships are extracted with proper target model resolution
 * from method return types and body analysis.
 */
class ModelScanner
{
    public function __construct(
        private readonly PhpParser $phpParser,
    ) {}

    /**
     * Scan models and return structured data.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $path = app_path('Models');

        if (! is_dir($path)) {
            return ['models' => ['count' => 0, 'items' => []]];
        }

        $files = $this->getPhpFiles($path);
        $items = [];

        foreach ($files as $file) {
            $relativePath = $file['relative_path'];

            $contents = file_get_contents($file['pathname']);
            if ($contents === false) {
                continue;
            }

            $parsed = $this->phpParser->parse($contents);

            $name = $parsed['class_name'] ?? $this->extractClassName($contents);
            $namespace = $parsed['namespace'] ?? $this->extractNamespace($contents) ?? 'App\\Models';

            if ($name === null) {
                continue;
            }

            $fqcn = $namespace . '\\' . $name;
            $traits = $parsed['traits'] ?? [];

            // Detect common Laravel traits
            $hasFactory = in_array('HasFactory', $traits) || in_array('Illuminate\\Database\\Eloquent\\Factories\\HasFactory', $traits);
            $softDeletes = in_array('SoftDeletes', $traits) || in_array('Illuminate\\Database\\Eloquent\\SoftDeletes', $traits);

            // Extract relationships with target models (proven from return types)
            $relations = $this->extractRelations($contents);

            // Extract scopes, accessors, mutators (proven from method signatures)
            $scopes = $this->extractScopes($contents);
            $accessors = $this->extractAccessors($contents);
            $mutators = $this->extractMutators($contents);

            // Extract observers (proven from boot() method)
            $observers = $this->extractObservers($contents);

            $items[] = [
                // Backward-compatible fields
                'name' => $name,
                'namespace' => $namespace,
                'fqcn' => $fqcn,
                'path' => $relativePath,

                // v6 Proven metadata
                'parent' => $parsed['parent'],
                'is_base_model' => $parsed['parent'] === 'Model' || $parsed['parent'] === 'Illuminate\\Database\\Eloquent\\Model',
                'traits' => $traits,
                'traits_count' => count($traits),
                'has_factory' => $hasFactory,
                'soft_deletes' => $softDeletes,
                'interfaces' => $parsed['interfaces'],

                // Database properties (proven from class definition)
                'table' => $this->extractStringProperty($contents, 'table'),
                'connection' => $this->extractStringProperty($contents, 'connection'),
                'primary_key' => $this->extractStringProperty($contents, 'primaryKey'),
                'key_type' => $this->extractStringProperty($contents, 'keyType'),
                'incrementing' => $this->extractBoolProperty($contents, 'incrementing'),
                'timestamps' => $this->extractBoolProperty($contents, 'timestamps'),

                // Mass assignment (proven from $fillable/$guarded declarations)
                'fillable' => $this->extractArray('fillable', $contents),
                'fillable_count' => count($this->extractArray('fillable', $contents)),
                'guarded' => $this->extractArray('guarded', $contents),
                'guarded_count' => count($this->extractArray('guarded', $contents)),
                'hidden' => $this->extractArray('hidden', $contents),
                'appends' => $this->extractArray('appends', $contents),

                // Casts (proven from $casts)
                'casts' => $this->extractCasts($contents),
                'casts_count' => count($this->extractCasts($contents)),

                // Relationships (proven from method return types and bodies)
                'relations' => $relations,
                'relations_count' => count($relations),

                // Scopes, accessors, mutators
                'scopes' => $scopes,
                'scopes_count' => count($scopes),
                'accessors' => $accessors,
                'accessors_count' => count($accessors),
                'mutators' => $mutators,
                'mutators_count' => count($mutators),

                // Observers (proven from boot() method)
                'observers' => $observers,
                'observers_count' => count($observers),
                'boot_method' => (bool) preg_match('/protected\s+function\s+boot\s*\(\s*\)/', $contents),

                // Constants
                'constants' => $parsed['constants'] ?? [],

                // Statistics
                'line_count' => $parsed['line_count'] ?? 0,
                'methods_count' => count(array_filter($parsed['methods'] ?? [], fn($m) => $m['visibility'] === 'public')),
            ];
        }

        usort($items, fn($a, $b) => $a['fqcn'] <=> $b['fqcn']);

        return [
            'models' => [
                'count' => count($items),
                'items' => $items,
                'by_table' => $this->groupByTable($items),
                'by_trait' => $this->groupByTrait($items),
                'parent_chain' => $this->buildParentChain($items),
            ],
        ];
    }

    /**
     * Extract relationships with target models (proven from return types + body).
     */
    private function extractRelations(string $contents): array
    {
        $relations = [];

        // Pattern 1: Typed return: function methodName(): \Illuminate...\RelationType
        $typePattern = '/function\s+(\w+)\s*\(\s*\)\s*:\s*(?:\\\\?Illuminate\\\\(?:Database\\\\)?Eloquent\\\\)?(HasMany|BelongsTo|HasOne|BelongsToMany|MorphMany|MorphTo|MorphOne|HasManyThrough|HasOneThrough|MorphToMany|MorphedByMany)/';
        if (preg_match_all($typePattern, $contents, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $methodName = $match[1];
                $relationType = $match[2];

                if (in_array($methodName, ['boot', 'bootTraits', 'newInstance', 'newQuery', 'newModelQuery', 'newEloquentBuilder', 'newCollection', 'newPivot'])) {
                    continue;
                }

                $targetModel = $this->extractRelationTarget($contents, $methodName, $relationType);

                $relations[] = [
                    'method' => $methodName,
                    'type' => $relationType,
                    'target' => $targetModel,
                    'evidence' => 'typed_return_type',
                ];
            }
        }

        // Pattern 2: Non-typed body analysis (fallback)
        if (empty($relations)) {
            $relationTypes = ['hasMany', 'belongsTo', 'hasOne', 'belongsToMany', 'morphMany', 'morphTo', 'morphOne',
                'hasManyThrough', 'hasOneThrough', 'morphToMany', 'morphedByMany'];

            foreach ($relationTypes as $type) {
                $bodyPattern = '/function\s+(\w+)\s*\(\s*\)\s*(?::[^{]+)?\{([^}]*)\$this->' . preg_quote($type, '/') . '\s*\(/s';
                if (preg_match_all($bodyPattern, $contents, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $methodName = $match[1];

                        if (in_array($methodName, ['boot', 'bootTraits'])) continue;

                        $targetModel = $this->extractRelationTarget($contents, $methodName, $type);

                        $relations[] = [
                            'method' => $methodName,
                            'type' => $type,
                            'target' => $targetModel,
                            'evidence' => 'body_analysis',
                        ];
                    }
                }
            }
        }

        return $relations;
    }

    /**
     * Extract the target model from a relationship method body.
     */
    private function extractRelationTarget(string $contents, string $methodName, string $relationType): ?string
    {
        $pattern = '/function\s+' . preg_quote($methodName, '/') . '\s*\(\s*\)\s*(?::[^{]+)?\{[^}]*\$this->' . preg_quote($relationType, '/') . '\s*\(\s*([^,\s)]+)/s';

        if (preg_match($pattern, $contents, $matches)) {
            $target = trim($matches[1]);
            $target = trim($target, "'\"");

            if (str_ends_with($target, '::class')) {
                $target = substr($target, 0, -7);
            }

            if (!empty($target) && !str_starts_with($target, '$')) {
                return $target;
            }
        }

        return null;
    }

    /**
     * Extract observers registered in the boot() method.
     */
    private function extractObservers(string $contents): array
    {
        $observers = [];

        if (preg_match_all('/static::observe\s*\(\s*([^)]+)\s*\)/', $contents, $matches)) {
            foreach ($matches[1] as $match) {
                $observer = trim($match);
                $observer = trim($observer, "'\"");
                if (str_ends_with($observer, '::class')) {
                    $observer = substr($observer, 0, -7);
                }
                if (!empty($observer) && !str_starts_with($observer, '$')) {
                    $observers[] = [
                        'class' => $observer,
                        'registered_in' => 'boot()',
                    ];
                }
            }
        }

        return $observers;
    }

    private function extractArray(string $property, string $contents): array
    {
        $pattern = '/protected\s+\$' . preg_quote($property, '/') . '\s*=\s*\[([^\]]*)\]/s';
        if (preg_match($pattern, $contents, $matches)) {
            $items = explode(',', $matches[1]);
            $result = [];
            foreach ($items as $item) {
                $item = trim($item);
                $item = trim($item, "'\"");
                if ($item !== '') {
                    $result[] = $item;
                }
            }
            return $result;
        }
        return [];
    }

    private function extractStringProperty(string $contents, string $property): ?string
    {
        $pattern = '/protected\s+\$' . preg_quote($property, '/') . '\s*=\s*[\'"]([^\'"]+)[\'"]\s*;/';
        if (preg_match($pattern, $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractBoolProperty(string $contents, string $property): ?bool
    {
        $pattern = '/protected\s+\$' . preg_quote($property, '/') . '\s*=\s*(true|false)\s*;/';
        if (preg_match($pattern, $contents, $matches)) {
            return $matches[1] === 'true';
        }
        return null;
    }

    private function extractCasts(string $contents): array
    {
        $casts = [];

        if (preg_match('/protected\s+\$casts\s*=\s*\[([^\]]*)\]/s', $contents, $matches)) {
            $items = explode(',', $matches[1]);
            foreach ($items as $item) {
                $item = trim($item);
                if ($item === '') continue;
                if (preg_match('/[\'"]([\w_]+)[\'"]\s*=>\s*[\'"]([\w_]+)[\'"]/', $item, $m)) {
                    $casts[$m[1]] = $m[2];
                }
            }
        }

        // Also check for casts() method (Castable interface)
        if (preg_match('/function\s+casts\s*\(\s*\)\s*(:\s*array)?\s*\{/', $contents)) {
            $casts['_has_casts_method'] = true;
        }

        return $casts;
    }

    private function extractScopes(string $contents): array
    {
        $scopes = [];
        if (preg_match_all('/public\s+function\s+scope(\w+)\s*\(/s', $contents, $matches)) {
            foreach ($matches[1] as $scope) {
                $scopes[] = [
                    'name' => lcfirst($scope),
                    'method' => 'scope' . $scope,
                ];
            }
        }
        return $scopes;
    }

    private function extractAccessors(string $contents): array
    {
        $accessors = [];
        if (preg_match_all('/public\s+function\s+get(\w+)Attribute\s*\(\s*\)/s', $contents, $matches)) {
            foreach ($matches[1] as $attr) {
                $accessors[] = [
                    'attribute' => lcfirst($attr),
                    'method' => 'get' . $attr . 'Attribute',
                ];
            }
        }
        return $accessors;
    }

    private function extractMutators(string $contents): array
    {
        $mutators = [];
        if (preg_match_all('/public\s+function\s+set(\w+)Attribute\s*\(/s', $contents, $matches)) {
            foreach ($matches[1] as $attr) {
                $mutators[] = [
                    'attribute' => lcfirst($attr),
                    'method' => 'set' . $attr . 'Attribute',
                ];
            }
        }
        return $mutators;
    }

    private function getPhpFiles(string $path): array
    {
        $result = [];
        $basePath = rtrim(realpath($path), '/');
        try {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $pathname = $file->getPathname();
                    $relativePath = str_replace($basePath . '/', '', $pathname);
                    $result[] = [
                        'pathname' => $pathname,
                        'relative_path' => $relativePath,
                        'filename' => $file->getFilename(),
                    ];
                }
            }
        } catch (\Throwable) {
            return [];
        }
        return $result;
    }

    private function extractClassName(string $contents): ?string
    {
        if (preg_match('/^class\s+(\w+)/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractNamespace(string $contents): ?string
    {
        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function groupByTable(array $items): array
    {
        $groups = [];
        foreach ($items as $model) {
            $table = $model['table'] ?? $this->modelNameToTable($model['name']);
            if (!isset($groups[$table])) {
                $groups[$table] = [];
            }
            $groups[$table][] = $model['name'];
        }
        return $groups;
    }

    private function groupByTrait(array $items): array
    {
        $groups = [];
        foreach ($items as $model) {
            foreach ($model['traits'] as $trait) {
                $short = $this->shortClassName($trait);
                if (!isset($groups[$short])) {
                    $groups[$short] = [];
                }
                $groups[$short][] = $model['name'];
            }
        }
        return $groups;
    }

    private function buildParentChain(array $items): array
    {
        $chain = [];
        foreach ($items as $model) {
            if ($model['parent'] && $model['parent'] !== 'Model' && !str_contains($model['parent'], 'Illuminate\\')) {
                $parentShort = $this->shortClassName($model['parent']);
                foreach ($items as $potential) {
                    if ($potential['name'] === $parentShort || $potential['fqcn'] === $model['parent']) {
                        $chain[] = [
                            'child' => $model['fqcn'],
                            'parent' => $potential['fqcn'],
                            'type' => 'extends',
                        ];
                    }
                }
            }
        }
        return $chain;
    }

    private function shortClassName(string $fqcn): string
    {
        $parts = explode('\\', $fqcn);
        return end($parts);
    }

    private function modelNameToTable(string $name): string
    {
        $snake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $name));
        return $snake . 's';
    }
}