<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Detects business rules by analyzing code patterns, validation rules,
 * model events, and service logic.
 *
 * Never invents information — only infers from source code.
 */
class BusinessRuleDetector
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function detect(array $data): array
    {
        $rules = [];

        // Detect rules from validation rules (Form Requests)
        $validationRules = $this->extractValidationRules($data);
        foreach ($validationRules as $rule) {
            $rules[] = [
                'type' => 'validation_constraint',
                'rule' => $rule['message'],
                'source' => $rule['source'],
                'field' => $rule['field'],
            ];
        }

        // Detect rules from model events (boot methods)
        $modelEvents = $this->extractModelEvents($data);
        foreach ($modelEvents as $event) {
            $rules[] = [
                'type' => 'model_event_rule',
                'rule' => $event['message'],
                'source' => $event['source'],
                'event' => $event['event'],
            ];
        }

        // Detect rules from accessors and mutators
        $accessorRules = $this->extractAccessorRules($data);
        foreach ($accessorRules as $rule) {
            $rules[] = [
                'type' => 'computed_attribute',
                'rule' => $rule['message'],
                'source' => $rule['source'],
            ];
        }

        // Detect rules from scopes
        $scopeRules = $this->extractScopeRules($data);
        foreach ($scopeRules as $rule) {
            $rules[] = [
                'type' => 'query_constraint',
                'rule' => $rule['message'],
                'source' => $rule['source'],
            ];
        }

        // Detect rules from unique/foreign key constraints
        $dbRules = $this->extractDatabaseRules($data);
        foreach ($dbRules as $rule) {
            $rules[] = [
                'type' => 'database_constraint',
                'rule' => $rule['message'],
                'source' => $rule['source'],
            ];
        }

        return [
            'business_rules' => [
                'count' => count($rules),
                'items' => $rules,
            ],
        ];
    }

    private function extractValidationRules(array $data): array
    {
        $rules = [];
        foreach ($data['form_requests']['items'] ?? [] as $request) {
            $requestRules = $request['rules'] ?? [];
            foreach ($requestRules as $ruleDef) {
                $field = $ruleDef['field'] ?? '';
                $ruleStr = $ruleDef['rules'] ?? '';

                // Extract meaningful business rules from validation
                $constraints = [];
                if (str_contains($ruleStr, 'unique')) $constraints[] = "{$field} must be unique";
                if (str_contains($ruleStr, 'exists')) $constraints[] = "{$field} must reference an existing record";
                if (str_contains($ruleStr, 'required')) $constraints[] = "{$field} is required";
                if (str_contains($ruleStr, 'min:')) {
                    preg_match('/min:(\d+)/', $ruleStr, $m);
                    $constraints[] = "{$field} minimum value/length is {$m[1]}";
                }
                if (str_contains($ruleStr, 'max:')) {
                    preg_match('/max:(\d+)/', $ruleStr, $m);
                    $constraints[] = "{$field} maximum value/length is {$m[1]}";
                }
                if (str_contains($ruleStr, 'email')) $constraints[] = "{$field} must be a valid email";
                if (str_contains($ruleStr, 'confirmed')) $constraints[] = "{$field} must be confirmed";
                if (str_contains($ruleStr, 'in:')) $constraints[] = "{$field} must be one of: " . $this->extractInValues($ruleStr);
                if (str_contains($ruleStr, 'date')) $constraints[] = "{$field} must be a valid date";
                if (str_contains($ruleStr, 'numeric')) $constraints[] = "{$field} must be numeric";
                if (str_contains($ruleStr, 'boolean')) $constraints[] = "{$field} must be true or false";

                foreach ($constraints as $constraint) {
                    $rules[] = [
                        'message' => $constraint,
                        'source' => "{$request['name']}::rules()",
                        'field' => $field,
                    ];
                }
            }
        }
        return $rules;
    }

    private function extractModelEvents(array $data): array
    {
        $rules = [];
        foreach ($data['models']['items'] ?? [] as $model) {
            $path = $model['path'] ?? '';
            $fullPath = app_path('Models/' . $path);
            if (!file_exists($fullPath)) continue;

            $contents = file_get_contents($fullPath);

            // Check for boot() method with model events
            if (preg_match('/protected\s+static\s+function\s+boot\s*\(\s*\)\s*\{(.*?)\}/s', $contents, $m)) {
                $bootBody = $m[1];

                $events = ['creating', 'created', 'updating', 'updated', 'saving', 'saved',
                           'deleting', 'deleted', 'restoring', 'restored'];

                foreach ($events as $event) {
                    if (preg_match('/static::' . $event . '\s*\(/s', $bootBody)) {
                        $desc = ucfirst($event);
                        $rules[] = [
                            'message' => "When {$model['name']} is being {$event}: [custom logic executed in boot()]",
                            'source' => "{$model['name']}::boot()",
                            'event' => $event,
                        ];
                    }
                }
            }

            // Check for trait usage that implies rules
            $traits = $model['traits'] ?? [];
            if (in_array('SoftDeletes', $traits)) {
                $rules[] = [
                    'message' => "{$model['name']} records are soft-deleted instead of permanently removed",
                    'source' => "{$model['name']} (SoftDeletes trait)",
                    'event' => 'deleted',
                ];
            }
        }
        return $rules;
    }

    private function extractAccessorRules(array $data): array
    {
        $rules = [];
        foreach ($data['models']['items'] ?? [] as $model) {
            foreach ($model['accessors'] ?? [] as $accessor) {
                $rules[] = [
                    'message' => "{$model['name']} computes '{$accessor}' attribute dynamically",
                    'source' => "{$model['name']}::get" . ucfirst($accessor) . "Attribute()",
                ];
            }
            foreach ($model['mutators'] ?? [] as $mutator) {
                $rules[] = [
                    'message' => "{$model['name']} transforms '{$mutator}' value when setting",
                    'source' => "{$model['name']}::set" . ucfirst($mutator) . "Attribute()",
                ];
            }
        }
        return $rules;
    }

    private function extractScopeRules(array $data): array
    {
        $rules = [];
        foreach ($data['models']['items'] ?? [] as $model) {
            foreach ($model['scopes'] ?? [] as $scope) {
                $rules[] = [
                    'message' => "{$model['name']} has a query scope '{$scope}' that constrains queries",
                    'source' => "{$model['name']}::scope" . ucfirst($scope) . "()",
                ];
            }
        }
        return $rules;
    }

    private function extractDatabaseRules(array $data): array
    {
        $rules = [];
        foreach ($data['database']['tables'] ?? [] as $table) {
            foreach ($table['columns'] ?? [] as $column) {
                $name = $column['name'] ?? '';
                $type = $column['type'] ?? '';

                // Detect unique constraints
                if (($column['unique'] ?? false) || ($column['index'] ?? false)) {
                    $rules[] = [
                        'message' => "{$table['name']}.{$name} must be unique",
                        'source' => "database (unique index on {$name})",
                    ];
                }

                // Detect foreign key constraints
                if (isset($column['foreign'])) {
                    $ref = $column['foreign'];
                    $rules[] = [
                        'message' => "{$table['name']}.{$name} must reference {$ref}",
                        'source' => "database schema (foreign key constraint)",
                    ];
                }

                // Detect required fields from migration
                if (str_contains($type, 'nullable') === false && in_array($name, ['id', 'created_at', 'updated_at']) === false) {
                    // Fields that are not nullable are effectively required
                    // but we only flag this if the column has a clear type
                }
            }

            // Detect pivot tables
            if ($table['is_pivot'] ?? false) {
                $parts = explode('_', $table['name']);
                $rules[] = [
                    'message' => "{$table['name']} is a pivot table linking " . implode(' and ', $parts),
                    'source' => "database schema",
                ];
            }
        }
        return $rules;
    }

    private function extractInValues(string $ruleStr): string
    {
        preg_match('/in:([^,|]+(?:,[^,|]+)*)/', $ruleStr, $m);
        return $m[1] ?? '';
    }
}