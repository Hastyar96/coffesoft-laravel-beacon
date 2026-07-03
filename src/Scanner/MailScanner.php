<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;
use Coffesoft\LaravelBeacon\Reader\PhpParser;

/**
 * Scans app/Mail and detects mail classes, subjects, templates, and views.
 */
class MailScanner
{
    public function __construct(
        private readonly FileReader $reader,
        private readonly PhpParser $parser,
    ) {}

    public function scan(): array
    {
        $path = app_path('Mail');
        if (!is_dir($path)) {
            return ['mail' => ['count' => 0, 'items' => []]];
        }

        $items = [];
        foreach ($this->reader->getPhpFiles($path) as $file) {
            $contents = $this->reader->read($file->getPathname());
            $parsed = $this->parser->parse($contents);
            if ($parsed['class_name'] === null) continue;

            $subject = $this->extractSubject($contents);
            $markdownTemplate = $this->extractMarkdownTemplate($contents);
            $viewName = $this->extractViewName($contents);
            $attachments = $this->detectAttachments($contents);

            $items[] = [
                'name' => $parsed['class_name'],
                'namespace' => $parsed['namespace'] ?? 'App\\Mail',
                'path' => $file->getRelativePathname(),
                'subject' => $subject,
                'markdown' => $markdownTemplate,
                'view' => $viewName,
                'has_attachments' => $attachments > 0,
                'attachment_count' => $attachments,
                'properties' => $parsed['properties'],
                'methods' => array_map(fn($m) => $m['name'], $parsed['methods']),
                'build_method_exists' => $this->hasBuildMethod($parsed['methods']),
            ];
        }

        return [
            'mail' => [
                'count' => count($items),
                'items' => $items,
            ],
        ];
    }

    private function extractSubject(string $contents): ?string
    {
        if (preg_match('/public\s+(string\s+)?\$subject\s*=\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            return $m[2];
        }
        if (preg_match('/->subject\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            return $m[1];
        }
        if (preg_match('/function\s+build\s*\([^)]*\)\s*\{(.*?)\}/s', $contents, $m)) {
            if (preg_match('/->subject\s*\(\s*__?\([\'"]([^\'"]+)[\'"]/', $m[1], $sm)) {
                return $sm[1];
            }
        }
        return null;
    }

    private function extractMarkdownTemplate(string $contents): ?string
    {
        if (preg_match('/->markdown\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            return $m[1];
        }
        return null;
    }

    private function extractViewName(string $contents): ?string
    {
        if (preg_match('/->view\s*\(\s*[\'"]([^\'"]+)[\'"]/', $contents, $m)) {
            return $m[1];
        }
        return null;
    }

    private function detectAttachments(string $contents): int
    {
        $count = 0;
        $count += preg_match_all('/\battach\s*\(/', $contents);
        $count += preg_match_all('/\battachFromStorage\s*\(/', $contents);
        $count += preg_match_all('/\battachData\s*\(/', $contents);
        return $count;
    }

    private function hasBuildMethod(array $methods): bool
    {
        foreach ($methods as $method) {
            if ($method['name'] === 'build') return true;
        }
        return false;
    }
}