<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;
use Illuminate\Support\Facades\File;

/**
 * Scanner that discovers migrations in database/migrations.
 *
 * Reads migration files and extracts metadata such as
 * class name, timestamp, table name, and operation type
 * without executing or parsing columns.
 */
class MigrationScanner implements Scanner
{
    /**
     * Scan database/migrations and return migration metadata.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $path = database_path('migrations');

        if (! is_dir($path)) {
            return [
                'migrations' => [
                    'count' => 0,
                    'items' => [],
                ],
            ];
        }

        $files = File::allFiles($path);

        $items = [];

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $filename = $file->getFilename();
            $contents = $file->getContents();

            $class = $this->extractClass($contents);
            $timestamp = $this->extractTimestamp($filename);

            $table = $this->detectTable($contents);
            $operation = $this->detectOperation($contents);

            $items[] = [
                'filename' => $filename,
                'class' => $class,
                'timestamp' => $timestamp,
                'table' => $table,
                'operation' => $operation,
                'path' => $file->getRelativePathname(),
            ];
        }

        return [
            'migrations' => [
                'count' => count($items),
                'items' => $items,
            ],
        ];
    }

    /**
     * Extract the migration class name from file contents.
     */
    private function extractClass(string $contents): ?string
    {
        if (preg_match('/^class\s+(\w+)/m', $contents, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract the timestamp prefix from the migration filename.
     */
    private function extractTimestamp(string $filename): string
    {
        if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})/', $filename, $matches)) {
            return $matches[1];
        }

        return '';
    }

    /**
     * Detect the table name from Laravel Schema calls.
     */
    private function detectTable(string $contents): ?string
    {
        $patterns = [
            '/Schema::create\([\'"](\w+)[\'"]/',
            '/Schema::table\([\'"](\w+)[\'"]/',
            '/Schema::drop\([\'"](\w+)[\'"]/',
            '/Schema::dropIfExists\([\'"](\w+)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $contents, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Detect the migration operation type.
     */
    private function detectOperation(string $contents): string
    {
        if (preg_match('/Schema::create\(/', $contents)) {
            return 'create';
        }

        if (preg_match('/Schema::table\(/', $contents)) {
            return 'update';
        }

        if (preg_match('/Schema::drop/', $contents)) {
            return 'drop';
        }

        return 'unknown';
    }
}