<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Exporter;

use Coffesoft\LaravelBeacon\Context\Context;

/**
 * MarkdownExporter — Generates all MD and text output files.
 * Handles: context.md, ai-context.md, ai-summary.md, developer-guide.md,
 * prompts.md, architecture-report.md, project-graph.md, ai-index.md
 */
class MarkdownExporter
{
    public function export(Context $context, string $path): void
    {
        $data = $context->all();
        $filename = basename($path);

        $markdown = match (true) {
            str_contains($filename, 'ai-summary') || str_contains($filename, 'ai_summary') => $this->buildAiSummary($data),
            str_contains($filename, 'ai-context') || str_contains($filename, 'ai_context') => $this->buildAiContext($data),
            str_contains($filename, 'developer-guide') || str_contains($filename, 'developer_guide') => $this->buildDeveloperGuide($data),
            str_contains($filename, 'architecture-report') || str_contains($filename, 'architecture_report') => $this->buildArchitectureReport($data),
            str_contains($filename, 'prompts') && !str_contains($filename, 'prompt-pack') => $this->buildPrompts($data),
            str_contains($filename, 'context') || str_contains($filename, '.md') => $this->buildContextMarkdown($data),
            str_contains($filename, 'project-graph') => $this->buildProjectGraphMarkdown($data),
            str_contains($filename, 'ai-index') => $this->buildAIIndexMarkdown($data),
            default => $this->buildContextMarkdown($data),
        };

        file_put_contents($path, $markdown);
    }

    /**
     * Build the comprehensive context.md file (backward compatible).
     */
    private function buildContextMarkdown(array $data): string
    {
        $md = [];

        $md[] = "# Laravel Beacon — AI Project Intelligence";
        $md[] = "";
        $md[] = "> Generated for AI-assisted development.";
        $md[] = "> **Laravel Beacon v1.0.0** — Project intelligence and analysis.";
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
        $md[] = "| **Beacon Version** | " . ($data['beacon_version'] ?? '1.0.0') . " |";
        $complexityLevel = $data['enhanced_statistics']['complexity']['level'] ?? '?';
        $complexityScore = $data['enhanced_statistics']['complexity']['score'] ?? '?';
        $md[] = "| **Complexity** | {$complexityLevel} (score: {$complexityScore}) |";
        $md[] = "";

        // Summary counts
        $stats = $data['statistics'] ?? [];
        $enhanced = $data['enhanced_statistics'] ?? [];
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
        $md[] = "| **Features** | " . ($data['features']['count'] ?? 0) . " |";
        $md[] = "| **Workflows** | " . ($data['workflows']['count'] ?? 0) . " |";
        $md[] = "";
        $medianMethods = $enhanced['controllers']['median_methods'] ?? '?';
        $md[] = "Average controller methods: **{$stats['average_controller_methods']}** (median: **{$medianMethods}**)";
        $md[] = "";
        $md[] = "Average model methods: **{$stats['average_model_methods']}**";
        $md[] = "";
        $mostConnected = $enhanced['models']['most_connected'] ?? '?';
        $md[] = "Most connected model: **{$mostConnected}**";
        $md[] = "";
        $md[] = "**See also:** `ai-context.md` for LLM-optimized overview, `ai-summary.md` for class summaries,";
        $md[] = "`architecture-report.md` for architecture analysis, `developer-guide.md` for onboarding.";
        $md[] = "";

        // Architecture (continued)
        $this->appendArchitecture($md, $data);
        $this->appendModelsSection($md, $data);
        $this->appendControllersSection($md, $data);
        $this->appendRoutesSection($md, $data);
        $this->appendBusinessRulesSection($md, $data);
        $this->appendSecuritySection($md, $data);
        $this->appendPerformanceSection($md, $data);
        $this->appendPackagesSection($md, $data);
        $this->appendAISummariesSection($md, $data);
        $this->appendFolderTreeSection($md, $data);

        $md[] = "---";
        $md[] = "*Generated by Laravel Beacon — AI Project Intelligence Engine*";
        $md[] = "*Run `php artisan beacon:scan` to regenerate.*";
        $md[] = "";

        return implode("\n", $md);
    }

    private function appendArchitecture(array &$md, array $data): void
    {
        $arch = $data['architecture'] ?? [];
        if (empty($arch)) return;
        $md[] = "---";
        $md[] = "## 🏗️ Architecture [confidence: 85]";
        $md[] = "";
        $md[] = "**Primary:** " . ($arch['primary'] ?? 'MVC');
        if (!empty($arch['secondary'])) {
            $md[] = "**Secondary:** " . implode(', ', $arch['secondary']);
            $md[] = "**Hybrid:** " . ($arch['is_hybrid'] ? 'Yes' : 'No');
        }
        foreach ($arch['explanations'] ?? [] as $type => $reason) {
            $md[] = "- **{$type}**: {$reason}";
        }
        $md[] = "";
    }

    private function appendModelsSection(array &$md, array $data): void
    {
        $md[] = "---";
        $md[] = "## 📦 Models [confidence: 90]";
        $md[] = "";
        foreach ($data['models']['items'] ?? [] as $model) {
            $md[] = "### {$model['name']}";
            $md[] = "- **File:** `{$model['path']}`";
            if (!empty($model['fillable'])) $md[] = "- **Attributes:** `" . implode('`, `', $model['fillable']) . "`";
            if (!empty($model['traits'])) $md[] = "- **Traits:** " . implode(', ', $model['traits']);
            if (!empty($model['relations'])) {
                $relStr = array_map(fn($r) => ($r['type'] ?? '?') . ': ' . ($r['target'] ?? '?'), $model['relations']);
                $md[] = "- **Relations:** " . implode(', ', $relStr);
            }
            $md[] = "";
        }
    }

    private function appendControllersSection(array &$md, array $data): void
    {
        $md[] = "---";
        $md[] = "## 🎮 Controllers [confidence: 90]";
        $md[] = "";
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $md[] = "### {$ctrl['name']}";
            $md[] = "- **Group:** `{$ctrl['group']}` | **CRUD:** " . ($ctrl['is_crud'] ? 'Yes' : 'No');
            $md[] = "- **Methods:** " . implode(', ', $ctrl['methods'] ?? []);
            if (!empty($ctrl['middleware'])) $md[] = "- **Middleware:** " . implode(', ', $ctrl['middleware']);
            $md[] = "";
        }
    }

    private function appendRoutesSection(array &$md, array $data): void
    {
        $md[] = "---";
        $md[] = "## 🛣️ Routes (" . ($data['routes']['count'] ?? 0) . " total) [confidence: 95]";
        $md[] = "";
        $routeGroups = $data['route_intelligence']['groups'] ?? [];
        foreach ($routeGroups as $module => $group) {
            $md[] = "### " . ucfirst($module) . " ({$group['total']} routes)";
            if (!empty($group['middleware'])) $md[] = "- Middleware: " . implode(', ', $group['middleware']);
            if (!empty($group['controllers'])) $md[] = "- Controllers: " . implode(', ', $group['controllers']);
            foreach (array_slice($group['routes'] ?? [], 0, 10) as $route) {
                $methods = implode(', ', array_diff($route['methods'] ?? [], ['HEAD']));
                $name = $route['name'] ? " (`{$route['name']}`)" : '';
                $md[] = "- `{$methods}` `{$route['uri']}`{$name}";
            }
            if (count($group['routes'] ?? []) > 10) {
                $md[] = "- ... and " . (count($group['routes']) - 10) . " more routes";
            }
            $md[] = "";
        }
    }

    private function appendBusinessRulesSection(array &$md, array $data): void
    {
        $rules = $data['business_rules'] ?? [];
        if (empty($rules['items'])) return;
        $md[] = "---";
        $md[] = "## 📜 Business Rules [confidence: 80]";
        $md[] = "";
        foreach (array_slice($rules['items'], 0, 20) as $rule) {
            $md[] = "- **{$rule['type']}**: {$rule['rule']}";
        }
        if (count($rules['items']) > 20) {
            $md[] = "- ... and " . (count($rules['items']) - 20) . " more rules";
        }
        $md[] = "";
    }

    private function appendSecuritySection(array &$md, array $data): void
    {
        $security = $data['security'] ?? [];
        if (empty($security['issues'])) return;
        $md[] = "---";
        $md[] = "## 🔒 Security Analysis [confidence: 85]";
        $md[] = "";
        foreach ($security['issues'] as $issue) {
            $icon = match ($issue['severity']) {
                'critical' => '🔴', 'high' => '🟠', 'warning' => '🟡', default => '🔵',
            };
            $md[] = "- {$icon} **{$issue['severity']}**: {$issue['message']}";
        }
        $md[] = "";
    }

    private function appendPerformanceSection(array &$md, array $data): void
    {
        $perf = $data['performance'] ?? [];
        if (empty($perf['issues'])) return;
        $md[] = "---";
        $md[] = "## ⚡ Performance Analysis [confidence: 75]";
        $md[] = "";
        foreach ($perf['issues'] as $issue) {
            $md[] = "- **{$issue['type']}**: {$issue['message']}";
        }
        $md[] = "";
    }

    private function appendPackagesSection(array &$md, array $data): void
    {
        $md[] = "---";
        $md[] = "## 📦 Packages [confidence: 95]";
        $md[] = "";
        $md[] = "| Package | Version | Category |";
        $md[] = "|---------|---------|----------|";
        foreach ($data['packages']['items'] ?? [] as $pkg) {
            $md[] = "| {$pkg['name']} | {$pkg['version']} | {$pkg['category']} |";
        }
        $md[] = "";
    }

    private function appendAISummariesSection(array &$md, array $data): void
    {
        $summaries = $data['ai_summaries'] ?? [];
        if (empty($summaries['items'])) return;
        $md[] = "---";
        $md[] = "## 🤖 AI Class Summaries [confidence: 85]";
        $md[] = "";
        $md[] = "> Detailed per-class summaries available in `ai-summary.md`.";
        $md[] = "";
        foreach (array_slice($summaries['items'], 0, 10) as $summary) {
            $md[] = "- **{$summary['class']}** ({$summary['type']})";
        }
        if (count($summaries['items']) > 10) {
            $md[] = "- ... and " . (count($summaries['items']) - 10) . " more classes";
        }
        $md[] = "";
    }

    private function appendFolderTreeSection(array &$md, array $data): void
    {
        $tree = $data['folder_tree'] ?? [];
        $md[] = "---";
        $md[] = "## 📁 Project Structure";
        $md[] = "";
        $md[] = "```";
        $md[] = $this->renderTree($tree['root'] ?? [], 0);
        $md[] = "```";
        $md[] = "";
    }

    // AI Summary helper
    private function buildAiSummary(array $data): string
    {
        return $data['ai_summary']['content'] ?? '# AI Summary' . "\n\n" . 'Run beacon:scan to generate.';
    }

    // AI Context helper
    private function buildAiContext(array $data): string
    {
        return $data['ai_context']['content'] ?? '# AI Context' . "\n\n" . 'See ai-summary.md for class summaries.';
    }

    // Developer Guide helper
    private function buildDeveloperGuide(array $data): string
    {
        return $data['developer_guide']['content'] ?? '# Developer Guide' . "\n\n" . 'Run beacon:scan to generate.';
    }

    // Architecture Report helper
    private function buildArchitectureReport(array $data): string
    {
        return $data['architecture_report']['content'] ?? '# Architecture Report' . "\n\n" . 'Run beacon:scan to generate.';
    }

    // Prompts helper
    private function buildPrompts(array $data): string
    {
        return $data['ai_prompts']['content'] ?? '# AI Prompts' . "\n\n" . 'See ai-context.md first.';
    }

    private function buildProjectGraphMarkdown(array $data): string
    {
        $md = ["# Project Relationship Graph", ""];
        $graph = $data['project_graph'] ?? [];
        $md[] = "## Nodes (" . count($graph['nodes'] ?? []) . ")";
        foreach ($graph['nodes'] ?? [] as $node) {
            $md[] = "- **{$node['name']}** ({$node['type']})";
        }
        $md[] = "";
        $md[] = "## Edges (" . count($graph['edges'] ?? []) . ")";
        foreach ($graph['edges'] ?? [] as $edge) {
            $md[] = "- `{$edge['from']}` --{$edge['label']}--> `{$edge['to']}`";
        }
        return implode("\n", $md);
    }

    private function buildAIIndexMarkdown(array $data): string
    {
        $md = ["# AI Index — Quick Reference", ""];
        $md[] = "## Models";
        foreach ($data['models']['items'] ?? [] as $model) {
            $md[] = "- `{$model['name']}`";
        }
        $md[] = "";
        $md[] = "## Key Classes";
        foreach ($data['ai_summaries']['items'] ?? [] as $summary) {
            if (in_array($summary['type'], ['service', 'repository', 'policy'])) {
                $md[] = "- **{$summary['class']}** ({$summary['type']})";
            }
        }
        $md[] = "";
        $md[] = "## Business Rules";
        foreach ($data['business_rules']['items'] ?? [] as $rule) {
            $md[] = "- {$rule['rule']}";
        }
        return implode("\n", $md);
    }

    private function renderTree(array $node, int $depth): string
    {
        $output = '';
        $indent = str_repeat('  ', $depth);
        if ($depth === 0) $output .= "{$node['name']}/\n";
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