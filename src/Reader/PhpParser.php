<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Reader;

use PhpToken;

/**
 * Lightweight AST-based PHP parser for safe source code analysis.
 *
 * Uses PHP's built-in tokenizer to parse PHP files without executing
 * any application code. Falls back to regex for complex pattern matching.
 *
 * Memory efficient: streams through tokens without building full AST.
 */
class PhpParser
{
    /**
     * Parse PHP code and return structured class metadata.
     *
     * @return array<string, mixed>
     */
    public function parse(string $contents): array
    {
        if (empty($contents)) {
            return [];
        }

        $tokens = $this->tokenize($contents);
        if (empty($tokens)) {
            return $this->fallbackParse($contents);
        }

        return [
            'namespace' => $this->extractNamespace($tokens),
            'class_name' => $this->extractClassName($tokens),
            'uses' => $this->extractUseStatements($tokens),
            'methods' => $this->extractMethods($tokens),
            'traits' => $this->extractTraitUses($tokens),
            'interfaces' => $this->extractImplementedInterfaces($tokens),
            'parent' => $this->extractParentClass($tokens),
            'constants' => $this->extractConstants($tokens),
            'properties' => $this->extractProperties($tokens),
            'is_abstract' => $this->isAbstract($tokens),
            'is_final' => $this->isFinal($tokens),
            'line_count' => substr_count($contents, "\n") + 1,
        ];
    }

    /**
     * Tokenize PHP code, handle edge cases gracefully.
     *
     * @return array<int, \PhpToken>
     */
    private function tokenize(string $contents): array
    {
        try {
            $tokens = PhpToken::tokenize($contents);
        } catch (\Throwable) {
            return [];
        }

        return $tokens;
    }

    /**
     * Extract namespace from token array.
     */
    private function extractNamespace(array $tokens): ?string
    {
        $count = count($tokens);
        for ($i = 0; $i < $count - 2; $i++) {
            if ($tokens[$i]->id === T_NAMESPACE) {
                $ns = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_NAME_QUALIFIED || $tokens[$j]->id === T_STRING) {
                        $ns .= $tokens[$j]->text;
                    } elseif ($tokens[$j]->text === '\\' && $ns !== '') {
                        $ns .= '\\';
                    } elseif ($tokens[$j]->text === ';') {
                        break;
                    }
                }
                return $ns ?: null;
            }
        }
        return null;
    }

    /**
     * Extract class name from token array.
     */
    private function extractClassName(array $tokens): ?string
    {
        $count = count($tokens);
        for ($i = 0; $i < $count - 2; $i++) {
            if ($tokens[$i]->id === T_CLASS && $tokens[$i + 1]->id === T_WHITESPACE) {
                for ($j = $i + 2; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_STRING) {
                        return $tokens[$j]->text;
                    }
                    if ($tokens[$j]->id !== T_WHITESPACE) {
                        break;
                    }
                }
            }
        }
        if ($this->hasInterface($tokens)) {
            return $this->extractInterfaceName($tokens);
        }
        return null;
    }

    /**
     * Check if file contains interface declaration.
     */
    private function hasInterface(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($token->id === T_INTERFACE) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract interface name.
     */
    private function extractInterfaceName(array $tokens): ?string
    {
        $count = count($tokens);
        for ($i = 0; $i < $count - 2; $i++) {
            if ($tokens[$i]->id === T_INTERFACE) {
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_STRING) {
                        return $tokens[$j]->text;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Extract use/import statements.
     *
     * @return array<int, string>
     */
    private function extractUseStatements(array $tokens): array
    {
        $uses = [];
        $count = count($tokens);
        for ($i = 0; $i < $count - 2; $i++) {
            if ($tokens[$i]->id === T_USE && !$this->isInsideClass($tokens, $i)) {
                $use = '';
                $braces = false;
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->text === '{') {
                        $braces = true;
                        continue;
                    }
                    if ($tokens[$j]->text === '}') {
                        $braces = false;
                        continue;
                    }
                    if ($tokens[$j]->text === ';' && !$braces) {
                        if (!empty(trim($use))) {
                            $uses[] = trim($use);
                        }
                        break;
                    }
                    if ($tokens[$j]->id === T_WHITESPACE && $use === '') {
                        continue;
                    }
                    $use .= $tokens[$j]->text;
                }
            }
        }
        return array_values(array_filter(array_map('trim', $uses)));
    }

    /**
     * Check if a T_USE token is inside a class definition.
     */
    private function isInsideClass(array $tokens, int $position): bool
    {
        $depth = 0;
        for ($i = 0; $i < $position; $i++) {
            if ($tokens[$i]->id === T_CLASS && $depth === 0) {
                return true;
            }
            if ($tokens[$i]->text === '{') {
                $depth++;
            } elseif ($tokens[$i]->text === '}') {
                $depth--;
            }
        }
        return false;
    }

    /**
     * Extract method declarations.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractMethods(array $tokens): array
    {
        $methods = [];
        $count = count($tokens);
        for ($i = 0; $i < $count - 4; $i++) {
            if ($tokens[$i]->text === 'function') {
                $visibility = 'public';
                $isStatic = false;
                $isAbstract = false;

                // Look backwards for visibility/static/abstract
                for ($k = $i - 1; $k >= max(0, $i - 5); $k--) {
                    if ($tokens[$k]->id === T_PUBLIC || $tokens[$k]->text === 'public') {
                        $visibility = 'public';
                    } elseif ($tokens[$k]->id === T_PROTECTED || $tokens[$k]->text === 'protected') {
                        $visibility = 'protected';
                    } elseif ($tokens[$k]->id === T_PRIVATE || $tokens[$k]->text === 'private') {
                        $visibility = 'private';
                    } elseif ($tokens[$k]->id === T_STATIC) {
                        $isStatic = true;
                    } elseif ($tokens[$k]->id === T_ABSTRACT) {
                        $isAbstract = true;
                    }
                    if ($tokens[$k]->id === T_CLASS || $tokens[$k]->text === ')' || $tokens[$k]->text === ';') {
                        break;
                    }
                }

                // Get method name
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_STRING && preg_match('/^[a-zA-Z_]/', $tokens[$j]->text)) {
                        $name = $tokens[$j]->text;

                        // Extract parameters
                        $params = [];
                        for ($p = $j + 1; $p < $count; $p++) {
                            if ($tokens[$p]->text === '(') {
                                $params = $this->extractFunctionParams($tokens, $p);
                                break;
                            }
                        }

                        // Extract return type
                        $returnType = null;
                        for ($r = $j + 1; $r < $count; $r++) {
                            if ($tokens[$r]->text === ')') {
                                for ($rt = $r + 1; $rt < $count; $rt++) {
                                    if ($tokens[$rt]->id === T_WHITESPACE) continue;
                                    if ($tokens[$rt]->text === ':') {
                                        for ($rf = $rt + 1; $rf < $count; $rf++) {
                                            if ($tokens[$rf]->id === T_WHITESPACE) continue;
                                            if ($tokens[$rf]->text === '{' || $tokens[$rf]->text === ';') break;
                                            $returnType = ($returnType ?? '') . $tokens[$rf]->text;
                                        }
                                    }
                                    break;
                                }
                                break;
                            }
                        }

                        $methods[] = [
                            'name' => $name,
                            'visibility' => $visibility,
                            'static' => $isStatic,
                            'abstract' => $isAbstract,
                            'params' => $params,
                            'return_type' => $returnType ? trim($returnType) : null,
                        ];
                        break;
                    }
                }
            }
        }
        return $methods;
    }

    /**
     * Extract function parameters from position after '('.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractFunctionParams(array $tokens, int $openParenPos): array
    {
        $params = [];
        $depth = 1;
        $current = '';
        $currentType = null;
        $hasType = false;

        for ($i = $openParenPos + 1; $i < count($tokens); $i++) {
            if ($tokens[$i]->text === '(') {
                $depth++;
                $current .= '(';
                continue;
            }
            if ($tokens[$i]->text === ')') {
                $depth--;
                if ($depth === 0) {
                    if (!empty(trim($current))) {
                        $params[] = $this->parseParam(trim($current));
                    }
                    break;
                }
                $current .= ')';
                continue;
            }
            if ($tokens[$i]->text === ',' && $depth === 1) {
                if (!empty(trim($current))) {
                    $params[] = $this->parseParam(trim($current));
                }
                $current = '';
                continue;
            }
            $current .= $tokens[$i]->text;
        }

        return $params;
    }

    /**
     * Parse a single parameter string into type and name.
     *
     * @return array<string, mixed>
     */
    private function parseParam(string $param): array
    {
        $result = ['type' => null, 'name' => null, 'default' => null, 'nullable' => false];

        // Check nullable
        if (str_starts_with($param, '?')) {
            $result['nullable'] = true;
            $param = substr($param, 1);
        }

        // Check default value
        if (str_contains($param, '=')) {
            $parts = explode('=', $param, 2);
            $param = trim($parts[0]);
            $default = trim($parts[1]);
            $result['default'] = $default;
        }

        // Extract type and name
        if (preg_match('/^([\w\\\\\[\]]+)\s+\$(\w+)$/', $param, $m)) {
            $result['type'] = $m[1];
            $result['name'] = $m[2];
        } elseif (preg_match('/^\$(\w+)$/', $param, $m)) {
            $result['name'] = $m[1];
        }

        return $result;
    }

    /**
     * Extract trait use statements inside classes.
     *
     * @return array<int, string>
     */
    private function extractTraitUses(array $tokens): array
    {
        $traits = [];
        $count = count($tokens);
        $inClass = false;
        $braceDepth = 0;

        for ($i = 0; $i < $count; $i++) {
            if ($tokens[$i]->id === T_CLASS) {
                $inClass = true;
            }
            if ($inClass && $tokens[$i]->text === '{') {
                $braceDepth++;
            }
            if ($inClass && $tokens[$i]->text === '}') {
                $braceDepth--;
                if ($braceDepth === 0) {
                    $inClass = false;
                }
            }
            if ($inClass && $tokens[$i]->id === T_USE) {
                $use = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->text === ';') {
                        break;
                    }
                    $use .= $tokens[$j]->text;
                }
                // Split by comma for multiple traits
                $parts = explode(',', $use);
                foreach ($parts as $part) {
                    $trait = trim(preg_replace('/\s+/', ' ', $part));
                    if (!empty($trait)) {
                        $traits[] = ltrim($trait, '\\');
                    }
                }
            }
        }

        return $traits;
    }

    /**
     * Extract implemented interfaces.
     *
     * @return array<int, string>
     */
    private function extractImplementedInterfaces(array $tokens): array
    {
        $interfaces = [];
        $count = count($tokens);
        for ($i = 0; $i < $count - 2; $i++) {
            if ($tokens[$i]->id === T_IMPLEMENTS) {
                $impl = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_WHITESPACE && empty($impl)) {
                        continue;
                    }
                    if ($tokens[$j]->text === '{') {
                        break;
                    }
                    if ($tokens[$j]->text === ',') {
                        $interfaces[] = trim($impl);
                        $impl = '';
                        continue;
                    }
                    $impl .= $tokens[$j]->text;
                }
                if (!empty(trim($impl))) {
                    $interfaces[] = trim($impl);
                }
                break;
            }
        }
        return $interfaces;
    }

    /**
     * Extract parent class name.
     */
    private function extractParentClass(array $tokens): ?string
    {
        $count = count($tokens);
        for ($i = 0; $i < $count - 2; $i++) {
            if ($tokens[$i]->id === T_EXTENDS) {
                $parent = '';
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_WHITESPACE && empty($parent)) {
                        continue;
                    }
                    if ($tokens[$j]->text === '{' || $tokens[$j]->id === T_IMPLEMENTS) {
                        break;
                    }
                    $parent .= $tokens[$j]->text;
                }
                return trim($parent) ?: null;
            }
        }
        return null;
    }

    /**
     * Extract class constants.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractConstants(array $tokens): array
    {
        $constants = [];
        $count = count($tokens);
        for ($i = 0; $i < $count - 3; $i++) {
            if ($tokens[$i]->id === T_CONST) {
                $name = null;
                $value = null;
                for ($j = $i + 1; $j < $count; $j++) {
                    if ($tokens[$j]->id === T_WHITESPACE) continue;
                    if ($tokens[$j]->id === T_STRING) {
                        $name = $tokens[$j]->text;
                        // Get value
                        for ($k = $j + 1; $k < $count; $k++) {
                            if ($tokens[$k]->id === T_WHITESPACE) continue;
                            if ($tokens[$k]->text === '=') {
                                for ($v = $k + 1; $v < $count; $v++) {
                                    if ($tokens[$v]->text === ';') break;
                                    $value = ($value ?? '') . $tokens[$v]->text;
                                }
                            }
                            break;
                        }
                        break;
                    }
                    break;
                }
                if ($name) {
                    $constants[] = ['name' => $name, 'value' => trim($value ?? '')];
                }
            }
        }
        return $constants;
    }

    /**
     * Extract class properties.
     *
     * @return array<int, array<string, mixed>>
     */
    private function extractProperties(array $tokens): array
    {
        $properties = [];
        $count = count($tokens);
        for ($i = 0; $i < $count - 4; $i++) {
            if ($tokens[$i]->id === T_VARIABLE) {
                $visibility = null;
                $isStatic = false;
                $type = null;

                for ($k = $i - 1; $k >= max(0, $i - 6); $k--) {
                    if ($tokens[$k]->id === T_PUBLIC) {
                        $visibility = 'public';
                    } elseif ($tokens[$k]->id === T_PROTECTED) {
                        $visibility = 'protected';
                    } elseif ($tokens[$k]->id === T_PRIVATE) {
                        $visibility = 'private';
                    } elseif ($tokens[$k]->id === T_STATIC) {
                        $isStatic = true;
                    } elseif ($tokens[$k]->id === T_STRING && !$isStatic) {
                        // Potential type hint for PHP 7.4+ typed properties
                        if (in_array($tokens[$k]->text, ['int', 'string', 'float', 'bool', 'array', 'mixed', 'null', 'void', 'callable', 'iterable', 'object'])) {
                            $type = $tokens[$k]->text;
                        }
                    } elseif ($tokens[$k]->text === ',') {
                        break;
                    }
                }

                if ($visibility !== null) {
                    $properties[] = [
                        'name' => $tokens[$i]->text,
                        'visibility' => $visibility,
                        'static' => $isStatic,
                        'type' => $type,
                    ];
                }
            }
        }
        return $properties;
    }

    /**
     * Check if class is abstract.
     */
    private function isAbstract(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($token->id === T_ABSTRACT) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if class is final.
     */
    private function isFinal(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($token->id === T_FINAL) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fallback regex-based parsing when tokenizer is unavailable.
     *
     * @return array<string, mixed>
     */
    private function fallbackParse(string $contents): array
    {
        $namespace = null;
        if (preg_match('/^namespace\s+([^;]+);/m', $contents, $m)) {
            $namespace = $m[1];
        }

        $className = null;
        if (preg_match('/^(?:abstract\s+|final\s+)?class\s+(\w+)/m', $contents, $m)) {
            $className = $m[1];
        } elseif (preg_match('/^interface\s+(\w+)/m', $contents, $m)) {
            $className = $m[1];
        }

        $uses = [];
        if (preg_match_all('/^use\s+([^;]+);/m', $contents, $m)) {
            foreach ($m[1] as $use) {
                $uses[] = trim($use);
            }
        }

        $methods = [];
        if (preg_match_all('/(?:public|protected|private|static|abstract)?\s*(?:public|protected|private)?\s*(?:static)?\s*function\s+(\w+)\s*\(/', $contents, $m)) {
            foreach ($m[1] as $method) {
                if (!in_array($method, ['__construct', '__destruct', '__call', '__callStatic'])) {
                    $methods[] = ['name' => $method, 'visibility' => 'public', 'static' => false, 'abstract' => false, 'params' => []];
                }
            }
        }

        return [
            'namespace' => $namespace,
            'class_name' => $className,
            'uses' => $uses,
            'methods' => $methods,
            'traits' => [],
            'interfaces' => [],
            'parent' => null,
            'constants' => [],
            'properties' => [],
            'is_abstract' => false,
            'is_final' => false,
            'line_count' => substr_count($contents, "\n") + 1,
        ];
    }
}