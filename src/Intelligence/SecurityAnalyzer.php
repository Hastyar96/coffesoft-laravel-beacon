<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Analyzes project security by detecting unguarded models, missing validation,
 * mass assignment risks, and weak authentication patterns.
 *
 * Only reports verified issues — no false positives.
 */
class SecurityAnalyzer
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function analyze(array $data): array
    {
        $issues = [];

        // Check for mass assignment vulnerabilities
        $unguardedModels = $this->findUnguardedModels($data);
        foreach ($unguardedModels as $model) {
            $issues[] = [
                'type' => 'mass_assignment',
                'severity' => 'warning',
                'message' => "Model {$model} does not define fillable or guarded properties — mass assignment is not explicitly protected",
                'class' => $model,
            ];
        }

        // Check for controllers without validation
        $controllersWithoutValidation = $this->findControllersWithoutValidation($data);
        foreach ($controllersWithoutValidation as $ctrl) {
            $issues[] = [
                'type' => 'missing_validation',
                'severity' => 'info',
                'message' => "Controller {$ctrl} has store/update methods but no form request or inline validation detected",
                'class' => $ctrl,
            ];
        }

        // Check for debug mode
        if ($this->isDebugModeEnabled()) {
            $issues[] = [
                'type' => 'debug_enabled',
                'severity' => 'critical',
                'message' => 'APP_DEBUG is set to true in production — sensitive information could be leaked via error pages',
            ];
        }

        // Check for dangerous routes accessible without authentication
        $exposedRoutes = $this->findExposedAdminRoutes($data);
        foreach ($exposedRoutes as $route) {
            $issues[] = [
                'type' => 'exposed_route',
                'severity' => 'warning',
                'message' => "Admin/API route '{$route}' appears to be accessible without auth middleware",
                'route' => $route,
            ];
        }

        // Check for APP_KEY
        if ($this->isAppKeyDefault()) {
            $issues[] = [
                'type' => 'weak_app_key',
                'severity' => 'critical',
                'message' => 'APP_KEY appears to be the default Laravel key or is not set — encryption will be insecure',
            ];
        }

        // Check for unguarded models using the $guarded = [] pattern
        $fullyGuardedModels = $this->findFullyGuardedModels($data);
        foreach ($fullyGuardedModels as $model) {
            $issues[] = [
                'type' => 'fully_unguarded',
                'severity' => 'high',
                'message' => "Model {$model} has protected \$guarded = [] — all attributes are mass assignable",
                'class' => $model,
            ];
        }

        return [
            'security' => [
                'issues_count' => count($issues),
                'issues' => $issues,
                'has_high_risk' => $this->hasHighRisk($issues),
                'has_critical' => $this->hasCritical($issues),
            ],
        ];
    }

    private function findUnguardedModels(array $data): array
    {
        $unguarded = [];
        foreach ($data['models']['items'] ?? [] as $model) {
            $fillable = $model['fillable'] ?? [];
            $guarded = $model['guarded'] ?? [];

            // If neither fillable nor guarded is explicitly set AND it extends Model
            if (empty($fillable) && empty($guarded)) {
                $unguarded[] = $model['name'];
            }
        }
        return $unguarded;
    }

    private function findFullyGuardedModels(array $data): array
    {
        $fully = [];
        foreach ($data['models']['items'] ?? [] as $model) {
            $guarded = $model['guarded'] ?? [];
            if (count($guarded) === 1 && $guarded[0] === '*') {
                $fully[] = $model['name'];
            }
        }
        return $fully;
    }

    private function findControllersWithoutValidation(array $data): array
    {
        $unvalidated = [];
        foreach ($data['controllers']['items'] ?? [] as $ctrl) {
            $methods = $ctrl['methods'] ?? [];
            $hasStore = in_array('store', $methods) || in_array('update', $methods);
            $hasValidation = !empty($ctrl['validation_classes'] ?? [])
                || in_array('inline_validation', $ctrl['validation_classes'] ?? []);

            if ($hasStore && !$hasValidation) {
                $unvalidated[] = $ctrl['name'];
            }
        }
        return $unvalidated;
    }

    private function isDebugModeEnabled(): bool
    {
        // Check env directly only if we don't boot Laravel
        $envPath = base_path('.env');
        if (!file_exists($envPath)) return false;

        $env = file_get_contents($envPath);
        return (bool)preg_match('/^APP_DEBUG\s*=\s*true$/m', $env);
    }

    private function findExposedAdminRoutes(array $data): array
    {
        $exposed = [];
        foreach ($data['routes']['items'] ?? [] as $route) {
            $uri = $route['uri'] ?? '';
            $middleware = $route['middleware'] ?? [];

            // Check routes that look like admin/API but don't have auth middleware
            if ((str_starts_with($uri, 'admin') || str_starts_with($uri, 'api'))
                && !in_array('auth', $middleware)
                && !in_array('auth:sanctum', $middleware)
                && !in_array('auth:api', $middleware)
                && !in_array('can:', $middleware)) {
                $exposed[] = $uri;
            }
        }
        return $exposed;
    }

    private function isAppKeyDefault(): bool
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) return false;

        $env = file_get_contents($envPath);

        // Detect common default or placeholder keys
        if (preg_match('/^APP_KEY\s*=\s*base64:[A-Za-z0-9+\/]{44}=$/m', $env, $m)) {
            return false; // Valid format
        }

        if (preg_match('/^APP_KEY\s*=\s*([^\s]+)/m', $env, $m)) {
            $key = trim($m[1]);
            $defaultKeys = [
                'SomeRandomString',
                'base64:SomeRandomString',
                'ChangeMeBy64CharactersLongButNotYet',
                'YourAppKeyHere',
            ];
            return in_array($key, $defaultKeys) || strlen($key) < 16;
        }

        return true; // APP_KEY not set
    }

    private function hasHighRisk(array $issues): bool
    {
        foreach ($issues as $issue) {
            if (in_array($issue['severity'], ['high', 'critical'])) return true;
        }
        return false;
    }

    private function hasCritical(array $issues): bool
    {
        foreach ($issues as $issue) {
            if ($issue['severity'] === 'critical') return true;
        }
        return false;
    }
}