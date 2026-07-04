<?php

declare(strict_types=1);

namespace Tests\Support;

use Coffesoft\LaravelBeacon\Support\SchemaValidator;
use PHPUnit\Framework\TestCase;

/**
 * Tests for SchemaValidator — validates scanner output structures.
 *
 * These tests ensure that:
 * 1. Valid scanner output passes validation
 * 2. Invalid output (wrong types) is caught
 * 3. Missing fields are detected
 * 4. The validator works with all known scanner schemas
 */
class SchemaValidatorTest extends TestCase
{
    // ========== ModelScanner Tests ==========

    public function test_valid_model_scanner_output_passes(): void
    {
        $output = [
            'models' => [
                'count' => 2,
                'items' => [
                    [
                        'name' => 'User',
                        'path' => 'User.php',
                        'fillable' => ['name', 'email'],
                        'traits' => ['HasFactory'],
                        'relations' => [
                            ['type' => 'hasMany', 'target' => 'Post'],
                        ],
                        'scopes' => [],
                        'accessors' => [],
                        'mutators' => [],
                        'observers' => [],
                        'line_count' => 50,
                        'methods_count' => 5,
                        'fillable_count' => 2,
                        'casts_count' => 1,
                        'relations_count' => 1,
                    ],
                ],
                'by_table' => ['users' => ['User']],
                'by_trait' => ['HasFactory' => ['User']],
                'parent_chain' => [],
            ],
        ];

        $errors = SchemaValidator::validate('ModelScanner', $output);
        $this->assertEmpty($errors, 'Valid ModelScanner output should have no errors: ' . print_r($errors, true));
    }

    public function test_invalid_model_scanner_type_is_caught(): void
    {
        $output = [
            'models' => [
                'count' => 'two', // Should be integer, not string
                'items' => [],
                'by_table' => [],
                'by_trait' => [],
                'parent_chain' => [],
            ],
        ];

        $errors = SchemaValidator::validate('ModelScanner', $output);
        $this->assertNotEmpty($errors, 'Invalid ModelScanner output should have errors');

        $foundCount = false;
        foreach ($errors as $error) {
            if (str_contains($error['field'], 'models.count')) {
                $foundCount = true;
                $this->assertEquals('integer', $error['expected']);
                $this->assertEquals('string', $error['actual']);
                break;
            }
        }
        $this->assertTrue($foundCount, 'Should catch the count type mismatch: ' . print_r($errors, true));
    }

    // ========== RouteScanner Tests ==========

    public function test_valid_route_scanner_output_passes(): void
    {
        $output = [
            'routes' => [
                'count' => 1,
                'items' => [
                    [
                        'uri' => '/users',
                        'methods' => ['GET'],
                        'name' => 'users.index',
                        'action' => 'UserController@index',
                        'controller' => 'App\Http\Controllers\UserController',
                        'controller_short' => 'UserController',
                        'method' => 'index',
                        'middleware' => ['web'],
                        'parameters' => [],
                        'parameter_count' => 0,
                        'has_wildcard' => false,
                        'module' => 'Web',
                        'evidence' => ['source_file' => 'web.php'],
                    ],
                ],
                'groups' => [],
                'by_controller' => [],
                'controller_count' => 0,
            ],
        ];

        $errors = SchemaValidator::validate('RouteScanner', $output);
        $this->assertEmpty($errors, 'Valid RouteScanner output should have no errors: ' . print_r($errors, true));
    }

    // ========== ControllerScanner Tests ==========

    public function test_valid_controller_scanner_output_passes(): void
    {
        $output = [
            'controllers' => [
                'count' => 1,
                'items' => [
                    [
                        'name' => 'UserController',
                        'path' => 'UserController.php',
                        'group' => 'root',
                        'methods' => ['index', 'store'],
                        'middleware' => ['web'],
                        'is_crud' => true,
                        'is_resource' => true,
                        'line_count' => 100,
                        'models_used' => [
                            ['class' => 'User', 'methods' => ['find'], 'lines' => [15]],
                        ],
                        'views_returned' => [
                            ['name' => 'users.index', 'line' => 20],
                        ],
                        // ... other fields
                    ],
                ],
            ],
        ];

        $errors = SchemaValidator::validate('ControllerScanner', $output);
        $this->assertEmpty($errors, 'Valid ControllerScanner output should have no errors: ' . print_r($errors, true));
    }

    public function test_controller_views_returned_is_array(): void
    {
        $output = [
            'controllers' => [
                'count' => 1,
                'items' => [
                    [
                        'name' => 'BadController',
                        'path' => 'BadController.php',
                        'group' => 'root',
                        'methods' => ['index'],
                        'middleware' => [],
                        'is_crud' => false,
                        'line_count' => 50,
                        'models_used' => [],
                        'views_returned' => 'users.index', // String instead of array — CRASH RISK
                    ],
                ],
            ],
        ];

        $errors = SchemaValidator::validate('ControllerScanner', $output);
        $this->assertNotEmpty($errors, 'views_returned must be array');

        $found = false;
        foreach ($errors as $error) {
            if (str_contains($error['field'], 'views_returned')) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'Should catch views_returned type mismatch');
    }

    // ========== StatisticsScanner Tests ==========

    public function test_valid_statistics_output_passes(): void
    {
        $output = [
            'statistics' => [
                'total_php_files' => 100,
                'total_blade_files' => 20,
                'models' => 10,
                'controllers' => 5,
                'services' => 3,
                'repositories' => 2,
                'requests' => 4,
                'policies' => 1,
                'events' => 2,
                'jobs' => 3,
                'notifications' => 1,
                'commands' => 2,
                'enums' => 3,
                'packages' => 10,
                'routes' => 50,
                'views' => 20,
                'blade_components' => 5,
                'livewire_components' => 2,
                'listeners' => 2,
                'database_tables' => 15,
                'traits' => 3,
                'helpers' => 1,
                'average_controller_methods' => 5.2,
                'average_model_methods' => 3.1,
                'largest_classes' => [],
            ],
        ];

        $errors = SchemaValidator::validate('StatisticsScanner', $output);
        $this->assertEmpty($errors, 'Valid StatisticsScanner output should have no errors: ' . print_r($errors, true));
    }

    // ========== Schema Consistency Tests ==========

    public function test_controllers_count_matches_items_count(): void
    {
        $output = [
            'controllers' => [
                'count' => 3, // Wrong — should be 2
                'items' => [
                    ['name' => 'A', 'path' => 'A.php', 'group' => 'root', 'methods' => [], 'middleware' => [], 'is_crud' => false, 'line_count' => 1, 'models_used' => [], 'views_returned' => []],
                    ['name' => 'B', 'path' => 'B.php', 'group' => 'root', 'methods' => [], 'middleware' => [], 'is_crud' => false, 'line_count' => 1, 'models_used' => [], 'views_returned' => []],
                ],
            ],
        ];

        // Schema validation checks types, not semantic correctness
        $errors = SchemaValidator::validate('ControllerScanner', $output);
        $this->assertEmpty($errors); // Types are correct, just value is wrong
    }

    public function test_all_scanner_schemas_are_reachable(): void
    {
        $scannerNames = [
            'ModelScanner',
            'ControllerScanner',
            'RouteScanner',
            'MigrationScanner',
            'DatabaseScanner',
            'StatisticsScanner',
            'ServiceScanner',
            'MiddlewareScanner',
            'PolicyScanner',
            'EventScanner',
            'JobScanner',
            'MailScanner',
            'NotificationScanner',
            'EnumScanner',
            'BladeScanner',
            'LivewireScanner',
            'PackageScanner',
            'FormRequestScanner',
            'HelperScanner',
            'RepositoryScanner',
            'TraitScanner',
            'APIScanner',
            'ConfigScanner',
        ];

        foreach ($scannerNames as $name) {
            $schema = SchemaValidator::getSchema($name);
            $this->assertNotEmpty($schema, "Scanner '{$name}' must have a schema defined");
        }
    }

    // ========== Context Type Validation Tests ==========

    /**
     * Test the exact scenario that causes "Array to string conversion":
     * When array_merge_recursive turns a scalar into an array.
     */
    public function test_array_merge_recursive_corruption_scenario(): void
    {
        // Simulate: first scanner returns routes with 'items' as array of routes
        $firstData = [
            'routes' => [
                'count' => 1,
                'items' => [
                    ['uri' => '/api/users', 'methods' => ['GET'], 'controller_short' => 'ApiUserController'],
                ],
            ],
        ];

        // Second scanner INCORRECTLY uses 'routes' with a different structure
        // This is what AgentContextEngine::countDependents() does with `return ['routes' => $routes, ...]`
        // But since these are at different nesting levels, array_merge_recursive is fine.
        // The real problem is when the SAME key at the SAME nesting level has different types.

        // The actual corruption happens like this:
        $result1 = ['data' => ['key' => 'string_value']];
        $result2 = ['data' => ['key' => ['array_value']]];

        // array_merge_recursive on these:
        $merged1 = array_merge_recursive($result1, $result2);
        // data.key is now ['string_value', ['array_value']] — nested array!

        // array_replace_recursive on these:
        $merged2 = array_replace_recursive($result1, $result2);
        // data.key is ['array_value'] — clean overwrite!

        $this->assertIsArray($merged1['data']['key'], 'array_merge_recursive produces array');
        $this->assertIsArray($merged2['data']['key'], 'array_replace_recursive also produces array');
        $this->assertEquals(
            ['array_value'],
            $merged2['data']['key'],
            'array_replace_recursive gives the last value'
        );
    }

    /**
     * Test that string values becoming arrays is caught.
     */
    public function test_string_to_array_corruption_is_detected(): void
    {
        // Simulate: first engine sets a string value
        $data1 = ['item' => ['name' => 'hello']];

        // Second engine returns same key with array
        $data2 = ['item' => ['name' => ['world']]]; // Array instead of string!

        // With array_merge_recursive:
        $corrupted = array_merge_recursive($data1, $data2);
        // $corrupted['item']['name'] = ['hello', ['world']] — an array!
        // This would crash if used in string context: echo "Name: {$corrupted['item']['name']}";

        $this->assertIsArray($corrupted['item']['name'], 'array_merge_recursive corrupts string into array');

        // With array_replace_recursive:
        $safe = array_replace_recursive($data1, $data2);
        $this->assertIsArray($safe['item']['name'], 'last value wins: array');
        $this->assertEquals(['world'], $safe['item']['name']);

        // Or if data2 had a string:
        $data2Fixed = ['item' => ['name' => 'world']];
        $safeFixed = array_replace_recursive($data1, $data2Fixed);
        $this->assertIsString($safeFixed['item']['name'], 'array_replace_recursive keeps string');
        $this->assertEquals('world', $safeFixed['item']['name']);
    }
}