<?php

declare(strict_types=1);

namespace Coffessoft\LaravelBeacon\Scanners;

use Coffessoft\LaravelBeacon\Contracts\Scanner;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Route as RouteItem;

/**
 * Scanner that reads Laravel route definitions.
 *
 * Uses the Route facade to collect all registered
 * routes with their URI, methods, name, action, and middleware.
 */
class RouteScanner implements Scanner
{
    /**
     * Scan registered routes and return route metadata.
     *
     * @return array<string, mixed>
     */
    public function scan(): array
    {
        $routes = Route::getRoutes();

        $items = [];

        foreach ($routes as $route) {
            $items[] = $this->buildRouteItem($route);
        }

        return [
            'routes' => [
                'count' => count($items),
                'items' => $items,
            ],
        ];
    }

    /**
     * Build a structured route item from a Route instance.
     *
     * @return array<string, mixed>
     */
    private function buildRouteItem(RouteItem $route): array
    {
        return [
            'uri' => $route->uri(),
            'methods' => $route->methods(),
            'name' => $route->getName(),
            'action' => $route->getActionName(),
            'middleware' => $route->middleware(),
        ];
    }
}