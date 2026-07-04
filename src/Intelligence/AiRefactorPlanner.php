<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * AI Refactor Planner
 *
 * Combines ReviewEngine + DiffEngine + TaskContextEngine into a unified
 * refactoring plan with priority levels, risk assessment, and suggested order.
 *
 * Output: refactor-plan.md, refactor-plan.json
 */
class AiRefactorPlanner
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function plan(array $data): array
    {
        $actions = [];

        // 1. Run all engines
        $reviewEngine = new ReviewEngine();
        $reviewResult = $reviewEngine->analyze($data);
        $findings = $reviewResult['review']['findings'] ?? [];

        $splitter = new AutoControllerSplitter();
        $splitResult = $splitter->analyze($data);

        $routeHealth = new RouteHealthEngine();
        $routeResult = $routeHealth->analyze($data);

        $codeFix = new CodeFixEngine();
        $fixResult = $codeFix->generate($data);
        $fixes = $fixResult['fix_suggestions']['fixes'] ?? [];

        // 2. Prioritize all findings
        foreach ($findings as $finding) {
            $actions[] = $this->prioritizeFinding($finding);
        }

        // 3. Add controller splits at correct priority
        foreach ($splitResult['controller_splits']['suggestions'] ?? [] as $split) {
            $actions[] = [
                'type' => 'controller_split',
                'priority' => $split['original_methods'] > 15 ? 'high' : 'medium',
                'title' => "Split {$split['original_controller']}",
                'description' => "{$split['original_methods']} methods across {$split['total_split_controllers']} suggested controllers",
                'risk' => 'medium',
                'effort' => 'medium',
                'confidence' => $split['confidence'],
            ];
        }

        // 4. Add route health issues
        foreach ($routeResult['route_health']['issues'] ?? [] as $issue) {
            $actions[] = [
                'type' => $issue['type'],
                'priority' => $issue['severity'] === 'high' ? 'high' : ($issue['severity'] === 'warning' ? 'medium' : 'low'),
                'title' => "Fix: {$issue['message']}",
                'description' => $issue['message'],
                'risk' => 'low',
                'effort' => 'small',
                'confidence' => $issue['confidence'],
            ];
        }

        // 5. Sort by priority
        $priorityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
        usort($actions, fn($a, $b) => ($priorityOrder[$a['priority']] ?? 99) <=> ($priorityOrder[$b['priority']] ?? 99));

        // 6. Build ordered execution plan
        $executionPlan = $this->buildExecutionPlan($actions);

        $md = $this->buildMarkdown($actions, $executionPlan);

        return [
            'refactor_plan' => [
                'total_actions' => count($actions),
                'actions' => $actions,
                'execution_plan' => $executionPlan,
                'markdown' => $md,
                'risks' => [
                    'high' => count(array_filter($actions, fn($a) => $a['priority'] === 'high' || $a['priority'] === 'critical')),
                    'medium' => count(array_filter($actions, fn($a) => $a['priority'] === 'medium')),
                    'low' => count(array_filter($actions, fn($a) => $a['priority'] === 'low')),
                ],
                'confidence' => 75,
            ],
        ];
    }

    private function prioritizeFinding(array $finding): array
    {
        $severity = $finding['severity'] ?? 'info';
        $type = $finding['type'] ?? '';

        $priority = match ($severity) {
            'critical' => 'critical',
            'high' => 'high',
            'warning' => 'medium',
            default => 'low',
        };

        $risk = match ($type) {
            'controller_split', 'fat_model', 'large_service' => 'medium',
            'missing_authorization' => 'high',
            'dead_controller' => 'low',
            'unnamed_routes', 'duplicate_validation' => 'low',
            default => 'low',
        };

        $effort = $finding['estimated_effort'] ?? match ($type) {
            'fat_controller', 'fat_model', 'large_service', 'large_class' => 'medium',
            default => 'small',
        };

        return [
            'type' => $type,
            'priority' => $priority,
            'title' => $finding['message'] ?? '',
            'description' => ($finding['evidence'] ?? '') ?: ($finding['message'] ?? ''),
            'class' => $finding['class'] ?? null,
            'path' => $finding['path'] ?? null,
            'risk' => $risk,
            'effort' => $effort,
            'confidence' => $finding['confidence'] ?? 50,
        ];
    }

    private function buildExecutionPlan(array $actions): array
    {
        $plan = [];
        $order = 1;

        // Phase 1: High risk, low effort (quick wins)
        $phase1 = array_filter($actions, fn($a) =>
            ($a['priority'] === 'high' || $a['priority'] === 'critical') && $a['effort'] === 'small'
        );
        foreach ($phase1 as $action) {
            $plan[] = [
                'step' => $order++,
                'phase' => 1,
                'phase_name' => 'High Risk - Quick Wins',
                'title' => $action['title'],
                'type' => $action['type'],
                'effort' => $action['effort'],
            ];
        }

        // Phase 2: Medium priority, small effort
        $phase2 = array_filter($actions, fn($a) =>
            $a['priority'] === 'medium' && $a['effort'] === 'small'
        );
        foreach ($phase2 as $action) {
            $plan[] = [
                'step' => $order++,
                'phase' => 2,
                'phase_name' => 'Medium Priority - Quick Wins',
                'title' => $action['title'],
                'type' => $action['type'],
                'effort' => $action['effort'],
            ];
        }

        // Phase 3: All remaining
        foreach ($actions as $action) {
            $already = array_filter($plan, fn($p) => $p['title'] === $action['title']);
            if (empty($already)) {
                $plan[] = [
                    'step' => $order++,
                    'phase' => 3,
                    'phase_name' => 'Remaining Improvements',
                    'title' => $action['title'],
                    'type' => $action['type'],
                    'effort' => $action['effort'],
                ];
            }
        }

        return $plan;
    }

    private function buildMarkdown(array $actions, array $executionPlan): string
    {
        $md = [];

        $md[] = '# AI Refactor Plan';
        $md[] = '';
        $md[] = '> Generated by Laravel Beacon v1.0.0 — AI Copilot';
        $md[] = '> Combines code review, diff analysis, and project knowledge.';
        $md[] = '> Read-only analysis. No changes are applied automatically.';
        $md[] = '';
        $md[] = '---';
        $md[] = '';

        // Summary
        $critical = count(array_filter($actions, fn($a) => $a['priority'] === 'critical'));
        $high = count(array_filter($actions, fn($a) => $a['priority'] === 'high'));
        $medium = count(array_filter($actions, fn($a) => $a['priority'] === 'medium'));
        $low = count(array_filter($actions, fn($a) => $a['priority'] === 'low'));

        $md[] = '## Summary';
        $md[] = '';
        $md[] = '| Priority | Count |';
        $md[] = '|----------|-------|';
        $md[] = "| 🔴 Critical | {$critical} |";
        $md[] = "| 🟠 High | {$high} |";
        $md[] = "| 🟡 Medium | {$medium} |";
        $md[] = "| 🔵 Low | {$low} |";
        $md[] = "| **Total** | **" . count($actions) . "** |";
        $md[] = '';

        // Execution Plan
        $md[] = '---';
        $md[] = '## Recommended Execution Order';
        $md[] = '';
        foreach ($executionPlan as $step) {
            $icon = match ($step['phase']) {
                1 => '🔴',
                2 => '🟡',
                default => '🔵',
            };
            $priority = match ($step['phase']) {
                1 => 'High priority',
                2 => 'Medium priority',
                default => 'Lower priority',
            };
            $md[] = "{$icon} **Step {$step['step']}** ({$priority}, {$step['effort']} effort): {$step['title']}";
        }
        $md[] = '';

        // Details
        $md[] = '---';
        $md[] = '## All Actions';
        $md[] = '';
        foreach ($actions as $action) {
            $icon = match ($action['priority']) {
                'critical' => '🔴', 'high' => '🟠',
                'medium' => '🟡', default => '🔵',
            };
            $md[] = "{$icon} **{$action['title']}**";
            $md[] = "- Priority: **{$action['priority']}** | Risk: **{$action['risk']}** | Effort: **{$action['effort']}** | Confidence: **{$action['confidence']}%**";
            if (!empty($action['class'])) {
                $md[] = "- Class: `{$action['class']}`";
            }
            $md[] = '';
        }

        $md[] = '---';
        $md[] = '*Generated by Laravel Beacon v1.0.0 — AI Copilot*';
        $md[] = '*All suggestions are read-only. Review manually before applying.*';

        return implode("\n", $md);
    }
}