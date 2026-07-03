<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

class FormRequestScanner
{
    public function __construct(private readonly FileReader $reader) {}

    public function scan(): array
    {
        $path = app_path('Http/Requests');
        $files = $this->reader->getPhpFiles($path);
        $items = [];

        foreach ($files as $file) {
            $contents = $this->reader->read($file->getPathname());
            $name = $this->reader->extractClassName($contents);
            if ($name === null) continue;

            $items[] = [
                'name' => $name,
                'namespace' => $this->reader->extractNamespace($contents) ?? 'App\\Http\\Requests',
                'path' => $file->getRelativePathname(),
                'rules' => $this->extractRules($contents),
                'authorize' => $this->hasAuthorize($contents),
                'messages' => $this->hasMessages($contents),
            ];
        }

        return ['form_requests' => ['count' => count($items), 'items' => $items]];
    }

    private function extractRules(string $contents): array
    {
        $rules = [];
        if (preg_match('/function\s+rules?\s*\([^)]*\)\s*:\s*array\s*\{([^}]*(?:\{[^}]*\}[^}]*)*)\}/s', $contents, $m)) {
            $body = $m[1];
            if (preg_match_all('/[\'"]([\w_]+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $body, $matches)) {
                foreach ($matches[1] as $i => $field) {
                    $rules[] = ['field' => $field, 'rules' => $matches[2][$i]];
                }
            }
        }
        return $rules;
    }

    private function hasAuthorize(string $contents): bool
    {
        preg_match('/function\s+authorize\s*\(/', $contents, $m);
        return !empty($m);
    }

    private function hasMessages(string $contents): bool
    {
        return (bool) preg_match('/function\s+messages?\s*\(/', $contents);
    }
}