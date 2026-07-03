<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Exporter;

use Coffesoft\LaravelBeacon\Context\Context;

/**
 * Exports context data to Markdown format for AI consumption.
 * Generates a comprehensive project overview that can be used
 * by any AI assistant as context.
 */
class MarkdownExporter
{
    /**
     * Export the context to a Markdown file.
     */
    public function export(Context $context, string $path): void
    {
        $data = $context->all();
        $filename = basename($path);

        $markdown = match (true) {
            str_contains($filename, 'ai-context') || str_contains($filename, 'ai_context') => $this->buildAiContext($data),
            str_contains($filename, 'developer-guide') || str_contains($filename, 'developer_guide') => $this->buildDeveloperGuide($data),
            str_contains($filename, 'prompts') && !str_contains($filename, 'prompt-pack') => $this->buildPrompts($data),
            str_contains($filename, 'context') || str_contains($filename, '.md') => $this->buildContextMarkdown($data),
            str_contains($filename, 'project-graph') => $this->buildProjectGraphMarkdown($data),
            str_contains($filename, 'ai-index') => $this->buildAIIndexMarkdown($data),
            default => $this->buildContextMarkdown($data),
        };

        file_put_contents($path, $markdown);
    }

    /**
     * Build the comprehensive context.md file.
     */
    private function buildContextMarkdown(array $data): string
    {
        $md = [];

        $md[] = "# Laravel Beacon — AI Project Intelligence";
        $md[] = "";
        $md[] = "> Generated for AI-assisted development.";
        $md[] = "> Total estimated tokens saved by reading this file instead of source code: **thousands**.";
        $md[] = "";
        $md[] = "---";
        $md[] = "";

        // Project overview
        $md[] = "## 📋 Project Overview";
        $md[] = "";
        $md[] = "| Property | Value |";
        $md[] = "|----------|-------|";
        $md[] = "| **Project** | " . basename(base_path()) . " |";
        $md[] = "| **Framework** | Laravel " . ($data['framework']['version'] ?? '?') . " |";
        $md[] = "| **PHP** | " . ($data['framework']['php_version'] ?? '?') . " |";
        $md[] = "| **Generated** | " . ($data['generated_at'] ?? '?') . " |";
        $md[] = "| **Beacon Version** | " . ($data['beacon_version'] ?? '2.0.0') . " |";
        $md[] = "";

        // Summary counts
        $stats = $data['statistics'] ?? [];
        $md[] = "## 📊 Quick Statistics";
        $md[] = "";
        $md[] = "| Component | Count |";
        $md[] = "|-----------|-------|";
        $md[] = "| **PHP Files** | " . ($stats['total_php_files'] ?? 0) . " |";
        $md[] = "| **Blade Views** | " . ($stats['total_blade_files'] ?? 0) . " |";
        $md[] = "| **Models** | " . ($stats['models'] ?? 0) . " |";
        $md[] = "| **Controllers** | " . ($stats['controllers'] ?? 0) . " |";
        $md[] = "| **Services** | " . ($stats['services'] ?? 0) . " |";
        $md[] = "| **Repositories** | " . ($stats['repositories'] ?? 0) . " |";
        $md[] = "| **Form Requests** | " . ($stats['requests'] ?? 0) . " |";
        $md[] = "| **Policies** | " . ($stats['policies'] ?? 0) . " |";
        $md[] = "| **Events** | " . ($stats['events'] ?? 0) . " |";
        $md[] = "| **Jobs** | " . ($stats['jobs'] ?? 0) . " |";
        $md[] = "| **Notifications** | " . ($stats['notifications'] ?? 0) . " |";
        $md[] = "| **Commands** | " . ($stats['commands'] ?? 0) . " |";
        $md[] = "| **Enums** | " . ($stats['enums'] ?? 0) . " |";
        $md[] = "| **Packages** | " . ($stats['packages'] ?? 0) . " |";
        $md[] = "| **Database Tables** | " . ($stats['database_tables'] ?? 0) . " |";
        $md[] = "| **Routes** | " . ($data['routes']['count'] ?? 0) . " |";
        $md[] = "";
        $md[] = "Average controller methods: **{$stats['average_controller_methods']}**";
        $md[] = "";
        $md[] = "Average model methods: **{$stats['average_model_methods']}**";
        $md[] = "";

        // Architecture
        $arch = $data['architecture'] ?? [];
        if (!empty($arch)) {
            $md[] = "---";
            $md[] = "## 🏗️ Architecture";
            $md[] = "";
            $md[] = "**Primary:** " . ($arch['primary'] ?? 'MVC');
            $md[] = "";
            if (!empty($arch['secondary'])) {
                $md[] = "**Secondary:** " . implode(', ', $arch['secondary']);
                $md[] = "";
                $md[] = "**Hybrid:** " . ($arch['is_hybrid'] ? 'Yes' : 'No');
                $md[] = "";
            }
            if (!empty($arch['explanations'])) {
                $md[] = "### Detection Reasoning";
                $md[] = "";
                foreach ($arch['explanations'] as $type => $reason) {
                    $md[] = "- **{$type}**: {$reason}";
                }
                $md[] = "";
            }
        }

        // Models
        $md[] = "---";
        $md[] = "## 📦 Models";
        $md[] = "";
        foreach ($data['models']['items'] ?? [] as $model) {
            $md[] = "### {$model['name']}";
            $md[] = "";
            $md[] = "- **Namespace:** `{$model['namespace']}`";
            $md[] = "- **File:** `{$model['path']}`";
            if (!empty($model['fillable'])) $md[] = "- **Fillable:** `" . implode('`, `', $model['fillable']) . "`";
            if (!empty($model['casts'])) {
                $castStr = implode(', ', array_map(fn($k, $v) => "{$k} => {$v}", array_keys($model['casts']), $model['casts']));
                $md[] = "- **Casts:** {$castStr}";
            }
            if (!empty($model['traits'])) $md[] = "- **Traits:** " . implode(', ', $model['traits']);
            if (!empty($model['relations'])) {
                $relStr = [];
                foreach ($model['relations'] as $type => $count) {
                    $relStr[] = "{$type}: {$count}";
                }
                $md[] = "- **Relations:** " . implode(', ', $relStr);
            }
            if (!empty($model['scopes'])) $md[] = "- **Scopes:** " . implode(', ', $model['scopes']);
            if (!empty($model['accessors'])) $md[] = "- **Accessors:** " . implode(', ', $model['accessors']);
            $md[] = "";
        }

        // Controllers
        $md[] = "---";
        $md[] = "## 🎮 Controllers";
        $md[] = "";
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $md[] = "### {$ctrl['name']}";
            $md[] = "";
            $md[] = "- **Group:** `{$ctrl['group']}`";
            $md[] = "- **CRUD:** " . ($ctrl['is_crud'] ? 'Yes' : 'No');
            $md[] = "- **Methods:** " . implode(', ', $ctrl['methods'] ?? []);
            if (!empty($ctrl['middleware'])) $md[] = "- **Middleware:** " . implode(', ', $ctrl['middleware']);
            $md[] = "";
        }

        // Routes
        $md[] = "---";
        $md[] = "## 🛣️ Routes ({$data['routes']['count']} total)";
        $md[] = "";
        $routeGroups = $data['route_intelligence']['groups'] ?? [];
        foreach ($routeGroups as $module => $group) {
            $md[] = "### {$module} ({$group['total']} routes)";
            $md[] = "";
            if (!empty($group['middleware'])) $md[] = "- **Middleware:** " . implode(', ', $group['middleware']);
            if (!empty($group['controllers'])) $md[] = "- **Controllers:** " . implode(', ', $group['controllers']);
            $md[] = "";
            foreach ($group['routes'] as $route) {
                $methods = implode(', ', array_diff($route['methods'] ?? [], ['HEAD']));
                $name = $route['name'] ? " (`{$route['name']}`)" : '';
                $md[] = "- `{$methods}` `{$route['uri']}`{$name}";
            }
            $md[] = "";
        }

        // Business Rules
        $rules = $data['business_rules'] ?? [];
        if (!empty($rules['items'])) {
            $md[] = "---";
            $md[] = "## 📜 Business Rules";
            $md[] = "";
            $md[] = "> Detected from validation, model events, database constraints, and accessors.";
            $md[] = "";
            foreach ($rules['items'] as $rule) {
                $md[] = "- **{$rule['type']}**: {$rule['rule']}";
                $md[] = "  - Source: `{$rule['source']}`";
            }
            $md[] = "";
        }

        // Security
        $security = $data['security'] ?? [];
        if (!empty($security['issues'])) {
            $md[] = "---";
            $md[] = "## 🔒 Security Analysis";
            $md[] = "";
            foreach ($security['issues'] as $issue) {
                $severity = $issue['severity'];
                $icon = match ($severity) {
                    'critical' => '🔴',
                    'high' => '🟠',
                    'warning' => '🟡',
                    default => '🔵',
                };
                $md[] = "- {$icon} **{$severity}**: {$issue['message']}";
            }
            $md[] = "";
        }

        // Performance
        $perf = $data['performance'] ?? [];
        if (!empty($perf['issues'])) {
            $md[] = "---";
            $md[] = "## ⚡ Performance Analysis";
            $md[] = "";
            foreach ($perf['issues'] as $issue) {
                $md[] = "- **{$issue['type']}**: {$issue['message']}";
            }
            $md[] = "";
        }

        // Packages
        $md[] = "---";
        $md[] = "## 📦 Packages";
        $md[] = "";
        $md[] = "| Package | Version | Category |";
        $md[] = "|---------|---------|----------|";
        foreach ($data['packages']['items'] ?? [] as $pkg) {
            $md[] = "| {$pkg['name']} | {$pkg['version']} | {$pkg['category']} |";
        }
        $md[] = "";

        // AI Summaries
        $summaries = $data['ai_summaries'] ?? [];
        if (!empty($summaries['items'])) {
            $md[] = "---";
            $md[] = "## 🤖 AI Class Summaries";
            $md[] = "";
            foreach ($summaries['items'] as $summary) {
                $md[] = "### {$summary['class']} ({$summary['type']})";
                $md[] = "";
                $md[] = "```";
                $md[] = $summary['summary'];
                $md[] = "```";
                $md[] = "";
            }
        }

        // Folder Tree
        $tree = $data['folder_tree'] ?? [];
        $md[] = "---";
        $md[] = "## 📁 Project Structure";
        $md[] = "";
        $md[] = "```";
        $md[] = $this->renderTree($tree['root'] ?? [], 0);
        $md[] = "```";
        $md[] = "";

        // Footer
        $md[] = "---";
        $md[] = "*Generated by Laravel Beacon v2 — AI Project Intelligence Engine*";
        $md[] = "*Run `php artisan beacon:scan` to regenerate.*";
        $md[] = "";

        return implode("\n", $md);
    }

    /**
     * Build project graph markdown.
     */
    private function buildProjectGraphMarkdown(array $data): string
    {
        $md = [];
        $md[] = "# Project Relationship Graph";
        $md[] = "";

        $graph = $data['project_graph'] ?? [];

        $md[] = "## Nodes (" . count($graph['nodes'] ?? []) . ")";
        $md[] = "";
        foreach ($graph['nodes'] ?? [] as $node) {
            $md[] = "- **{$node['name']}** ({$node['type']})";
        }
        $md[] = "";

        $md[] = "## Edges (" . count($graph['edges'] ?? []) . ")";
        $md[] = "";
        foreach ($graph['edges'] ?? [] as $edge) {
            $from = $edge['from'] ?? '';
            $to = $edge['to'] ?? '';
            $label = $edge['label'] ?? '';
            $md[] = "- `{$from}` --{$label}--> `{$to}`";
        }
        $md[] = "";

        return implode("\n", $md);
    }

    /**
     * Build AI Index markdown.
     */
    private function buildAIIndexMarkdown(array $data): string
    {
        $md = [];
        $md[] = "# AI Index — Quick Reference";
        $md[] = "";
        $md[] = "This file contains the most important project metadata for AI assistants.";
        $md[] = "";

        $md[] = "## Models";
        $md[] = "";
        foreach ($data['models']['items'] ?? [] as $model) {
            $md[] = "- `{$model['name']}`";
            if (!empty($model['fillable'])) $md[] = "   - Fillable: " . implode(', ', $model['fillable']);
        }
        $md[] = "";

        $md[] = "## Key Classes";
        $md[] = "";
        foreach ($data['ai_summaries']['items'] ?? [] as $summary) {
            if (in_array($summary['type'], ['service', 'repository', 'policy'])) {
                $md[] = "- **{$summary['class']}** ({$summary['type']})";
            }
        }
        $md[] = "";

        $md[] = "## Business Rules";
        $md[] = "";
        foreach ($data['business_rules']['items'] ?? [] as $rule) {
            $md[] = "- {$rule['rule']}";
        }
        $md[] = "";

        return implode("\n", $md);
    }

    /**
     * Build AI context markdown (from AiContextCompressor).
     */
    private function buildAiContext(array $data): string
    {
        $content = $data['ai_context']['content'] ?? '';
        if (!empty($content)) {
            return $content;
        }

        // Fallback: build from component data
        $lines = [];
        $lines[] = '# AI Context — Laravel Project Intelligence';
        $lines[] = '';
        $lines[] = '> Optimized for AI assistants.';
        $lines[] = '> This file provides full project understanding.';
        $lines[] = '';
        $lines[] = '## Project';
        $lines[] = '';
        $lines[] = '- Name: ' . basename(base_path());
        $lines[] = '- Framework: Laravel ' . ($data['framework']['version'] ?? '?');
        $lines[] = '- PHP: ' . ($data['framework']['php_version'] ?? '?');
        $lines[] = '';
        $lines[] = '## Architecture';
        $lines[] = '';
        $lines[] = ($data['architecture']['primary'] ?? 'MVC');
        if (!empty($data['architecture']['secondary'])) {
            $lines[] = 'Patterns: ' . implode(', ', $data['architecture']['secondary']);
        }
        $lines[] = '';
        $lines[] = '## Quick Stats';
        $lines[] = '';
        $lines[] = '- Models: ' . ($data['models']['count'] ?? 0);
        $lines[] = '- Controllers: ' . ($data['controllers']['count'] ?? 0);
        $lines[] = '- Routes: ' . ($data['routes']['count'] ?? 0);
        $lines[] = '- Services: ' . ($data['services']['count'] ?? 0);
        $lines[] = '- Packages: ' . ($data['packages']['count'] ?? 0);
        $lines[] = '';
        $lines[] = '*See developer-guide.md and prompts.md for more.*';

        return implode("\n", $lines);
    }

    /**
     * Build developer guide markdown (from DeveloperOnboarding).
     */
    private function buildDeveloperGuide(array $data): string
    {
        $content = $data['developer_guide']['content'] ?? '';
        if (!empty($content)) {
            return $content;
        }

        $lines = [];
        $lines[] = '# Developer Onboarding Guide';
        $lines[] = '';
        $lines[] = '> Generated by Laravel Beacon v2.1';
        $lines[] = '';
        $lines[] = '## Quick Start';
        $lines[] = '';
        $lines[] = '1. Read ai-context.md for project overview';
        $lines[] = '2. Start with routes/web.php';
        $lines[] = '3. Trace a request through: Route → Controller → Service → Model';
        $lines[] = '4. Review features.json for feature map';
        $lines[] = '5. Review workflows.json for business flows';
        $lines[] = '';
        $lines[] = '## Folder Structure';
        $lines[] = '';
        $lines[] = '- `app/Models` - Eloquent models';
        $lines[] = '- `app/Http/Controllers` - HTTP handlers';
        $lines[] = '- `app/Services` - Business logic';
        $lines[] = '- `app/Repositories` - Data access layer';
        $lines[] = '- `app/Http/Requests` - Form validation';
        $lines[] = '- `app/Policies` - Authorization';
        $lines[] = '- `app/Jobs` - Queue tasks';
        $lines[] = '- `app/Events` - Event classes';
        $lines[] = '- `app/Notifications` - Notification channels';
        $lines[] = '- `routes/` - Route definitions';
        $lines[] = '- `resources/views/` - Blade templates';
        $lines[] = '- `database/migrations/` - Schema changes';

        return implode("\n", $lines);
    }

    /**
     * Build prompts markdown (from AiPromptPack).
     */
    private function buildPrompts(array $data): string
    {
        return $data['ai_prompts']['content'] ?? '# AI Prompts' . "\n\n" . 'See ai-context.md for project understanding first.';
    }

    /**
     * Recursively render folder tree.
     */
    private function renderTree(array $node, int $depth): string
    {
        $output = '';
        $indent = str_repeat('  ', $depth);

        if ($depth === 0) {
            $output .= "{$node['name']}/\n";
        }

        foreach ($node['children'] ?? [] as $child) {
            if (isset($child['exists']) && !$child['exists']) continue;

            if (!empty($child['children'])) {
                $output .= "{$indent}├── {$child['name']}/\n";
                $output .= $this->renderTree($child, $depth + 1);
            } else {
                $output .= "{$indent}├── {$child['name']}\n";
            }
        }

        return $output;
    }
}