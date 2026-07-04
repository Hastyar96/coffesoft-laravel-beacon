<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * AI Workspace Engine — generates focused workspaces for AI coding agents.
 *
 * Instead of exporting the entire project, allows AI agents to request
 * focused workspaces containing only the files and relationships needed
 * for a specific task.
 *
 * Every fact in a workspace is backed by source code evidence.
 * No inference. No speculation. Only proven relationships.
 */
class AiWorkspaceEngine
{
    /**
     * Generate a focused workspace for a route.
     *
     * @return array<string, mixed>
     */
    public function forRoute(string $routeName, array $allData): array
    {
        $route = null;
        foreach ($allData['routes']['items'] ?? [] as $r) {
            if ($r['name'] === $routeName || $r['uri'] === $routeName) {
                $route = $r;
                break;
            }
        }

        if ($route === null) {
            return ['error' => "Route '{$routeName}' not found."];
        }

        $workspace = [
            'type' => 'route_workspace',
            'target' => $routeName,
            'generated_at' => date('c'),
            'assembly' => [],
        ];

        // Route info
        $workspace['assembly'][] = [
            'component' => 'route',
            'evidence' => 'route_scanner',
            'data' => $route,
        ];

        // Middleware chain
        foreach ($route['middleware'] ?? [] as $mw) {
            $workspace['assembly'][] = [
                'component' => 'middleware',
                'name' => $mw,
                'evidence' => 'route_middleware',
            ];
        }

        // Controller
        $controllerName = $route['controller_short'] ?? null;
        if ($controllerName) {
            foreach ($allData['controllers']['items'] ?? [] as $ctrl) {
                if ($ctrl['name'] === $controllerName || str_ends_with($ctrl['fqcn'] ?? '', '\\' . $controllerName)) {
                    $workspace['assembly'][] = [
                        'component' => 'controller',
                        'name' => $ctrl['name'],
                        'file' => $ctrl['path'],
                        'methods' => $ctrl['methods'],
                        'constructor_dependencies' => $ctrl['constructor_dependencies'] ?? [],
                        'models_used' => $ctrl['models_used'] ?? [],
                        'events_dispatched' => $ctrl['events_dispatched'] ?? [],
                        'jobs_dispatched' => $ctrl['jobs_dispatched'] ?? [],
                        'views_returned' => $ctrl['views_returned'] ?? [],
                        'form_requests_used' => $ctrl['form_requests_used'] ?? [],
                        'public_methods' => $ctrl['public_methods'] ?? [],
                        'evidence' => 'controller_scanner',
                    ];

                    // Add views returned by this controller
                    foreach ($ctrl['views_returned'] ?? [] as $view) {
                        $viewName = $view['name'] ?? '';
                        foreach ($allData['blade']['views'] ?? [] as $bv) {
                            if ($bv['name'] === $viewName) {
                                $workspace['assembly'][] = [
                                    'component' => 'blade_view',
                                    'name' => $viewName,
                                    'file' => $bv['path'],
                                    'extends' => $bv['extends'],
                                    'sections' => $bv['sections'],
                                    'components' => $bv['components'],
                                    'includes' => $bv['includes'],
                                    'evidence' => 'blade_scanner',
                                ];

                                // Frontend data for this view
                                foreach ($allData['views']['forms'] ?? $allData['frontend']['forms'] ?? [] as $form) {
                                    if (($form['view_name'] ?? '') === $viewName) {
                                        $workspace['assembly'][] = [
                                            'component' => 'html_form',
                                            'view' => $viewName,
                                            'action' => $form['action'],
                                            'resolved_route' => $form['resolved_route'],
                                            'method' => $form['method'],
                                            'has_csrf' => $form['has_csrf'],
                                            'line' => $form['line'],
                                            'evidence' => 'view_scanner',
                                        ];
                                    }
                                }
                            }
                        }
                    }

                    // Add form requests
                    foreach ($ctrl['form_requests_used'] ?? [] as $req) {
                        $reqClass = $req['class'] ?? '';
                        foreach ($allData['form_requests']['items'] ?? [] as $fr) {
                            if ($fr['name'] === $reqClass || ($fr['namespace'] ?? '') . '\\' . $fr['name'] === $reqClass) {
                                $workspace['assembly'][] = [
                                    'component' => 'form_request',
                                    'name' => $fr['name'],
                                    'file' => $fr['path'],
                                    'rules' => $fr['rules'] ?? [],
                                    'evidence' => 'form_request_scanner',
                                ];
                            }
                        }
                    }

                    // Add policies checked
                    $policies = $ctrl['policy_checks'] ?? [];
                    foreach ($policies as $policy) {
                        $workspace['assembly'][] = [
                            'component' => 'policy_check',
                            'ability' => $policy['ability'],
                            'type' => $policy['type'],
                            'line' => $policy['line'] ?? null,
                            'evidence' => 'controller_scanner',
                        ];
                    }

                    break;
                }
            }
        }

        // JavaScript references to this route
        foreach ($allData['javascript']['route_references'] ?? [] as $rr) {
            if ($rr['route_name'] === $routeName) {
                $workspace['assembly'][] = [
                    'component' => 'js_reference',
                    'route' => $routeName,
                    'file' => $rr['file'],
                    'line' => $rr['line'],
                    'evidence' => 'javascript_scanner',
                ];

                // Also include the JS file's AJAX calls
                foreach ($allData['javascript']['ajax_calls'] ?? [] as $ajax) {
                    if (($ajax['file'] ?? '') === ($rr['file'] ?? '')) {
                        $workspace['assembly'][] = [
                            'component' => 'ajax_call',
                            'url' => $ajax['url'],
                            'method' => $ajax['method'],
                            'file' => $ajax['file'],
                            'line' => $ajax['line'],
                            'evidence' => 'javascript_scanner',
                        ];
                    }
                }
            }
        }

        // AJAX endpoints matching this route's URI
        foreach ($allData['javascript']['ajax_calls'] ?? [] as $ajax) {
            $ajaxUrl = trim($ajax['url'] ?? '', "'\" \t\n\r\0\x0B");
            $ajaxPath = '/' . ltrim(parse_url($ajaxUrl, PHP_URL_PATH) ?: $ajaxUrl, '/');
            $routeUri = '/' . ltrim($route['uri'] ?? '', '/');

            if ($ajaxPath === $routeUri) {
                $workspace['assembly'][] = [
                    'component' => 'ajax_endpoint',
                    'url' => $ajaxUrl,
                    'method' => $ajax['method'],
                    'file' => $ajax['file'],
                    'line' => $ajax['line'],
                    'evidence' => 'url_matched_route',
                ];
            }
        }

        // DataTables referencing this route
        foreach ($allData['javascript']['data_tables'] ?? [] as $dt) {
            $ajaxUrl = trim($dt['ajax_url'] ?? '', "'\" \t\n\r\0\x0B");
            $ajaxPath = '/' . ltrim(parse_url($ajaxUrl, PHP_URL_PATH) ?: $ajaxUrl, '/');
            $routeUri = '/' . ltrim($route['uri'] ?? '', '/');

            if ($ajaxPath === $routeUri) {
                $workspace['assembly'][] = [
                    'component' => 'datatable',
                    'selector' => $dt['selector'],
                    'ajax_url' => $dt['ajax_url'],
                    'server_side' => $dt['server_side'],
                    'columns' => $dt['columns'],
                    'file' => $dt['file'],
                    'evidence' => 'javascript_scanner',
                ];
            }
        }

        return $this->finalizeWorkspace($workspace);
    }

    /**
     * Generate a focused workspace for a Blade view.
     *
     * @return array<string, mixed>
     */
    public function forView(string $viewName, array $allData): array
    {
        $workspace = [
            'type' => 'view_workspace',
            'target' => $viewName,
            'generated_at' => date('c'),
            'assembly' => [],
        ];

        // View info
        $viewData = null;
        foreach ($allData['blade']['views'] ?? [] as $bv) {
            if ($bv['name'] === $viewName) {
                $viewData = $bv;
                break;
            }
        }

        if ($viewData === null) {
            return ['error' => "View '{$viewName}' not found."];
        }

        $workspace['assembly'][] = [
            'component' => 'blade_view',
            'name' => $viewName,
            'file' => $viewData['path'],
            'extends' => $viewData['extends'],
            'sections' => $viewData['sections'],
            'components' => $viewData['components'],
            'includes' => $viewData['includes'],
            'stacks' => $viewData['stacks'],
            'pushes' => $viewData['pushes'],
            'evidence' => 'blade_scanner',
        ];

        // Frontend elements from this view
        foreach ($allData['views']['forms'] ?? $allData['frontend']['forms'] ?? [] as $form) {
            if (($form['view_name'] ?? '') === $viewName) {
                $workspace['assembly'][] = [
                    'component' => 'html_form',
                    'action' => $form['action'],
                    'resolved_route' => $form['resolved_route'],
                    'method' => $form['method'],
                    'has_csrf' => $form['has_csrf'],
                    'wire_submit' => $form['wire_submit'] ?? null,
                    'line' => $form['line'],
                    'evidence' => 'view_scanner',
                ];
            }
        }

        // Elements from this view
        foreach ($allData['views']['elements'] ?? $allData['frontend']['elements'] ?? [] as $el) {
            if (($el['view_name'] ?? '') === $viewName) {
                $workspace['assembly'][] = [
                    'component' => 'ui_element',
                    'type' => $el['type'],
                    'wire' => $el['wire'] ?? [],
                    'alpine' => $el['alpine'] ?? [],
                    'x_on' => $el['x_on'] ?? [],
                    'x_data' => $el['x_data'] ?? null,
                    'line' => $el['line'],
                    'evidence' => 'view_scanner',
                ];
            }
        }

        // Controllers returning this view
        foreach ($allData['controllers']['items'] ?? [] as $ctrl) {
            foreach ($ctrl['views_returned'] ?? [] as $view) {
                if (($view['name'] ?? '') === $viewName) {
                    $workspace['assembly'][] = [
                        'component' => 'controller',
                        'name' => $ctrl['name'],
                        'file' => $ctrl['path'],
                        'method' => $ctrl['methods'] ?? [],
                        'models_used' => $ctrl['models_used'] ?? [],
                        'events_dispatched' => $ctrl['events_dispatched'] ?? [],
                        'evidence' => 'controller_scanner',
                    ];

                    // Routes to this controller
                    foreach ($allData['routes']['items'] ?? [] as $route) {
                        if (($route['controller_short'] ?? '') === $ctrl['name']) {
                            $workspace['assembly'][] = [
                                'component' => 'route',
                                'uri' => $route['uri'],
                                'methods' => $route['methods'],
                                'name' => $route['name'],
                                'middleware' => $route['middleware'],
                                'evidence' => 'route_scanner',
                            ];
                        }
                    }
                }
            }
        }

        // JavaScript files that reference routes used by this view
        foreach ($allData['javascript']['route_references'] ?? [] as $rr) {
            foreach ($allData['routes']['items'] ?? [] as $route) {
                if ($route['name'] === $rr['route_name'] || $route['uri'] === $rr['route_name']) {
                    if ($this->controllerReturnsView($route, $viewName, $allData)) {
                        $workspace['assembly'][] = [
                            'component' => 'js_route_reference',
                            'route_name' => $rr['route_name'],
                            'file' => $rr['file'],
                            'line' => $rr['line'],
                            'evidence' => 'javascript_scanner',
                        ];
                    }
                }
            }
        }

        return $this->finalizeWorkspace($workspace);
    }

    /**
     * Generate an AI task package — all context needed to implement a feature.
     *
     * @param string $taskDescription Description of what the AI needs to do
     * @param array $targets Array of target files/routes/views to include
     * @param array $allData All scanned project data
     * @return array<string, mixed>
     */
    public function taskPackage(string $taskDescription, array $targets, array $allData): array
    {
        $package = [
            'package' => [
                'task' => $taskDescription,
                'generated_at' => date('c'),
                'summary' => $this->generateTaskSummary($targets, $allData),
                'architecture' => $this->generateArchitectureContext($targets, $allData),
                'affected_files' => [],
                'editing_order' => [],
                'dependencies' => [],
                'warnings' => [],
                'risks' => [
                    'level' => 'LOW',
                    'details' => [],
                ],
                'evidence' => [],
                'unknown_areas' => [],
            ],
        ];

        $allFiles = [];
        $allWarnings = [];

        // Collect all workspaces for targets
        foreach ($targets as $target) {
            $workspace = match ($target['type'] ?? '') {
                'route' => $this->forRoute($target['name'], $allData),
                'view' => $this->forView($target['name'], $allData),
                default => [],
            };

            if (isset($workspace['assembly'])) {
                foreach ($workspace['assembly'] as $item) {
                    if (isset($item['file']) && !in_array($item['file'], $allFiles)) {
                        $allFiles[] = $item['file'];
                        $component_type = $item['component'] ?? 'unknown';
                        $package['package']['affected_files'][] = [
                            'file' => $item['file'],
                            'component' => $component_type,
                            'name' => $item['name'] ?? $item['component'] ?? '',
                            'evidence' => $item['evidence'] ?? 'workspace_assembly',
                        ];
                    }
                }
            }
        }

        // Deduplicate affected files
        $seen = [];
        $package['package']['affected_files'] = array_values(array_filter(
            $package['package']['affected_files'],
            fn($f) => !in_array($f['file'], $seen) && array_push($seen, $f['file'])
        ));

        // Generate editing order (backend first, then frontend)
        $order = [];
        foreach ($package['package']['affected_files'] as $af) {
            $order[] = [
                'file' => $af['file'],
                'type' => $af['component'],
                'order' => $this->editingPriority($af['component']),
            ];
        }
        usort($order, fn($a, $b) => $a['order'] <=> $b['order']);
        $package['package']['editing_order'] = $order;

        // Determine risk level
        $routeCount = count(array_filter($package['package']['affected_files'], fn($f) => $f['component'] === 'route'));
        $controllerCount = count(array_filter($package['package']['affected_files'], fn($f) => $f['component'] === 'controller'));
        $modelCount = count(array_filter($package['package']['affected_files'], fn($f) => $f['component'] === 'model'));

        if ($routeCount > 5 || $controllerCount > 3 || $modelCount > 3) {
            $package['package']['risks']['level'] = 'HIGH';
            $package['package']['risks']['details'][] = "{$routeCount} routes, {$controllerCount} controllers, {$modelCount} models affected";
        } elseif ($routeCount > 2 || $controllerCount > 1) {
            $package['package']['risks']['level'] = 'MEDIUM';
            $package['package']['risks']['details'][] = "Multiple components affected";
        }

        $package['package']['warnings'] = $allWarnings;

        return $package;
    }

    /**
     * Generate safe editing context for a single file.
     *
     * @return array<string, mixed>
     */
    public function safeEditContext(string $file, array $allData): array
    {
        $fileType = $this->detectFileType($file);
        $context = [
            'file' => $file,
            'type' => $fileType,
            'depends_on' => [],
            'depended_by' => [],
            'evidence' => [],
        ];

        // If it's a controller, find everything it depends on and depends on it
        if ($fileType === 'controller') {
            $ctrlName = pathinfo($file, PATHINFO_FILENAME);
            foreach ($allData['controllers']['items'] ?? [] as $ctrl) {
                if ($ctrl['name'] !== $ctrlName) continue;

                // What this controller depends on
                foreach ($ctrl['constructor_dependencies'] ?? [] as $dep) {
                    $context['depends_on'][] = [
                        'type' => 'service',
                        'name' => $dep['class'],
                        'evidence' => 'constructor_injection',
                        'line' => $dep['line'] ?? null,
                    ];
                }

                foreach ($ctrl['models_used'] ?? [] as $model) {
                    $context['depends_on'][] = [
                        'type' => 'model',
                        'name' => $model['class'],
                        'evidence' => 'proven_model_usage',
                        'lines' => $model['lines'] ?? [],
                    ];
                }

                // What depends on this controller
                foreach ($allData['routes']['items'] ?? [] as $route) {
                    if (($route['controller_short'] ?? '') === $ctrlName) {
                        $context['depended_by'][] = [
                            'type' => 'route',
                            'name' => $route['name'] ?? $route['uri'],
                            'uri' => $route['uri'],
                            'method' => $route['method'],
                            'evidence' => 'route_action',
                        ];
                    }
                }

                foreach ($ctrl['views_returned'] ?? [] as $view) {
                    $context['depended_by'][] = [
                        'type' => 'blade_view',
                        'name' => $view['name'],
                        'evidence' => 'view_return',
                        'line' => $view['line'] ?? null,
                    ];
                }
            }
        }

        // If it's a view
        if ($fileType === 'view') {
            $viewName = str_replace(['.blade.php', '/'], ['', '.'], $file);
            foreach ($allData['controllers']['items'] ?? [] as $ctrl) {
                foreach ($ctrl['views_returned'] ?? [] as $view) {
                    if (($view['name'] ?? '') === $viewName) {
                        $context['depended_by'][] = [
                            'type' => 'controller',
                            'name' => $ctrl['name'],
                            'evidence' => 'view_return',
                        ];
                    }
                }
            }

            foreach ($allData['views']['forms'] ?? $allData['frontend']['forms'] ?? [] as $form) {
                if (($form['view_name'] ?? '') === $viewName) {
                    $context['depends_on'][] = [
                        'type' => 'ajax_endpoint',
                        'action' => $form['action'],
                        'evidence' => 'html_form',
                    ];
                }
            }
        }

        return $context;
    }

    private function detectFileType(string $file): string
    {
        if (str_contains($file, 'Http/Controllers')) return 'controller';
        if (str_contains($file, 'Models')) return 'model';
        if (str_contains($file, 'Services')) return 'service';
        if (str_contains($file, 'Http/Requests')) return 'form_request';
        if (str_contains($file, 'resources/views') || str_contains($file, '.blade.php')) return 'view';
        if (str_contains($file, '.js')) return 'javascript';
        return 'unknown';
    }

    private function controllerReturnsView(array $route, string $viewName, array $allData): bool
    {
        $ctrlShort = $route['controller_short'] ?? '';
        foreach ($allData['controllers']['items'] ?? [] as $ctrl) {
            if ($ctrl['name'] === $ctrlShort) {
                foreach ($ctrl['views_returned'] ?? [] as $view) {
                    if (($view['name'] ?? '') === $viewName) return true;
                }
            }
        }
        return false;
    }

    private function generateTaskSummary(array $targets, array $allData): array
    {
        $summary = [
            'targets' => count($targets),
            'routes' => 0,
            'controllers' => 0,
            'models' => 0,
            'views' => 0,
            'js_references' => count($allData['javascript']['route_references'] ?? []),
            'ajax_calls' => count($allData['javascript']['ajax_calls'] ?? []),
            'total_routes' => count($allData['routes']['items'] ?? []),
            'total_controllers' => count($allData['controllers']['items'] ?? []),
        ];

        foreach ($targets as $target) {
            if (($target['type'] ?? '') === 'route') $summary['routes']++;
            if (($target['type'] ?? '') === 'view') $summary['views']++;
        }

        return $summary;
    }

    private function generateArchitectureContext(array $targets, array $allData): array
    {
        $layers = [
            'routes' => count($allData['routes']['items'] ?? []),
            'controllers' => count($allData['controllers']['items'] ?? []),
            'models' => count($allData['models']['items'] ?? []),
            'views' => count($allData['blade']['views'] ?? []),
            'services' => count($allData['services']['items'] ?? []),
            'events' => count($allData['events']['items'] ?? []),
            'jobs' => count($allData['jobs']['items'] ?? []),
            'notifications' => count($allData['notifications']['items'] ?? []),
        ];

        return [
            'framework' => 'Laravel ' . ($allData['framework']['version'] ?? '?'),
            'layers' => $layers,
        ];
    }

    private function editingPriority(string $component): int
    {
        return match ($component) {
            'model' => 1,
            'migration' => 2,
            'service' => 3,
            'repository' => 4,
            'controller' => 5,
            'form_request' => 6,
            'policy' => 7,
            'route' => 8,
            'blade_view' => 9,
            'html_form' => 10,
            'js_reference' => 11,
            'ajax_call' => 12,
            default => 50,
        };
    }

    private function finalizeWorkspace(array $workspace): array
    {
        $workspace['summary'] = [
            'components' => count($workspace['assembly']),
            'types' => array_count_values(array_column($workspace['assembly'], 'component')),
        ];
        return $workspace;
    }
}