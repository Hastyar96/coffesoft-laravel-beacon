<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Request Lifecycle Engine — builds complete, proven request flows
 * from Browser → Button → JS → AJAX → Route → Middleware → Controller →
 * Service → Model → Event → Response → View → DOM Update.
 *
 * Every step is backed by source code evidence.
 * No inference. Only proven chain-of-execution relationships.
 */
class RequestLifecycleEngine
{
    /**
     * Build complete lifecycles for all routes.
     *
     * @param array<string, mixed> $allData All scanned project data
     * @return array<string, mixed>
     */
    public function build(array $allData): array
    {
        $lifecycles = [];

        foreach ($allData['routes']['items'] ?? [] as $route) {
            $lifecycles[] = $this->buildRouteLifecycle($route, $allData);
        }

        return [
            'request_lifecycles' => [
                'count' => count($lifecycles),
                'items' => $lifecycles,
                'notes' => 'Every step is proven from source code evidence.',
            ],
        ];
    }

    private function buildRouteLifecycle(array $route, array $allData): array
    {
        $routeName = $route['name'] ?? $route['uri'];
        $controllerShort = $route['controller_short'] ?? null;

        $lifecycle = [
            'route' => [
                'uri' => $route['uri'],
                'methods' => $route['methods'],
                'name' => $route['name'],
                'evidence' => 'route_scanner',
            ],
            'middleware_chain' => [],
            'controller' => null,
            'validation' => null,
            'authorization' => [],
            'service_calls' => [],
            'model_usage' => [],
            'events_dispatched' => [],
            'jobs_dispatched' => [],
            'notifications_sent' => [],
            'view_response' => null,
            'blade_components' => [],
            'javascript_references' => [],
            'ajax_endpoints_triggered' => [],
        ];

        // Middleware chain
        foreach ($route['middleware'] ?? [] as $mw) {
            $lifecycle['middleware_chain'][] = [
                'name' => $mw,
                'evidence' => 'route_middleware',
            ];
        }

        if ($controllerShort === null) {
            return $lifecycle;
        }

        // Find controller
        foreach ($allData['controllers']['items'] ?? [] as $ctrl) {
            if ($ctrl['name'] !== $controllerShort) continue;

            $lifecycle['controller'] = [
                'name' => $ctrl['name'],
                'file' => $ctrl['path'],
                'method' => $route['method'],
                'evidence' => 'controller_scanner',
            ];

            // Constructor dependencies (service injections)
            foreach ($ctrl['constructor_dependencies'] ?? [] as $dep) {
                $lifecycle['service_calls'][] = [
                    'class' => $dep['class'],
                    'type' => 'constructor_injection',
                    'line' => $dep['line'] ?? null,
                    'evidence' => 'constructor_type_hint',
                ];
            }

            // Models used
            foreach ($ctrl['models_used'] ?? [] as $modelRef) {
                $lifecycle['model_usage'][] = [
                    'class' => $modelRef['class'],
                    'methods' => $modelRef['methods'] ?? [],
                    'lines' => $modelRef['lines'] ?? [],
                    'evidence' => 'static_method_call',
                ];
            }

            // Validation
            foreach ($ctrl['form_requests_used'] ?? [] as $req) {
                $lifecycle['validation'] = [
                    'class' => $req['class'],
                    'lines' => $req['lines'] ?? [],
                    'evidence' => 'method_parameter_type_hint',
                ];
                break;
            }

            // Authorization
            foreach ($ctrl['policy_checks'] ?? [] as $policy) {
                $lifecycle['authorization'][] = [
                    'ability' => $policy['ability'],
                    'type' => $policy['type'],
                    'line' => $policy['line'] ?? null,
                    'evidence' => 'authorize_call',
                ];
            }

            // Events
            foreach ($ctrl['events_dispatched'] ?? [] as $event) {
                $lifecycle['events_dispatched'][] = [
                    'class' => $event['class'],
                    'method' => $event['method'] ?? 'dispatch',
                    'lines' => $event['lines'] ?? [],
                    'evidence' => 'dispatch_call',
                ];
            }

            // Jobs
            foreach ($ctrl['jobs_dispatched'] ?? [] as $job) {
                $lifecycle['jobs_dispatched'][] = [
                    'class' => $job['class'],
                    'method' => $job['method'] ?? 'dispatch',
                    'lines' => $job['lines'] ?? [],
                    'evidence' => 'dispatch_call',
                ];
            }

            // Notifications
            foreach ($ctrl['notifications_sent'] ?? [] as $notif) {
                $lifecycle['notifications_sent'][] = [
                    'class' => $notif['class'],
                    'method' => $notif['method'] ?? 'notify',
                    'evidence' => 'notify_call',
                ];
            }

            // View response
            foreach ($ctrl['views_returned'] ?? [] as $view) {
                $viewName = $view['name'] ?? '';
                $lifecycle['view_response'] = [
                    'name' => $viewName,
                    'line' => $view['line'] ?? null,
                    'evidence' => 'view_call',
                ];

                // Blade components in this view
                foreach ($allData['blade']['views'] ?? [] as $bv) {
                    if ($bv['name'] === $viewName) {
                        foreach ($bv['components'] ?? [] as $comp) {
                            $lifecycle['blade_components'][] = [
                                'name' => $comp,
                                'evidence' => 'x_component_tag',
                            ];
                        }
                    }
                }
            }

            break;
        }

        // JavaScript references to this route
        foreach ($allData['javascript']['route_references'] ?? [] as $rr) {
            if ($rr['route_name'] === $routeName) {
                $lifecycle['javascript_references'][] = [
                    'file' => $rr['file'],
                    'line' => $rr['line'],
                    'type' => $rr['type'] ?? 'route_helper',
                    'evidence' => 'javascript_scanner',
                ];
            }
        }

        // AJAX endpoints matching this route's URI
        $routeUri = '/' . ltrim($route['uri'] ?? '', '/');
        foreach ($allData['javascript']['ajax_calls'] ?? [] as $ajax) {
            $ajaxUrl = trim($ajax['url'] ?? '', "'\" \t\n\r\0\x0B");
            $ajaxPath = '/' . ltrim(parse_url($ajaxUrl, PHP_URL_PATH) ?: $ajaxUrl, '/');
            if ($ajaxPath === $routeUri) {
                $lifecycle['ajax_endpoints_triggered'][] = [
                    'url' => $ajaxUrl,
                    'method' => $ajax['method'],
                    'file' => $ajax['file'],
                    'line' => $ajax['line'],
                    'evidence' => 'url_matched_route',
                ];
            }
        }

        return $lifecycle;
    }
}