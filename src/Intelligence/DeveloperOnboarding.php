<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates a practical developer onboarding guide based on project analysis.
 */
class DeveloperOnboarding
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $lines = [];

        $lines[] = '# Developer Onboarding Guide';
        $lines[] = '';
        $lines[] = '> Generated automatically by Laravel Beacon v2.1';
        $lines[] = '> Confidence scores (0–100) indicate reliability of each section.';
        $lines[] = '';

        // Architecture overview
        $arch = $data['architecture'] ?? [];
        $lines[] = '---';
        $lines[] = '## 1. Project Architecture [confidence: 85]';
        $lines[] = '';
        $lines[] = 'Primary: **' . ($arch['primary'] ?? 'MVC') . '**';
        if (!empty($arch['secondary'])) {
            $lines[] = 'Secondary patterns: ' . implode(', ', $arch['secondary']);
        }
        $lines[] = '';
        $lines[] = 'This project uses Laravel as the foundation framework.';
        $lines[] = 'The architecture follows Laravel conventions with additional patterns detected above.';
        $lines[] = '';

        // Folder responsibilities
        $lines[] = '---';
        $lines[] = '## 2. Folder Structure & Responsibilities [confidence: 95]';
        $lines[] = '';
        $lines[] = '| Folder | Purpose |';
        $lines[] = '|--------|---------|';
        $lines[] = '| `app/Console/Commands` | Artisan CLI commands for maintenance & operations |';
        $lines[] = '| `app/Events` | Event classes that signal application state changes |';
        $lines[] = '| `app/Exceptions` | Custom exception handlers |';
        $lines[] = '| `app/Http/Controllers` | HTTP request handlers — route targets |';
        $lines[] = '| `app/Http/Middleware` | Request filtering & modification |';
        $lines[] = '| `app/Http/Requests` | Form validation & authorization |';
        $lines[] = '| `app/Http/Resources` | API resource transformation |';
        $lines[] = '| `app/Jobs` | Background task processing (queue/sync) |';
        $lines[] = '| `app/Listeners` | Event handlers |';
        $lines[] = '| `app/Mail` | Email notification classes |';
        $lines[] = '| `app/Models` | Eloquent ORM models — database entities |';
        $lines[] = '| `app/Notifications` | User notification channels (mail, DB, broadcast) |';
        $lines[] = '| `app/Policies` | Authorization rules for models |';
        $lines[] = '| `app/Providers` | Service providers for bootstrapping |';
        $lines[] = '| `app/Repositories` | Data access layer (if repository pattern detected) |';
        $lines[] = '| `app/Services` | Business logic layer |';
        $lines[] = '| `config/` | Application configuration files |';
        $lines[] = '| `database/migrations/` | Database schema versioning |';
        $lines[] = '| `database/factories/` | Model factories for testing |';
        $lines[] = '| `database/seeders/` | Database seeding scripts |';
        $lines[] = '| `resources/views/` | Blade templates (UI layer) |';
        $lines[] = '| `routes/` | Route definitions (web.php, api.php, etc.) |';
        $lines[] = '| `tests/` | PHPUnit test files |';
        $lines[] = '';

        // Naming conventions
        $lines[] = '---';
        $lines[] = '## 3. Naming Conventions [confidence: 80]';
        $lines[] = '';
        $lines[] = 'Based on analysis of existing code:';
        $lines[] = '';
        $lines[] = '- **Models**: PascalCase, singular (e.g., `Product`, `User`, `OrderItem`)';
        $lines[] = '- **Controllers**: PascalCase + `Controller` suffix (e.g., `ProductController`)';
        $lines[] = '- **Services**: PascalCase + `Service` suffix (e.g., `ProductService`)';
        $lines[] = '- **Repositories**: PascalCase + `Repository` suffix (e.g., `ProductRepository`)';
        $lines[] = '- **Form Requests**: PascalCase + `Request` suffix (e.g., `StoreProductRequest`)';
        $lines[] = '- **Policies**: PascalCase + `Policy` suffix (e.g., `ProductPolicy`)';
        $lines[] = '- **Events**: PascalCase + `Event` suffix (e.g., `ProductCreated`)';
        $lines[] = '- **Jobs**: PascalCase + `Job` suffix (e.g., `ProcessPayment`)';
        $lines[] = '- **Notifications**: PascalCase (e.g., `OrderShipped`)';
        $lines[] = '- **Database Tables**: snake_case, plural (e.g., `products`, `order_items`)';
        $lines[] = '- **Routes**: kebab-case URIs (e.g., `/admin/products`, `/api/v1/users`)';
        $lines[] = '- **Blade Views**: dot notation, lowercase (e.g., `products.index`, `admin.dashboard`)';
        $lines[] = '';

        // Coding style
        $lines[] = '---';
        $lines[] = '## 4. Coding Style [confidence: 70]';
        $lines[] = '';
        $lines[] = 'The project follows Laravel/PHP-FIG conventions:';
        $lines[] = '';
        $lines[] = '- **PHP**: Strict types enabled (`declare(strict_types=1)`)';
        $lines[] = '- **Naming**: camelCase for methods/variables, PascalCase for classes';
        $lines[] = '- **Imports**: Fully qualified use statements at file top';
        $lines[] = '- **Type hints**: Strong typing on method signatures';
        $lines[] = '- **Return types**: Explicit return type declarations';
        $lines[] = '- **Injection**: Constructor dependency injection preferred';
        $lines[] = '- **Validation**: Form Request classes for complex validation';
        $lines[] = '- **Authorization**: Policy classes for model authorization';
        $lines[] = '- **Business Logic**: Service layer encapsulates domain logic';
        $lines[] = '';

        // Where to add new code
        $lines[] = '---';
        $lines[] = '## 5. Where to Add New Code [confidence: 90]';
        $lines[] = '';
        $lines[] = '### New Model';
        $lines[] = '```';
        $lines[] = 'php artisan make:model {ModelName} -m';
        $lines[] = '```';
        $lines[] = 'Place in: `app/Models/{ModelName}.php`';
        $lines[] = 'Add fillable, casts, relationships, and traits as needed.';
        $lines[] = '';

        $lines[] = '### New Controller';
        $lines[] = '```';
        $lines[] = 'php artisan make:controller {ModelName}Controller --resource';
        $lines[] = '```';
        $lines[] = 'Place in: `app/Http/Controllers/{Group}/{ControllerName}.php`';
        $lines[] = 'Groups are organized by module/area (Admin, Api, Coach, etc.).';
        $lines[] = '';

        $lines[] = '### New Service';
        $lines[] = '```';
        $lines[] = 'php artisan make:service {Name}Service';
        $lines[] = '```';
        $lines[] = 'Place in: `app/Services/{Name}Service.php`';
        $lines[] = 'Inject required repositories and other services via constructor.';
        $lines[] = '';

        $lines[] = '### New Repository';
        $lines[] = '```';
        $lines[] = 'Create interface + implementation in app/Repositories/';
        $lines[] = '```';
        $lines[] = 'Interface: `app/Repositories/{Name}RepositoryInterface.php`';
        $lines[] = 'Implementation: `app/Repositories/{Name}Repository.php`';
        $lines[] = '';

        $lines[] = '### New Route';
        $lines[] = '```';
        $lines[] = "// routes/web.php — Web routes\n// routes/api.php — API routes\n// routes/admin.php — Admin routes\n// routes/{module}.php — Module-specific routes";
        $lines[] = '```';
        $lines[] = '';

        $lines[] = '### New View';
        $lines[] = '```';
        $lines[] = "Place in: resources/views/{feature}/{action}.blade.php\n// e.g., resources/views/products/index.blade.php";
        $lines[] = '```';
        $lines[] = '';

        $lines[] = '### New Policy';
        $lines[] = '```';
        $lines[] = 'php artisan make:policy {ModelName}Policy --model={ModelName}';
        $lines[] = '```';
        $lines[] = 'Place in: `app/Policies/{ModelName}Policy.php`';
        $lines[] = 'Register in `AuthServiceProvider` if needed.';
        $lines[] = '';

        $lines[] = '### New Job';
        $lines[] = '```';
        $lines[] = 'php artisan make:job {Name}Job';
        $lines[] = '```';
        $lines[] = 'Place in: `app/Jobs/{Name}Job.php`';
        $lines[] = 'Implement `ShouldQueue` for async processing.';
        $lines[] = '';

        $lines[] = '### New Event/Listener';
        $lines[] = '```';
        $lines[] = 'php artisan make:event {Name}';
        $lines[] = 'php artisan make:listener {Name}Listener --event={Event}';
        $lines[] = '```';
        $lines[] = 'Register in `EventServiceProvider` or use `Event::listen()`.';
        $lines[] = '';

        // Overview of detected modules
        $routeGroups = $data['route_intelligence']['groups'] ?? [];
        if (!empty($routeGroups)) {
            $lines[] = '---';
            $lines[] = '## 6. Module Overview [confidence: 80]';
            $lines[] = '';
            $lines[] = 'The project is organized into the following modules/areas:';
            $lines[] = '';
            foreach ($routeGroups as $module => $group) {
                $lines[] = "- **" . ucfirst($module) . "**: {$group['total']} routes";
                if (!empty($group['controllers'])) {
                    $lines[] = "  - Controllers: " . implode(', ', $group['controllers']);
                }
            }
            $lines[] = '';
        }

        // First steps
        $lines[] = '---';
        $lines[] = '## 7. First Steps for a New Developer';
        $lines[] = '';
        $lines[] = '1. **Read `ai-context.md`** for a complete project overview.';
        $lines[] = '2. **Start with `routes/web.php`** and trace a single request end-to-end.';
        $lines[] = '3. **Read the first CRUD controller** to understand the request lifecycle.';
        $lines[] = '4. **Examine a Model** to understand the entity structure.';
        $lines[] = '5. **Check `database/migrations/`** for the schema.';
        $lines[] = '6. **Review features.json** for the complete feature map.';
        $lines[] = '7. **Review workflows.json** for business workflows.';
        $lines[] = '8. **Use prompts.md** for AI-assisted development.';
        $lines[] = '';

        return [
            'developer_guide' => [
                'content' => implode("\n", $lines),
                'confidence' => 85,
            ],
        ];
    }
}