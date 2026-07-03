<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Scanner;

use Coffesoft\LaravelBeacon\Reader\FileReader;

class MiddlewareScanner
{
    public function __construct(private readonly FileReader $reader) {}

    public function scan(): array
    {
        $path = app_path('Http/Middleware');
        $files = $this->reader->getPhpFiles($path);
        $items = [];

        foreach ($files as $file) {
            $contents = $this->reader->read($file->getPathname());
            $name = $this->reader->extractClassName($contents);
            if ($name === null) continue;

            $items[] = [
                'name' => $name,
                'namespace' => $this->reader->extractNamespace($contents) ?? 'App\\Http\\Middleware',
                'path' => $file->getRelativePathname(),
                'handle_params' => $this->reader->extractConstructorParams($contents),
                'methods' => $this->reader->extractPublicMethods($contents),
            ];
        }

        // Detect registered middleware from Kernel
        $kernel = $this->readKernelMiddleware();

        return [
            'middleware' => [
                'count' => count($items),
                'items' => $items,
                'registered' => $kernel,
            ],
        ];
    }

    private function readKernelMiddleware(): array
    {
        $registered = [];

        foreach (['app/Http/Kernel.php', 'bootstrap/app.php'] as $kernelPath) {
            $bp = base_path($kernelPath);
            if (! file_exists($bp)) continue;

            $contents = file_get_contents($bp);
            if ($contents === false) continue;

            if (preg_match('/\$middleware\s*=\s*\[([^\]]*)\]/s', $contents, $m)) {
                $registered['global'] = array_map('trim', explode(',', $m[1]));
            }
            if (preg_match('/\$routeMiddleware\s*=\s*\[([^\]]*)\]/s', $contents, $m)) {
                $registered['route_middleware'] = array_map('trim', explode(',', $m[1]));
            }
            if (preg_match('/\$middlewareGroups\s*=\s*\[([^\]]*)\]/s', $contents, $m)) {
                $registered['groups'] = array_map('trim', explode(',', $m[1]));
            }
        }

        return $registered;
    }
}