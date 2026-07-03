<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Exporters;

use Coffessoft\LaravelBeacon\Context\Context;
use Coffessoft\LaravelBeacon\Contracts\Exporter;
use Illuminate\Support\Facades\File;

/**
 * Exporter that converts Context data into Markdown format
 * and writes it to disk.
 */
class MarkdownExporter implements Exporter
{
    /**
     * Export the given context as a markdown file.
     */
    public function export(Context $context): void
    {
        $markdown = $this->buildMarkdown($context);

        $path = $this->resolveOutputPath();

        File::ensureDirectoryExists(dirname($path));

        File::put($path, $markdown);
    }

    /**
     * Resolve the full output file path from configuration.
     */
    private function resolveOutputPath(): string
    {
        $directory = config('beacon.output_directory', 'storage/app/beacon');

        return base_path($directory . DIRECTORY_SEPARATOR . 'context.md');
    }

    /**
     * Build a structured markdown document from the context.
     */
    private function buildMarkdown(Context $context): string
    {
        $lines = [];

        $lines[] = '# Laravel Beacon Context';
        $lines[] = '';

        $this->addFrameworkSection($lines, $context);
        $this->addEnvironmentSection($lines, $context);
        $this->addTableSection($lines, 'Models', $context->get('models.items', []), ['Name', 'Namespace', 'Path']);
        $this->addTableSection($lines, 'Controllers', $context->get('controllers.items', []), ['Name', 'Namespace', 'Path']);
        $this->addRoutesSection($lines, $context);
        $this->addMigrationsSection($lines, $context);

        return implode("\n", $lines);
    }

    /**
     * Append the framework information section.
     */
    private function addFrameworkSection(array &$lines, Context $context): void
    {
        $lines[] = '## Framework';
        $lines[] = '';

        $lines[] = sprintf('- Framework: **%s**', $context->get('framework', 'No data'));
        $lines[] = sprintf('- Laravel Version: **%s**', $context->get('laravel_version', 'No data'));
        $lines[] = sprintf('- PHP Version: **%s**', $context->get('php_version', 'No data'));
        $lines[] = sprintf('- Base Path: `%s`', $context->get('base_path', 'No data'));

        $lines[] = '';
    }

    /**
     * Append the environment information section.
     */
    private function addEnvironmentSection(array &$lines, Context $context): void
    {
        $env = $context->get('environment', []);

        $lines[] = '## Environment';
        $lines[] = '';

        if (empty($env)) {
            $lines[] = 'No data.';
            $lines[] = '';

            return;
        }

        $lines[] = sprintf('- App Env: **%s**', $env['app_env'] ?? 'No data');
        $lines[] = sprintf('- Debug: **%s**', $this->formatBoolean($env['debug'] ?? null));
        $lines[] = sprintf('- Timezone: **%s**', $env['timezone'] ?? 'No data');
        $lines[] = sprintf('- Locale: **%s**', $env['locale'] ?? 'No data');

        $lines[] = '';
    }

    /**
     * Append a generic table section for list data.
     */
    private function addTableSection(array &$lines, string $title, array $items, array $columns): void
    {
        $lines[] = '## ' . $title;
        $lines[] = '';

        if (empty($items)) {
            $lines[] = 'No data.';
            $lines[] = '';

            return;
        }

        $header = '| ' . implode(' | ', $columns) . ' |';
        $separator = '| ' . implode(' | ', array_fill(0, count($columns), '---')) . ' |';

        $lines[] = $header;
        $lines[] = $separator;

        foreach ($items as $item) {
            $row = array_map(function (string $col) use ($item): string {
                return $item[$col] ?? '';
            }, $columns);

            $lines[] = '| ' . implode(' | ', $row) . ' |';
        }

        $lines[] = '';
    }

    /**
     * Append the routes section with specific columns.
     */
    private function addRoutesSection(array &$lines, Context $context): void
    {
        $items = $context->get('routes.items', []);
        $columns = ['uri', 'methods', 'name', 'action'];

        $lines[] = '## Routes';
        $lines[] = '';

        if (empty($items)) {
            $lines[] = 'No data.';
            $lines[] = '';

            return;
        }

        $header = '| ' . implode(' | ', $columns) . ' |';
        $separator = '| ' . implode(' | ', array_fill(0, count($columns), '---')) . ' |';

        $lines[] = $header;
        $lines[] = $separator;

        foreach ($items as $item) {
            $methods = is_array($item['methods'] ?? null)
                ? implode(', ', $item['methods'])
                : ($item['methods'] ?? '');

            $lines[] = sprintf(
                '| %s | %s | %s | %s |',
                $item['uri'] ?? '',
                $methods,
                $item['name'] ?? '',
                $item['action'] ?? ''
            );
        }

        $lines[] = '';
    }

    /**
     * Append the migrations section with specific columns.
     */
    private function addMigrationsSection(array &$lines, Context $context): void
    {
        $items = $context->get('migrations.items', []);
        $columns = ['filename', 'table', 'operation'];

        $lines[] = '## Migrations';
        $lines[] = '';

        if (empty($items)) {
            $lines[] = 'No data.';
            $lines[] = '';

            return;
        }

        $header = '| ' . implode(' | ', $columns) . ' |';
        $separator = '| ' . implode(' | ', array_fill(0, count($columns), '---')) . ' |';

        $lines[] = $header;
        $lines[] = $separator;

        foreach ($items as $item) {
            $row = array_map(function (string $col) use ($item): string {
                return $item[$col] ?? '';
            }, $columns);

            $lines[] = '| ' . implode(' | ', $row) . ' |';
        }

        $lines[] = '';
    }

    /**
     * Format a nullable boolean for markdown display.
     */
    private function formatBoolean(mixed $value): string
    {
        if ($value === true) {
            return 'true';
        }

        if ($value === false) {
            return 'false';
        }

        return 'null';
    }
}