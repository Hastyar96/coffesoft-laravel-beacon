<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Context;

/**
 * Central data model for the Beacon context.
 *
 * Framework-agnostic container that holds all
 * scanned project metadata in a structured way.
 */
class Context
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @var array<int, array<string, mixed>>
     */
    private array $validationWarnings = [];

    /**
     * @var array<string, string>
     */
    private static array $schema = [
        'framework' => 'array',
        'incremental_scan' => 'boolean',
        'cache_stats' => 'array',
        'models' => 'array',
        'controllers' => 'array',
        'routes' => 'array',
        'migrations' => 'array',
        'database' => 'array',
        'statistics' => 'array',
        'configuration' => 'array',
        'services' => 'array',
        'repositories' => 'array',
        'form_requests' => 'array',
        'middleware' => 'array',
        'policies' => 'array',
        'events' => 'array',
        'jobs' => 'array',
        'notifications' => 'array',
        'mail' => 'array',
        'traits' => 'array',
        'enums' => 'array',
        'helpers' => 'array',
        'livewire' => 'array',
        'blade' => 'array',
        'api' => 'array',
        'queue' => 'array',
        'storage' => 'array',
        'packages' => 'array',
        'modules' => 'array',
        'architecture' => 'array',
        'security' => 'array',
        'performance' => 'array',
        'business_rules' => 'array',
        'project_graph' => 'array',
        'ai_summaries' => 'array',
        'folder_tree' => 'array',
        'enhanced_statistics' => 'array',
        'database_intelligence' => 'array',
        'route_intelligence' => 'array',
        'ai_context' => 'array',
        'workflows' => 'array',
        'entry_points' => 'array',
        'dependency_graph' => 'array',
        'features' => 'array',
        'developer_guide' => 'array',
        'impact_map' => 'array',
        'ai_prompts' => 'array',
        'generated_at' => 'string',
        'beacon_version' => 'string',
        'controller_groups' => 'array',
        'total_modules' => 'integer',
    ];

    /**
     * Set a value at the given key.
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Get a value by key with dot notation support.
     *
     * For example, `get('models.items')` traverses into
     * `$data['models']['items']`.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (! str_contains($key, '.')) {
            return $this->data[$key] ?? $default;
        }

        $segments = explode('.', $key);
        $value = $this->data;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    /**
     * Determine if a key exists with dot notation support.
     */
    public function has(string $key): bool
    {
        if (! str_contains($key, '.')) {
            return array_key_exists($key, $this->data);
        }

        $segments = explode('.', $key);
        $value = $this->data;

        foreach ($segments as $segment) {
            if (! is_array($value) || ! array_key_exists($segment, $value)) {
                return false;
            }

            $value = $value[$segment];
        }

        return true;
    }

    /**
     * Merge the given data into the context.
     *
     * Uses array_replace_recursive instead of array_merge_recursive
     * to prevent PHP's "Array to string conversion" errors.
     *
     * FIX: array_merge_recursive converts duplicate keys to arrays,
     * which silently corrupts data types. When downstream code
     * uses these values in string contexts, PHP crashes.
     *
     * array_replace_recursive safely overwrites values.
     */
    public function merge(array $data): self
    {
        // First, validate top-level keys of incoming data
        $source = $this->detectCaller();

        $this->validateData($data, $source);
        
        /** @var array<string, mixed> $merged */
        $merged = array_replace_recursive($this->data, $data);

        $this->data = $merged;

        return $this;
    }

    /**
     * Validate incoming data against the known schema.
     * Detects potential type mismatches before they cause crashes.
     */
    private function validateData(array $data, ?string $source): void
    {
        foreach ($data as $key => $value) {
            // Check against known schema
            if (isset(self::$schema[$key])) {
                $expectedType = self::$schema[$key];
                $actualType = gettype($value);

                if ($actualType !== $expectedType) {
                    $this->addWarning([
                        'component' => $source ?? 'unknown',
                        'field' => $key,
                        'expected' => $expectedType,
                        'received' => $actualType,
                        'message' => "Schema validation: field '{$key}' should be {$expectedType}, got {$actualType}",
                    ]);
                }
            }

            // Detect array_merge_recursive corruption: values that became arrays
            if (isset($this->data[$key]) && is_array($this->data[$key]) && !is_array($value)) {
                // value was scalar, existing is array - would merge into array
                if (!isset(self::$schema[$key])) {
                    $this->addWarning([
                        'component' => $source ?? 'unknown',
                        'field' => $key,
                        'expected' => 'array',
                        'received' => gettype($value),
                        'message' => "Type conflict on '{$key}': existing is array, new value is " . gettype($value),
                    ]);
                }
            }

            if (isset($this->data[$key]) && !is_array($this->data[$key]) && is_array($value)) {
                // existing is scalar, new value is array
                $this->addWarning([
                    'component' => $source ?? 'unknown',
                    'field' => $key,
                    'expected' => gettype($this->data[$key]),
                    'received' => 'array',
                    'message' => "Type conflict on '{$key}': existing is " . gettype($this->data[$key]) . ", new value is array",
                ]);
            }

            // Recursively validate nested array structures for consistency
            if (is_array($value) && isset($this->data[$key]) && is_array($this->data[$key])) {
                $this->validateNestedTypes($key, $this->data[$key], $value, $source);
            }
        }
    }

    /**
     * Detect nested type conflicts that would crash string interpolation.
     */
    private function validateNestedTypes(string $prefix, array $existing, array $incoming, ?string $source): void
    {
        foreach ($incoming as $key => $value) {
            $fullKey = $prefix . '.' . $key;

            if (isset($existing[$key])) {
                $existingType = gettype($existing[$key]);
                $incomingType = gettype($value);

                // Mixed scalar/array at same key = corruption risk
                if ($existingType !== $incomingType && $existingType !== 'array' && $incomingType !== 'array') {
                    // Both scalar but different types
                    continue; // This is fine, replace_recursive handles it
                }

                // Check if existing was corrupted by previous array_merge_recursive
                if (is_array($existing[$key]) && !is_array($value)) {
                    $this->addWarning([
                        'component' => $source ?? 'unknown',
                        'field' => $fullKey,
                        'expected' => 'scalar',
                        'received' => 'array',
                        'message' => "Potential corruption: '{$fullKey}' was turned into array by array_merge_recursive",
                    ]);
                }
            }

            if (is_array($value)) {
                $existingChild = $existing[$key] ?? [];
                if (is_array($existingChild)) {
                    $this->validateNestedTypes($fullKey, $existingChild, $value, $source);
                }
            }
        }
    }

    /**
     * Add a validation warning that can be retrieved later.
     */
    private function addWarning(array $warning): void
    {
        $this->validationWarnings[] = $warning;
    }

    /**
     * Get all validation warnings collected during the scan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getValidationWarnings(): array
    {
        return $this->validationWarnings;
    }

    /**
     * Return all context data as an array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        // Final validation pass before returning
        $this->runFinalValidation();
        return $this->data;
    }

    /**
     * Detect which component is calling merge by examining the call stack.
     */
    private function detectCaller(): ?string
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);
        foreach ($trace as $frame) {
            if (isset($frame['class']) && $frame['class'] !== self::class) {
                $parts = explode('\\', $frame['class']);
                return end($parts);
            }
        }
        return null;
    }

    /**
     * Final validation pass - check every value in the data array
     * to detect any corrupted types that would cause crashes.
     */
    private function runFinalValidation(): void
    {
        // Check for values that are arrays where they shouldn't be
        // by scanning the expected structure
        $this->validateStructure($this->data, '');
    }

    /**
     * Recursively validate the data structure.
     *
     * @param array<string, mixed> $data
     * @param array<int, string> $path
     */
    private function validateStructure(array $data, string $prefix): void
    {
        foreach ($data as $key => $value) {
            $fullKey = $prefix ? $prefix . '.' . $key : $key;

            if (is_string($value) || is_int($value) || is_float($value) || is_bool($value) || is_null($value)) {
                // Scalar values are always safe
                continue;
            }

            if (is_array($value)) {
                $this->validateStructure($value, $fullKey);
                continue;
            }

            // If we get here, the type is unexpected (e.g., object, resource)
            $this->addWarning([
                'field' => $fullKey,
                'expected' => 'scalar|array',
                'received' => gettype($value),
                'message' => "Unexpected type '" . gettype($value) . "' at '{$fullKey}'",
            ]);
        }
    }

    /**
     * Get a validation report as a formatted string.
     */
    public function getValidationReport(): string
    {
        if (empty($this->validationWarnings)) {
            return '';
        }

        $report = [];
        $report[] = '=== Beacon Validation Warnings ===';
        $report[] = '';

        foreach ($this->validationWarnings as $i => $warning) {
            $report[] = "Warning #" . ($i + 1);
            $report[] = "  Component: " . ($warning['component'] ?? 'unknown');
            $report[] = "  Field:     " . ($warning['field'] ?? 'unknown');
            $report[] = "  Expected:  " . ($warning['expected'] ?? 'unknown');
            $report[] = "  Received:  " . ($warning['received'] ?? 'unknown');
            $report[] = "  Message:   " . ($warning['message'] ?? 'No details');
            $report[] = '';
        }

        $report[] = 'Total warnings: ' . count($this->validationWarnings);
        $report[] = '=== End Report ===';

        return implode("\n", $report);
    }

    /**
     * Check if there are validation warnings.
     */
    public function hasValidationWarnings(): bool
    {
        return !empty($this->validationWarnings);
    }

    /**
     * Clear all validation warnings (e.g., for retry).
     */
    public function clearValidationWarnings(): void
    {
        $this->validationWarnings = [];
    }

    /**
     * Add a named schema entry.
     */
    public static function addSchema(string $key, string $type): void
    {
        self::$schema[$key] = $type;
    }
}