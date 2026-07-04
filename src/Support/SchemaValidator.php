<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Support;

/**
 * SchemaValidator — validates scanner output against documented schemas.
 *
 * Every scanner output must match its expected structure.
 * This prevents "Array to string conversion" errors by catching
 * type mismatches at the source.
 */
class SchemaValidator
{
    /**
     * @var array<string, array<string, string>> Scanner name => [field => type]
     */
    private static array $scannerSchemas = [
        'ModelScanner' => [
            'models' => 'array',
            'models.count' => 'integer',
            'models.items' => 'array',
            'models.items.*.name' => 'string',
            'models.items.*.path' => 'string',
            'models.items.*.fillable' => 'array',
            'models.items.*.traits' => 'array',
            'models.items.*.relations' => 'array',
            'models.items.*.scopes' => 'array',
            'models.items.*.accessors' => 'array',
            'models.items.*.mutators' => 'array',
            'models.items.*.observers' => 'array',
            'models.items.*.line_count' => 'integer',
            'models.items.*.methods_count' => 'integer',
            'models.items.*.fillable_count' => 'integer',
            'models.items.*.casts_count' => 'integer',
            'models.items.*.relations_count' => 'integer',
        ],
        'ControllerScanner' => [
            'controllers' => 'array',
            'controllers.count' => 'integer',
            'controllers.items' => 'array',
            'controllers.items.*.name' => 'string',
            'controllers.items.*.path' => 'string',
            'controllers.items.*.group' => 'string',
            'controllers.items.*.methods' => 'array',
            'controllers.items.*.middleware' => 'array',
            'controllers.items.*.is_crud' => 'boolean',
            'controllers.items.*.line_count' => 'integer',
            'controllers.items.*.models_used' => 'array',
            'controllers.items.*.views_returned' => 'array',
        ],
        'RouteScanner' => [
            'routes' => 'array',
            'routes.count' => 'integer',
            'routes.items' => 'array',
            'routes.items.*.uri' => 'string',
            'routes.items.*.methods' => 'array',
            'routes.items.*.name' => 'string|null',
            'routes.items.*.action' => 'string',
            'routes.items.*.controller' => 'string|null',
            'routes.items.*.controller_short' => 'string|null',
            'routes.items.*.method' => 'string|null',
            'routes.items.*.middleware' => 'array',
            'routes.items.*.parameters' => 'array',
            'routes.items.*.parameter_count' => 'integer',
            'routes.items.*.has_wildcard' => 'boolean',
            'routes.items.*.module' => 'string',
        ],
        'MigrationScanner' => [
            'migrations' => 'array',
            'migrations.count' => 'integer',
            'migrations.items' => 'array',
            'migrations.tables' => 'array',
        ],
        'DatabaseScanner' => [
            'database' => 'array',
            'database.tables' => 'array',
            'database.pivot_tables' => 'array',
            'database.total_tables' => 'integer',
        ],
        'StatisticsScanner' => [
            'statistics' => 'array',
            'statistics.total_php_files' => 'integer',
            'statistics.total_blade_files' => 'integer',
            'statistics.models' => 'integer',
            'statistics.controllers' => 'integer',
            'statistics.services' => 'integer',
            'statistics.repositories' => 'integer',
            'statistics.requests' => 'integer',
            'statistics.policies' => 'integer',
            'statistics.events' => 'integer',
            'statistics.jobs' => 'integer',
            'statistics.notifications' => 'integer',
            'statistics.commands' => 'integer',
            'statistics.enums' => 'integer',
            'statistics.packages' => 'integer',
            'statistics.database_tables' => 'integer',
            'statistics.average_controller_methods' => 'float|integer',
            'statistics.average_model_methods' => 'float|integer',
        ],
        'ServiceScanner' => [
            'services' => 'array',
            'services.count' => 'integer',
            'services.items' => 'array',
            'services.items.*.name' => 'string',
            'services.items.*.methods' => 'array',
            'services.items.*.dependencies' => 'array',
        ],
        'MiddlewareScanner' => [
            'middleware' => 'array',
            'middleware.count' => 'integer',
            'middleware.items' => 'array',
        ],
        'PolicyScanner' => [
            'policies' => 'array',
            'policies.count' => 'integer',
            'policies.items' => 'array',
        ],
        'EventScanner' => [
            'events' => 'array',
            'events.count' => 'integer',
            'events.definitions' => 'array',
            'events.listeners' => 'array',
        ],
        'JobScanner' => [
            'jobs' => 'array',
            'jobs.count' => 'integer',
            'jobs.definitions' => 'array',
        ],
        'MailScanner' => [
            'mail' => 'array',
            'mail.count' => 'integer',
            'mail.items' => 'array',
        ],
        'NotificationScanner' => [
            'notifications' => 'array',
            'notifications.count' => 'integer',
            'notifications.items' => 'array',
        ],
        'EnumScanner' => [
            'enums' => 'array',
            'enums.count' => 'integer',
            'enums.definitions' => 'array',
        ],
        'BladeScanner' => [
            'blade' => 'array',
            'blade.count' => 'integer',
            'blade.views' => 'array',
            'blade.layouts' => 'array',
            'blade.components' => 'array',
        ],
        'LivewireScanner' => [
            'livewire' => 'array',
            'livewire.components' => 'array',
        ],
        'PackageScanner' => [
            'packages' => 'array',
            'packages.count' => 'integer',
            'packages.items' => 'array',
            'packages.items.*.name' => 'string',
            'packages.items.*.version' => 'string',
            'packages.items.*.category' => 'string',
        ],
        'FormRequestScanner' => [
            'form_requests' => 'array',
            'form_requests.count' => 'integer',
            'form_requests.items' => 'array',
        ],
        'HelperScanner' => [
            'helpers' => 'array',
            'helpers.count' => 'integer',
            'helpers.files' => 'array',
        ],
        'RepositoryScanner' => [
            'repositories' => 'array',
            'repositories.count' => 'integer',
            'repositories.items' => 'array',
        ],
        'TraitScanner' => [
            'traits' => 'array',
            'traits.count' => 'integer',
            'traits.items' => 'array',
        ],
        'APIScanner' => [
            'api' => 'array',
            'api.resources' => 'array',
            'api.controllers' => 'array',
        ],
        'ConfigScanner' => [
            'configuration' => 'array',
        ],
    ];

    /**
     * Validate scanner output against its schema.
     *
     * @param string $scannerName The name of the scanner
     * @param array $output The output data from the scanner
     * @return array<int, array{field: string, expected: string, actual: string, path: string}>
     */
    public static function validate(string $scannerName, array $output): array
    {
        $errors = [];
        $schema = self::$scannerSchemas[$scannerName] ?? [];

        if (empty($schema)) {
            // Unknown scanner - just check top-level types are arrays
            foreach ($output as $key => $value) {
                if (!is_array($value)) {
                    $errors[] = [
                        'field' => $key,
                        'expected' => 'array',
                        'actual' => gettype($value),
                        'path' => $scannerName . ':' . $key,
                    ];
                }
            }
            return $errors;
        }

        foreach ($schema as $fieldPath => $expectedType) {
            $actualValue = self::getValueAtPath($output, $fieldPath);

            if ($actualValue === self::$NOT_FOUND) {
                $errors[] = [
                    'field' => $fieldPath,
                    'expected' => $expectedType,
                    'actual' => 'missing',
                    'path' => $scannerName . ':' . $fieldPath,
                ];
                continue;
            }

            // Handle wildcard paths (e.g., items.*.name)
            if (str_contains($fieldPath, '.*.')) {
                self::validateWildcard($errors, $output, $fieldPath, $expectedType, $scannerName);
                continue;
            }

            // Check type
            $actualType = self::normalizeType($actualValue);
            if (!self::typeMatches($expectedType, $actualType)) {
                $errors[] = [
                    'field' => $fieldPath,
                    'expected' => $expectedType,
                    'actual' => $actualType,
                    'path' => $scannerName . ':' . $fieldPath,
                ];
            }
        }

        return $errors;
    }

    /**
     * Validate all known scanners against their schemas.
     *
     * @param array<string, array> $scannerOutputs Map of scanner name => output
     * @return array<string, array<int, array>> Scanner name => list of validation errors
     */
    public static function validateAll(array $scannerOutputs): array
    {
        $allErrors = [];

        foreach ($scannerOutputs as $scannerName => $output) {
            $errors = self::validate($scannerName, $output);
            if (!empty($errors)) {
                $allErrors[$scannerName] = $errors;
            }
        }

        return $allErrors;
    }

    /**
     * Get a validation report string from errors.
     *
     * @param array<string, array<int, array>> $allErrors
     * @return string
     */
    public static function formatReport(array $allErrors): string
    {
        if (empty($allErrors)) {
            return 'All scanner outputs validated successfully.';
        }

        $lines = [];
        $lines[] = '=== Scanner Schema Validation Report ===';
        $lines[] = '';
        $lines[] = 'Warnings: ' . array_sum(array_map('count', $allErrors));
        $lines[] = '';

        foreach ($allErrors as $scanner => $errors) {
            $lines[] = "Scanner: {$scanner}";
            $lines[] = str_repeat('-', strlen($scanner) + 8);
            foreach ($errors as $error) {
                $lines[] = "  Field:    {$error['field']}";
                $lines[] = "  Expected: {$error['expected']}";
                $lines[] = "  Actual:   {$error['actual']}";
                if (isset($error['message'])) {
                    $lines[] = "  Message:  {$error['message']}";
                }
                $lines[] = '';
            }
        }

        $lines[] = '=== End Report ===';

        return implode("\n", $lines);
    }

    /**
     * Add or override a scanner schema.
     */
    public static function setSchema(string $scannerName, array $schema): void
    {
        self::$scannerSchemas[$scannerName] = $schema;
    }

    /**
     * Get the schema for a scanner.
     */
    public static function getSchema(string $scannerName): array
    {
        return self::$scannerSchemas[$scannerName] ?? [];
    }

    // --- Internal helpers ---

    private static mixed $NOT_FOUND = '__NOT_FOUND__';

    private static function getValueAtPath(array $data, string $path): mixed
    {
        // Handle wildcard paths by just checking if parent exists
        if (str_contains($path, '.*.')) {
            $parts = explode('.*.', $path, 2);
            $parentPath = $parts[0];
            $parent = self::getValueAtPathInner($data, $parentPath);
            if ($parent === self::$NOT_FOUND || !is_array($parent)) {
                return self::$NOT_FOUND;
            }

            // Check if at least one item exists
            if (empty($parent)) {
                return self::$NOT_FOUND;
            }

            return $parent;
        }

        return self::getValueAtPathInner($data, $path);
    }

    private static function getValueAtPathInner(array $data, string $path): mixed
    {
        $parts = explode('.', $path);
        $current = $data;

        foreach ($parts as $part) {
            if (!is_array($current) || !array_key_exists($part, $current)) {
                return self::$NOT_FOUND;
            }
            $current = $current[$part];
        }

        return $current;
    }

    private static function validateWildcard(array &$errors, array $data, string $fieldPath, string $expectedType, string $scannerName): void
    {
        $parts = explode('.*.', $fieldPath, 2);
        $parentPath = $parts[0];
        $childPath = $parts[1];

        $parent = self::getValueAtPathInner($data, $parentPath);
        if ($parent === self::$NOT_FOUND || !is_array($parent)) {
            $errors[] = [
                'field' => $fieldPath,
                'expected' => $expectedType,
                'actual' => 'missing',
                'path' => $scannerName . ':' . $fieldPath,
            ];
            return;
        }

        foreach ($parent as $index => $item) {
            $childValue = self::getValueAtPathInner($item, $childPath);
            if ($childValue === self::$NOT_FOUND) {
                $errors[] = [
                    'field' => "{$fieldPath} (item #{$index})",
                    'expected' => $expectedType,
                    'actual' => 'missing',
                    'path' => $scannerName . ":{$parentPath}[{$index}].{$childPath}",
                ];
                continue;
            }
            $actualType = self::normalizeType($childValue);
            if (!self::typeMatches($expectedType, $actualType)) {
                $errors[] = [
                    'field' => "{$parentPath}[{$index}].{$childPath}",
                    'expected' => $expectedType,
                    'actual' => $actualType,
                    'path' => $scannerName . ":{$parentPath}[{$index}].{$childPath}",
                ];
            }
        }
    }

    private static function normalizeType(mixed $value): string
    {
        if ($value === null) return 'null';
        if (is_bool($value)) return 'boolean';
        if (is_int($value)) return 'integer';
        if (is_float($value)) return 'float';
        if (is_string($value)) return 'string';
        if (is_array($value)) return 'array';
        if (is_object($value)) return 'object';
        return gettype($value);
    }

    private static function typeMatches(string $expected, string $actual): bool
    {
        // Handle nullable types
        if (str_contains($expected, '|')) {
            $allowed = explode('|', $expected);
            return in_array($actual, $allowed, true);
        }

        // Handle null as a valid type
        if ($expected === 'null' && $actual === 'null') {
            return true;
        }

        return $expected === $actual;
    }
}