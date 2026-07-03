<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Context;

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
     * Set a value at the given key.
     */
    public function set(string $key, mixed $value): self
    {
        $this->data[$key] = $value;

        return $this;
    }

    /**
     * Get a value by key with an optional default.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * Determine if a key exists.
     */
    public function has(string $key): bool
    {
        return array_key_exists($key, $this->data);
    }

    /**
     * Merge the given data into the context.
     */
    public function merge(array $data): self
    {
        /** @var array<string, mixed> $merged */
        $merged = array_merge_recursive($this->data, $data);

        $this->data = $merged;

        return $this;
    }

    /**
     * Return all context data as an array.
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->data;
    }
}