# Laravel Beacon v1.0.0

**Project Intelligence Engine for Laravel** — Scan, understand, and export clean context for any Laravel project.

Transform static project analysis into actionable AI context. Beacon helps AI assistants (ChatGPT, Claude, Gemini, Cursor, Cline, Copilot) understand a Laravel project almost as if they had been developed together with the original team from day one.

---

## Quick Start

```bash
composer require coffesoft/laravel-beacon

# Full project scan (recommended first step)
php artisan beacon:scan

# Generate task-specific AI context
php artisan beacon:task "Create attendance system"

# Code quality review
php artisan beacon:review --min-severity=warning

# AI-powered refactoring plan
php artisan beacon:fix-plan
```

The service provider auto-discovers via Laravel package discovery.

---

## Compatibility

| Laravel | PHP | Support Level |
|---------|-----|--------------|
| 13.x | 8.2–8.4 | ✅ Official |
| 12.x | 8.2–8.4 | ✅ Official |
| 11.x | 8.1–8.4 | ✅ Official |
| 10.x | 8.1–8.3 | ✅ Official |
| 9.x | 8.1–8.2 | ✅ Official |

---

## Commands

### Core Commands

```bash
# Full project scan — runs all scanners + intelligence engines
php artisan beacon:scan

# Re-export from cached scan data
php artisan beacon:export --format=md
php artisan beacon:export --format=json
```

### AI Working Context

```bash
# Generate task-specific context for AI assistants
php artisan beacon:task "Create attendance system"

# Show project changes since last scan
php artisan beacon:diff

# Code quality review with severity ranking
php artisan beacon:review --min-severity=warning
```

### AI Copilot

```bash
# Full AI refactoring plan with priorities and execution order
php artisan beacon:fix-plan

# Code fix suggestions from review output
php artisan beacon:suggest-fix

# Route quality analysis (duplicates, orphans, REST violations)
php artisan beacon:route-health
```

---

## Output Files

All outputs are stored in `storage/app/beacon/`.

### Analysis Outputs

| File | Format | Content |
|------|--------|---------|
| `context.json` | JSON | Complete structured project data |
| `context.md` | Markdown | AI-readable project overview |
| `project-graph.json` | JSON | Relationship graph between project components |
| `architecture.json` | JSON | Detected architecture patterns and analysis |

### AI Working Context

| File | Format | Content |
|------|--------|---------|
| `ai-context.md` | Markdown | LLM-optimized project summary |
| `ai-summary.md` | Markdown | Class-by-class summaries |
| `developer-guide.md` | Markdown | Onboarding guide for new developers |
| `prompts.md` | Markdown | 10 reusable AI prompts |
| `task-context.md` | Markdown | Task-specific development context |
| `diff.md` | Markdown | Project changes since last scan |
| `review.md` | Markdown | Code quality findings with severity |

### AI Copilot

| File | Format | Content |
|------|--------|---------|
| `fix-suggestions.json` | JSON | Actionable code fix suggestions |
| `fix-suggestions.md` | Markdown | Human-readable fix descriptions |
| `route-health.json` | JSON | Route quality analysis |
| `route-health.md` | Markdown | Route health report |
| `refactor-plan.json` | JSON | Prioritized refactoring actions |
| `refactor-plan.md` | Markdown | 3-phase execution plan |

---

## Architecture

```
Scanners → Raw Data → Intelligence Engines → Output Files
```

### Scanners (25)
Models, Controllers, Routes, Migrations, Database, Config, Services, Repositories, FormRequests, Middleware, Policies, Events, Jobs, Notifications, Mail, Traits, Enums, Helpers, Livewire, Blade, API, Queue, Storage, Packages, Commands

### Intelligence Engines
Architecture Detection, Security Analysis, Performance Analysis, Business Rules, Relationship Graph, AI Summaries, Database Intelligence, Route Intelligence, Folder Tree, Module Detection, Workflow Detection, Dependency Graph, Feature Map, Impact Map, AI Context Compression, Entry Points, Developer Onboarding, AI Prompt Pack, Task Context, Diff Engine, Review Engine, Enhanced Statistics, Route Health, Code Fix Engine, AI Refactor Planner, Auto Controller Splitter

---

## Safety

- **Read-only** — Never executes or modifies application code
- **AST-first** — Uses PHP tokenization for safe analysis
- **Zero application boot** — No database queries, no service registration
- **Confidence scores** — Every finding includes 0–100 reliability rating
- **Evidence field** — Every suggestion includes the data that triggered it

---

## What It Scans

| Category | Source | Data Collected |
|----------|--------|----------------|
| Models | `app/Models/` | Attributes, casts, relations, scopes, accessors, mutators, traits |
| Controllers | `app/Http/Controllers/` | Methods, CRUD detection, middleware, validation |
| Routes | Registered routes | URI, methods, names, actions, middleware, groups |
| Services | `app/Services/`, Actions, UseCases | Dependencies, methods, referenced models/events/jobs |
| Events | `app/Events/`, `app/Listeners/` | Events, listeners, subscribers, dispatchers |
| Jobs | `app/Jobs/` | Queued/sync, dispatch locations, unique locking |
| Notifications | `app/Notifications/` | Channels (mail, database, broadcast, SMS) |
| Database | `database/migrations/` | Tables, columns, indexes, foreign keys |
| API | `app/Http/Resources/` | Resources, controllers, Sanctum/Passport/JWT |
| Packages | `composer.json` | Categorized with purpose detection |
| Blade | `resources/views/` | Layouts, components, sections, inheritance |
| Livewire | `app/Livewire/` | Components, properties, events, computed values |
| Mail | `app/Mail/` | Subjects, markdown templates, views, attachments |
| Enums | `app/Enums/` | Cases, backed values, usage |
| Traits | `app/Traits/`, `app/Concerns/` | Definitions, usage map |
| Helpers | `app/Helpers/` | Global functions, autoload files |
| Middleware | `app/Http/Middleware/` | Registered middleware, aliases, groups |
| Policies | `app/Policies/` | Model mapping, abilities |
| Form Requests | `app/Http/Requests/` | Validation rules, authorize(), custom messages |
| Repositories | `app/Repositories/` | Interfaces, implementations, model references |
| Storage | `config/filesystems.php` | Disks, upload paths, public symlinks |
| Queue | `config/queue.php` | Drivers, connections, Horizon detection |

---

## Requirements

- PHP 8.1+
- Laravel 9.x, 10.x, 11.x, 12.x, or 13.x
- ext-json (included with PHP by default)
- ext-mbstring (included with PHP by default)

---

## License

MIT