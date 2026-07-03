<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;

/**
 * Scanner that reads composer.json and exposes useful metadata.
 *
 * Only returns explicitly allowed keys. Silently handles
 * missing files and invalid JSON.
 */
class ComposerScanner implements Scanner
{
    /**
     * Read composer.json and return curated metadata.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $path = base_path('composer.json');

        if (! file_exists($path)) {
            return [];
        }

        $contents = file_get_contents($path);

        if ($contents === false) {
            return [];
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($contents, true);

        if (! is_array($data)) {
            return [];
        }

        return [
            'composer' => [
                'name' => $data['name'] ?? null,
                'description' => $data['description'] ?? null,
                'type' => $data['type'] ?? null,
                'license' => $data['license'] ?? null,
                'php' => $data['require']['php'] ?? null,
                'laravel' => $data['require']['laravel/framework']
                    ?? $data['require']['laravel/laravel']
                    ?? null,
                'require' => $data['require'] ?? [],
                'require_dev' => $data['require-dev'] ?? [],
                'autoload' => $data['autoload'] ?? [],
            ],
        ];
    }
}