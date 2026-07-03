<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates comprehensive database intelligence from scanned data.
 */
class DatabaseIntelligence
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        $tables = [];

        foreach ($data['database']['tables'] ?? [] as $table) {
            $tables[$table['name']] = [
                'name' => $table['name'],
                'columns' => $table['columns'] ?? [],
                'has_timestamps' => $this->hasColumn($table, 'created_at'),
                'has_soft_deletes' => $this->hasColumn($table, 'deleted_at'),
                'has_increments' => $this->hasColumn($table, 'id'),
                'foreign_keys' => $this->getForeignKeys($table),
                'unique_constraints' => $this->getUniqueConstraints($table),
                'indexes' => $this->getIndexes($table),
                'is_pivot' => $this->isPivotTable($table['name']),
            ];
        }

        // Build relationship map
        $relationships = $this->buildModelTableRelationships($data);

        return [
            'database_intelligence' => [
                'tables' => array_values($tables),
                'table_count' => count($tables),
                'relationships' => $relationships,
            ],
        ];
    }

    private function hasColumn(array $table, string $columnName): bool
    {
        foreach ($table['columns'] ?? [] as $column) {
            if (($column['name'] ?? '') === $columnName) return true;
        }
        return false;
    }

    private function getForeignKeys(array $table): array
    {
        $fks = [];
        foreach ($table['columns'] ?? [] as $column) {
            $name = $column['name'] ?? '';
            $type = $column['type'] ?? '';

            // Standard foreign key pattern: {table}_id
            if (preg_match('/^(\w+)_id$/', $name, $m)) {
                $fks[] = [
                    'column' => $name,
                    'references' => $m[1],
                    'type' => $type,
                ];
            }

            // Explicit foreign key constraint
            if (isset($column['foreign'])) {
                $fks[] = [
                    'column' => $name,
                    'references' => $column['foreign'],
                    'type' => $type,
                    'explicit' => true,
                ];
            }
        }
        return $fks;
    }

    private function getUniqueConstraints(array $table): array
    {
        $uniques = [];
        foreach ($table['columns'] ?? [] as $column) {
            if ($column['unique'] ?? false) {
                $uniques[] = $column['name'];
            }
        }
        return $uniques;
    }

    private function getIndexes(array $table): array
    {
        $indexes = [];
        foreach ($table['columns'] ?? [] as $column) {
            if (($column['index'] ?? false) && !($column['unique'] ?? false)) {
                $indexes[] = $column['name'];
            }
        }
        return $indexes;
    }

    private function isPivotTable(string $tableName): bool
    {
        // Pivot tables often are named with singular words alphabetically ordered
        // e.g., model_model, or contain '_pivot' suffix
        if (str_contains($tableName, '_pivot') || str_ends_with($tableName, '_pivot')) {
            return true;
        }

        // Check for alphabetical ordering pattern: a_b (two singular words)
        $parts = explode('_', $tableName);
        if (count($parts) === 2 && $parts[0] !== $parts[1]) {
            return true;
        }

        return false;
    }

    private function buildModelTableRelationships(array $data): array
    {
        $relationships = [];

        foreach ($data['models']['items'] ?? [] as $model) {
            $modelName = $model['name'];
            $relations = $model['relations'] ?? [];

            foreach ($relations as $type => $count) {
                $relationships[] = [
                    'from_model' => $modelName,
                    'type' => $type,
                    'count' => $count,
                ];
            }
        }

        return $relationships;
    }
}