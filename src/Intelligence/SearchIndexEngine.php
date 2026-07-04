<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Search Index — pre-computed answers to common questions.
 * Allows AI to answer "Where is login implemented?" instantly.
 */
class SearchIndexEngine
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $entries = [];

        // Common questions about where things live
        $this->addQuestion($entries, 'Where is login implemented?', $this->locateLogin($data));
        $this->addQuestion($entries, 'Where is authentication?', $this->locateAuthentication($data));
        $this->addQuestion($entries, 'Where are payments?', $this->locatePayments($data));
        $this->addQuestion($entries, 'Where is the dashboard?', $this->locateDashboard($data));
        $this->addQuestion($entries, 'Where are uploads?', $this->locateUploads($data));
        $this->addQuestion($entries, 'Where are notifications?', $this->locateNotifications($data));
        $this->addQuestion($entries, 'Where is validation?', $this->locateValidation($data));
        $this->addQuestion($entries, 'Where are queues?', $this->locateQueues($data));
        $this->addQuestion($entries, 'Where are scheduled tasks?', $this->locateSchedules($data));
        $this->addQuestion($entries, 'Where is business logic?', $this->locateBusinessLogic($data));
        $this->addQuestion($entries, 'Where are API endpoints?', $this->locateApi($data));
        $this->addQuestion($entries, 'Where are admin routes?', $this->locateAdmin($data));
        $this->addQuestion($entries, 'Where are policies?', $this->locatePolicies($data));
        $this->addQuestion($entries, 'Where are events?', $this->locateEvents($data));
        $this->addQuestion($entries, 'Where are jobs?', $this->locateJobs($data));
        $this->addQuestion($entries, 'Where are mail classes?', $this->locateMail($data));
        $this->addQuestion($entries, 'Where are database migrations?', $this->locateMigrations($data));
        $this->addQuestion($entries, 'Where are tests?', $this->locateTests($data));
        $this->addQuestion($entries, 'Where is the home page?', $this->locateHome($data));
        $this->addQuestion($entries, 'Where are custom helpers?', $this->locateHelpers($data));

        return [
            'search_index' => [
                'count' => count($entries),
                'entries' => $entries,
                'confidence' => 85,
            ],
        ];
    }

    private function addQuestion(array &$entries, string $question, array $answer): void
    {
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $question));
        $entries[] = [
            'id' => 'q:' . $slug,
            'question' => $question,
            'answer' => $answer,
            'keywords' => explode(' ', strtolower($question)),
        ];
    }

    private function searchControllers(array $data, string $keyword): array
    {
        $results = [];
        foreach ($data['controllers']['items'] ?? [] as $c) {
            if (str_contains(strtolower($c['name']), $keyword)) {
                $results[] = $c['name'];
            }
        }
        return $results;
    }

    private function searchRoutes(array $data, string $keyword): array
    {
        $results = [];
        foreach ($data['routes']['items'] ?? [] as $r) {
            if (str_contains(strtolower($r['uri'] ?? ''), $keyword)) {
                $results[] = $r['uri'];
            }
        }
        return $results;
    }

    private function locateLogin(array $data): array
    {
        return [
            'summary' => 'Login is implemented via authentication routes and controllers',
            'routes' => $this->searchRoutes($data, 'login'),
            'controllers' => $this->searchControllers($data, 'auth'),
            'views' => ['resources/views/auth/login.blade.php'],
            'middleware' => [],
            'models' => ['User'],
            'confidence' => 85,
        ];
    }

    private function locateAuthentication(array $data): array
    {
        return [
            'summary' => 'Authentication system across multiple layers',
            'routes' => array_merge(
                $this->searchRoutes($data, 'login'),
                $this->searchRoutes($data, 'register'),
                $this->searchRoutes($data, 'password'),
                $this->searchRoutes($data, 'logout'),
                $this->searchRoutes($data, 'auth')
            ),
            'controllers' => $this->searchControllers($data, 'auth'),
            'config' => ['config/auth.php'],
            'models' => ['User'],
            'package_info' => $data['api']['authentication'] ?? [],
            'confidence' => 85,
        ];
    }

    private function locatePayments(array $data): array
    {
        $services = [];
        $packages = [];
        foreach ($data['services']['items'] ?? [] as $s) {
            if (str_contains($s['name'], 'Payment') || str_contains($s['name'], 'Invoice') || str_contains($s['name'], 'Billing')) {
                $services[] = $s['name'];
            }
        }
        foreach ($data['packages']['items'] ?? [] as $p) {
            if ($p['category'] === 'payments') {
                $packages[] = $p['name'];
            }
        }
        return [
            'summary' => ($packages ? 'Payment processing via ' . implode(', ', $packages) : 'No payment packages detected'),
            'services' => $services,
            'packages' => $packages,
            'controllers' => $this->searchControllers($data, 'payment'),
            'routes' => $this->searchRoutes($data, 'payment'),
            'confidence' => 80,
        ];
    }

    private function locateDashboard(array $data): array
    {
        return [
            'summary' => 'Dashboard routes and controllers',
            'routes' => $this->searchRoutes($data, 'dashboard'),
            'controllers' => $this->searchControllers($data, 'dashboard'),
            'confidence' => 85,
        ];
    }

    private function locateUploads(array $data): array
    {
        $result = ['summary' => 'File upload and storage handling', 'files' => []];
        foreach ($data['storage']['upload_paths'] ?? [] as $u) {
            $result['files'][] = $u['file'];
        }
        $result['disks'] = $data['storage']['disks'] ?? [];
        $result['symlinks'] = $data['storage']['public_links'] ?? [];
        $result['confidence'] = 80;
        return $result;
    }

    private function locateNotifications(array $data): array
    {
        $result = ['summary' => 'Notification classes', 'files' => []];
        foreach ($data['notifications']['items'] ?? [] as $n) {
            $result['files'][] = $n['path'];
        }
        $result['count'] = count($data['notifications']['items'] ?? []);
        $result['channels'] = [];
        foreach ($data['notifications']['items'] ?? [] as $n) {
            foreach ($n['channels'] ?? [] as $ch) {
                $result['channels'][$ch] = ($result['channels'][$ch] ?? 0) + 1;
            }
        }
        $result['confidence'] = 85;
        return $result;
    }

    private function locateValidation(array $data): array
    {
        $result = ['summary' => 'Form Request validation classes', 'files' => []];
        foreach ($data['form_requests']['items'] ?? [] as $r) {
            $result['files'][] = $r['path'];
        }
        $result['count'] = $data['form_requests']['count'] ?? 0;
        $result['confidence'] = 90;
        return $result;
    }

    private function locateQueues(array $data): array
    {
        $result = ['summary' => 'Queue and job processing', 'jobs' => [], 'config' => $data['queue'] ?? []];
        foreach ($data['jobs']['items'] ?? [] as $j) {
            $result['jobs'][] = $j['name'];
        }
        $result['count'] = $data['jobs']['count'] ?? 0;
        $result['confidence'] = 85;
        return $result;
    }

    private function locateSchedules(array $data): array
    {
        $result = ['summary' => 'Scheduled tasks (cron)', 'files' => ['app/Console/Kernel.php']];
        foreach ($data['entry_points']['items'] ?? [] as $ep) {
            if (($ep['type'] ?? '') === 'schedule') {
                $result['tasks'] = $ep['tasks'] ?? [];
            }
        }
        $result['confidence'] = 80;
        return $result;
    }

    private function locateBusinessLogic(array $data): array
    {
        $result = ['summary' => 'Business logic layer', 'services' => []];
        foreach ($data['services']['items'] ?? [] as $s) {
            $result['services'][] = $s['name'];
        }
        foreach ($data['repositories']['items'] ?? [] as $r) {
            $result['repositories'][] = $r['name'];
        }
        $result['service_count'] = $data['services']['count'] ?? 0;
        $result['repository_count'] = $data['repositories']['count'] ?? 0;
        $result['confidence'] = 85;
        return $result;
    }

    private function locateApi(array $data): array
    {
        $result = ['summary' => 'REST API endpoints', 'routes' => []];
        foreach ($data['routes']['items'] ?? [] as $r) {
            if (str_starts_with($r['uri'] ?? '', 'api')) {
                $result['routes'][] = $r['uri'];
            }
        }
        $result['count'] = count($result['routes']);
        $result['resources'] = array_map(fn($res) => $res['path'], $data['api']['resources'] ?? []);
        $result['auth'] = $data['api']['authentication'] ?? [];
        $result['controllers'] = array_map(fn($c) => $c['name'], $data['api']['controllers'] ?? []);
        $result['confidence'] = 90;
        return $result;
    }

    private function locateAdmin(array $data): array
    {
        $result = ['summary' => 'Admin panel routes', 'routes' => []];
        foreach ($data['routes']['items'] ?? [] as $r) {
            if (str_starts_with($r['uri'] ?? '', 'admin')) {
                $result['routes'][] = $r['uri'];
            }
        }
        $result['count'] = count($result['routes']);
        $result['confidence'] = 90;
        return $result;
    }

    private function locatePolicies(array $data): array
    {
        $result = ['summary' => 'Authorization policies', 'policies' => []];
        foreach ($data['policies']['items'] ?? [] as $p) {
            $result['policies'][] = ['name' => $p['name'], 'model' => $p['model'], 'abilities' => $p['abilities'] ?? []];
        }
        $result['count'] = $data['policies']['count'] ?? 0;
        $result['confidence'] = 90;
        return $result;
    }

    private function locateEvents(array $data): array
    {
        $result = ['summary' => 'Event system', 'events' => []];
        foreach ($data['events']['items'] ?? [] as $e) {
            $result['events'][] = $e['name'];
        }
        $result['count'] = $data['events']['count'] ?? 0;
        $result['listeners'] = array_map(fn($l) => $l['name'], $data['events']['listeners'] ?? []);
        $result['confidence'] = 85;
        return $result;
    }

    private function locateJobs(array $data): array
    {
        $result = ['summary' => 'Background jobs', 'jobs' => []];
        foreach ($data['jobs']['items'] ?? [] as $j) {
            $result['jobs'][] = ['name' => $j['name'], 'queued' => $j['queued']];
        }
        $result['count'] = $data['jobs']['count'] ?? 0;
        $result['confidence'] = 85;
        return $result;
    }

    private function locateMail(array $data): array
    {
        $result = ['summary' => 'Mail classes', 'files' => []];
        foreach ($data['mail']['items'] ?? [] as $m) {
            $result['files'][] = $m['path'];
        }
        $result['count'] = $data['mail']['count'] ?? 0;
        $result['confidence'] = 85;
        return $result;
    }

    private function locateMigrations(array $data): array
    {
        return [
            'summary' => 'Database migration files',
            'directory' => 'database/migrations/',
            'count' => $data['statistics']['database_tables'] ?? 0,
            'confidence' => 95,
        ];
    }

    private function locateTests(array $data): array
    {
        return [
            'summary' => 'Test files',
            'directories' => ['tests/', 'tests/Feature/', 'tests/Unit/'],
            'confidence' => 90,
        ];
    }

    private function locateHome(array $data): array
    {
        $homeRoute = null;
        foreach ($data['routes']['items'] ?? [] as $r) {
            if ($r['uri'] === '/' || $r['uri'] === 'home') {
                $homeRoute = $r;
                break;
            }
        }
        return [
            'summary' => 'Home page' . ($homeRoute ? " at /" : ' not explicitly defined'),
            'route' => $homeRoute ? $homeRoute['uri'] : '/',
            'controller' => $homeRoute ? $homeRoute['action'] : null,
            'confidence' => 85,
        ];
    }

    private function locateHelpers(array $data): array
    {
        $result = ['summary' => 'Custom helper files and functions', 'files' => [], 'functions' => []];
        foreach ($data['helpers']['files'] ?? [] as $f) {
            $result['files'][] = $f['path'];
            foreach ($f['functions'] ?? [] as $fn) {
                $result['functions'][] = $fn['name'];
            }
        }
        $result['count'] = $data['helpers']['count'] ?? 0;
        $result['autoload'] = $data['helpers']['autoload_files'] ?? [];
        $result['confidence'] = 80;
        return $result;
    }
}