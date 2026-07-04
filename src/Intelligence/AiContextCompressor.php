<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates an LLM-optimized context file (ai-context.md).
 * 
 * Target: 500–2000 lines.
 * Concise summaries only — no raw data dumps.
 * This is the first file an AI assistant should read.
 */
class AiContextCompressor
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $lines = [];

        $lines[] = '# AI Context — Laravel Project Intelligence';
        $lines[] = '';
        $lines[] = '> Optimized for AI assistants (ChatGPT, Claude, Gemini, Cline, Copilot).';
        $lines[] = '> This file provides full project understanding in ~500–2000 lines.';
        $lines[] = '> Confidence scores (0–100) indicate reliability of each detected item.';
        $lines[] = '';

        // Project purpose
        $lines[] = '---';
        $lines[] = '## 1. Project Overview';
        $lines[] = '';
        $lines[] = $this->formatTable([
            ['Property', 'Value'],
            ['Project Name', basename(base_path())],
            ['Framework', 'Laravel ' . ($data['framework']['version'] ?? '?')],
            ['PHP Version', $data['framework']['php_version'] ?? '?'],
            ['Beacon Version', $data['beacon_version'] ?? '1.0.0'],
            ['Generated', $data['generated_at'] ?? '?'],
            ['Confidence', '100 (from project metadata)'],
        ]);
        $lines[] = '';

        // Architecture summary
        $arch = $data['architecture'] ?? [];
        $lines[] = '---';
        $lines[] = '## 2. Architecture [confidence: 85]';
        $lines[] = '';
        $lines[] = 'Primary architecture: **' . ($arch['primary'] ?? 'MVC') . '**';
        if (!empty($arch['secondary'])) {
            $lines[] = 'Secondary patterns: ' . implode(', ', $arch['secondary']);
        }
        $lines[] = 'Hybrid architecture: ' . ($arch['is_hybrid'] ? 'Yes' : 'No');
        $lines[] = '';
        foreach ($arch['explanations'] ?? [] as $type => $reason) {
            $lines[] = "- **{$type}**: {$reason}";
        }
        $lines[] = '';

        // Main modules
        $modules = $data['modules'] ?? [];
        $lines[] = '---';
        $lines[] = '## 3. Main Modules [confidence: 80]';
        $lines[] = '';
        if (!empty($modules['items'])) {
            foreach ($modules['items'] as $module) {
                $lines[] = "- **{$module['name']}**";
                if (!empty($module['routes'])) $lines[] = "  - Routes: {$module['routes']}";
                if (!empty($module['controllers'])) $lines[] = "  - Controllers: " . implode(', ', array_column($module['controllers'] ?? [], 'name'));
            }
        } else {
            $routeGroups = $data['route_intelligence']['groups'] ?? [];
            foreach ($routeGroups as $module => $group) {
                $lines[] = "- **" . ucfirst($module) . "**: {$group['total']} routes via " . implode(', ', array_slice($group['controllers'] ?? [], 0, 3));
            }
        }
        $lines[] = '';

        // Core business entities
        $lines[] = '---';
        $lines[] = '## 4. Core Business Entities [confidence: 90]';
        $lines[] = '';
        foreach ($data['models']['items'] ?? [] as $model) {
            $rels = array_map(fn($r) => ($r['type'] ?? '?') . '->' . ($r['target'] ?? '?'), $model['relations'] ?? []);
            $lines[] = "- **{$model['name']}**" . (!empty($rels) ? ': ' . implode(', ', $rels) : '');
            if (!empty($model['fillable'])) {
                $lines[] = "  - Attributes: " . implode(', ', array_slice($model['fillable'], 0, 8)) . (count($model['fillable']) > 8 ? '...' : '');
            }
        }
        $lines[] = '';

        // Important services
        $lines[] = '---';
        $lines[] = '## 5. Services [confidence: 85]';
        $lines[] = '';
        foreach ($data['services']['items'] ?? [] as $svc) {
            $lines[] = "- **{$svc['name']}** — " . implode(', ', array_slice($svc['methods'] ?? [], 0, 5)) . (count($svc['methods'] ?? []) > 5 ? '...' : '');
            if (!empty($svc['referenced_models'])) {
                $models = array_map(fn($m) => substr(strrchr($m, '\\') ?: $m, 1), $svc['referenced_models']);
                $lines[] = "  - Models: " . implode(', ', $models);
            }
        }
        $lines[] = '';

        // Important controllers
        $lines[] = '---';
        $lines[] = '## 6. Key Controllers [confidence: 90]';
        $lines[] = '';
        $crudControllers = array_filter($data['controllers']['items'] ?? [], fn($c) => $c['is_crud'] ?? false);
        foreach ($crudControllers as $ctrl) {
            $lines[] = "- **{$ctrl['name']}** (CRUD) — " . implode(', ', array_slice($ctrl['methods'] ?? [], 0, 6)) . (count($ctrl['methods'] ?? []) > 6 ? '...' : '');
        }
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            if (!($ctrl['is_crud'] ?? false)) {
                $lines[] = "- **{$ctrl['name']}** — " . implode(', ', $ctrl['methods'] ?? []);
            }
        }
        $lines[] = '';

        // Business rules summary
        $rules = $data['business_rules'] ?? [];
        $lines[] = '---';
        $lines[] = '## 7. Business Rules [confidence: 80]';
        $lines[] = '';
        $lines[] = count($rules['items'] ?? $rules['count'] ?? 0) . ' business rules detected.';
        $lines[] = '';
        foreach (array_slice($rules['items'] ?? [], 0, 20) as $rule) {
            $lines[] = "- {$rule['rule']}";
        }
        if (count($rules['items'] ?? []) > 20) {
            $lines[] = '- ... and ' . (count($rules['items']) - 20) . ' more rules.';
        }
        $lines[] = '';

        // Authentication flow
        $auth = $data['api']['authentication'] ?? [];
        $lines[] = '---';
        $lines[] = '## 8. Authentication [confidence: 85]';
        $lines[] = '';
        if ($auth['sanctum'] ?? false) {
            $lines[] = '- **Sanctum**: API token authentication (SPA/mobile support)';
        }
        if ($auth['passport'] ?? false) {
            $lines[] = '- **Passport**: OAuth2 authentication server';
        }
        if ($auth['jwt'] ?? false) {
            $lines[] = '- **JWT**: JSON Web Token authentication';
        }
        if (!empty($auth['providers'])) {
            $lines[] = '- Auth providers: ' . implode(', ', $auth['providers']);
        }
        // Check routes for auth middleware
        $hasAuthRoutes = false;
        foreach ($data['routes']['items'] ?? [] as $route) {
            if (in_array('auth', $route['middleware'] ?? []) || in_array('auth:sanctum', $route['middleware'] ?? [])) {
                $hasAuthRoutes = true;
                break;
            }
        }
        if ($hasAuthRoutes) $lines[] = '- Auth middleware applied to protected routes';
        $lines[] = '';

        // Route overview
        $lines[] = '---';
        $lines[] = '## 9. Route Overview [confidence: 95]';
        $lines[] = '';
        $lines[] = "Total routes: **{$data['routes']['count']}**";
        $lines[] = '';
        $routeGroups = $data['route_intelligence']['groups'] ?? [];
        foreach ($routeGroups as $module => $group) {
            $methods = array_unique($group['methods_summary'] ?? []);
            $lines[] = "- **" . ucfirst($module) . "** ({$group['total']} routes)";
            $lines[] = "  - Methods: " . implode(', ', $methods);
            $lines[] = "  - Middleware: " . implode(', ', array_slice($group['middleware'] ?? [], 0, 5)) . (count($group['middleware'] ?? []) > 5 ? '...' : '');
        }
        $lines[] = '';

        // Database overview
        $dbIntel = $data['database_intelligence'] ?? [];
        $lines[] = '---';
        $lines[] = '## 10. Database Overview [confidence: 85]';
        $lines[] = '';
        $lines[] = "Tables: **{$dbIntel['table_count']}**";
        $lines[] = '';
        foreach ($dbIntel['tables'] ?? [] as $table) {
            $flags = [];
            if ($table['has_timestamps']) $flags[] = 'timestamps';
            if ($table['has_soft_deletes']) $flags[] = 'soft-deletes';
            if ($table['is_pivot']) $flags[] = 'pivot';
            $lines[] = "- **{$table['name']}** (" . count($table['columns']) . " cols)" . (!empty($flags) ? ' [' . implode(', ', $flags) . ']' : '');
            if (!empty($table['foreign_keys'])) {
                $fks = array_map(fn($fk) => "{$fk['column']} → {$fk['references']}", array_slice($table['foreign_keys'], 0, 4));
                $lines[] = "  - FK: " . implode(', ', $fks) . (count($table['foreign_keys']) > 4 ? '...' : '');
            }
        }
        $lines[] = '';

        // External integrations
        $lines[] = '---';
        $lines[] = '## 11. External Integrations [confidence: 70]';
        $lines[] = '';
        foreach ($data['packages']['items'] ?? [] as $pkg) {
            if (in_array($pkg['category'], ['payments', 'api', 'search', 'realtime', 'export_pdf', 'media'])) {
                $lines[] = "- **{$pkg['name']}** ({$pkg['version']}) — {$pkg['purpose']}";
            }
        }
        if ($hasThirdParty = $this->detectThirdPartyApis($data)) {
            foreach ($hasThirdParty as $api) {
                $lines[] = "- {$api}";
            }
        }
        $lines[] = '';

        // Queue usage
        $queue = $data['queue'] ?? [];
        $lines[] = '---';
        $lines[] = '## 12. Queue Usage [confidence: 80]';
        $lines[] = '';
        $lines[] = "Default driver: **{$queue['default_driver']}**";
        $lines[] = "Connections: " . implode(', ', $queue['connections'] ?? []);
        $lines[] = "Horizon: " . (($queue['horizon_installed'] ?? false) ? 'Installed' : 'Not installed');
        $lines[] = "Queueable jobs: " . count(array_filter($data['jobs']['items'] ?? [], fn($j) => $j['queued']));
        $lines[] = "Sync jobs: " . count(array_filter($data['jobs']['items'] ?? [], fn($j) => !$j['queued']));
        $lines[] = '';

        // Notifications
        $lines[] = '---';
        $lines[] = '## 13. Notifications [confidence: 80]';
        $lines[] = '';
        $lines[] = count($data['notifications']['items'] ?? []) . ' notification classes.';
        $channels = [];
        foreach ($data['notifications']['items'] ?? [] as $notif) {
            foreach ($notif['channels'] ?? [] as $ch) {
                $channels[$ch] = ($channels[$ch] ?? 0) + 1;
            }
        }
        if (!empty($channels)) {
            $lines[] = 'Channels used:';
            foreach ($channels as $ch => $count) {
                $lines[] = "- {$ch}: {$count} notifications";
            }
        }
        $lines[] = '';

        // AI hints
        $lines[] = '---';
        $lines[] = '## 14. AI Hints';
        $lines[] = '';
        $lines[] = '- **Best entry points**: See developer-guide.md for onboarding guide.';
        $lines[] = '- **Workflows**: See workflows.json for detected business workflows.';
        $lines[] = '- **Impact analysis**: See impact-map.json before making changes.';
        $lines[] = '- **Prompts**: See prompts.md for reusable AI prompts.';
        $lines[] = '- **Architecture detection**: architecture.json explains each detected pattern.';
        $lines[] = '- **Security findings**: security section in context.json for risks.';
        $lines[] = '- **Performance issues**: performance section in context.json for optimization.';
        $lines[] = '';

        return [
            'ai_context' => [
                'content' => implode("\n", $lines),
                'line_count' => substr_count(implode("\n", $lines), "\n") + 1,
                'generated' => true,
            ],
        ];
    }

    private function formatTable(array $rows): string
    {
        $lines = [];
        foreach ($rows as $i => $row) {
            $lines[] = '| ' . implode(' | ', $row) . ' |';
            if ($i === 0) {
                $lines[] = '| ' . implode(' | ', array_fill(0, count($row), '---')) . ' |';
            }
        }
        return implode("\n", $lines);
    }

    private function detectThirdPartyApis(array $data): array
    {
        $apis = [];
        $searchTerms = [
            'HttpClient', 'GuzzleHttp', 'curl_init', 'file_get_contents',
            'Stripe\\', 'PayPal\\', 'Paypal\\', 'Mollie\\', 'Braintree\\',
            'Socialite', 'Google_Client', 'Facebook\\', 'Twitter\\',
            'algolia', 'meilisearch', 'elasticsearch',
            'pusher', 'Pusher\\', 'WebSocket',
            'Sentry\\', 'bugsnag',
        ];

        $dirs = [app_path('Services'), app_path('Http/Controllers')];
        foreach ($dirs as $dir) {
            if (!is_dir($dir)) continue;
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir));
            foreach ($iterator as $file) {
                if ($file->getExtension() !== 'php') continue;
                $contents = file_get_contents($file->getPathname());
                foreach ($searchTerms as $term) {
                    if (str_contains($contents, $term)) {
                        $apiName = explode('\\', $term)[0];
                        $apiName = preg_replace('/[^a-zA-Z0-9]/', '', $apiName);
                        if ($apiName && !in_array($apiName, $apis)) {
                            $apis[$apiName] = $apiName;
                        }
                    }
                }
            }
        }

        return array_values($apis);
    }
}