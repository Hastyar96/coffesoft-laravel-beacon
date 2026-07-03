<?php

declare(strict_types=1);

namespace Coffesoft\LaravelBeacon\Intelligence;

/**
 * Generates reusable AI prompts for working with the project.
 * Compatible with ChatGPT, Claude, Gemini, Cline, Cursor, and Copilot.
 */
class AiPromptPack
{
    /**
     * @param array<string, mixed> $data All scanned project data
     * @return array<string, mixed>
     */
    public function generate(array $data): array
    {
        $projectName = basename(base_path());

        $lines = [];

        $lines[] = '# AI Prompt Pack — ' . $projectName;
        $lines[] = '';
        $lines[] = '> Reusable prompts for AI-assisted development.';
        $lines[] = '> Compatible with ChatGPT, Claude, Gemini, Cline, Cursor, and Copilot.';
        $lines[] = '>';
        $lines[] = '> _Replace `{target}` with specific classes, `{feature}` with feature names._';
        $lines[] = '';

        // Prompt 1: Explain
        $lines[] = '---';
        $lines[] = '## 🧠 1. Explain This Project';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'Read the file storage/app/beacon/ai-context.md and then explain:';
        $lines[] = '';
        $lines[] = '1. What is the main purpose of this project?';
        $lines[] = '2. What architecture patterns are used?';
        $lines[] = '3. What are the core business entities and their relationships?';
        $lines[] = '4. How does authentication work?';
        $lines[] = '5. What are the main workflows?';
        $lines[] = '6. What external services are integrated?';
        $lines[] = '7. What queue system is used?';
        $lines[] = '8. Where should a new developer start reading the code?';
        $lines[] = '```';
        $lines[] = '';

        // Prompt 2: Bugs
        $lines[] = '---';
        $lines[] = '## 🐛 2. Find Bugs';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'Read the files in storage/app/beacon/ and analyze this project for:';
        $lines[] = '';
        $lines[] = '1. N+1 query problems (especially in controllers and services)';
        $lines[] = '2. Missing validation in store/update operations';
        $lines[] = '3. Mass assignment vulnerabilities (unguarded models)';
        $lines[] = '4. Potential race conditions in job handling';
        $lines[] = '5. Missing authorization checks';
        $lines[] = '6. Hardcoded values that should be configurable';
        $lines[] = '7. Unused imports and dead code';
        $lines[] = '8. Inconsistent error handling patterns';
        $lines[] = '';
        $lines[] = 'For each bug found, explain the impact and provide a fix.';
        $lines[] = '```';
        $lines[] = '';

        // Prompt 3: Refactor
        $lines[] = '---';
        $lines[] = '## 🔧 3. Refactor Safely';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'I want to refactor {target}. Read storage/app/beacon/impact-map.json';
        $lines[] = 'and tell me:';
        $lines[] = '';
        $lines[] = '1. What files will be affected by changes to {target}?';
        $lines[] = '2. What is the dependency chain?';
        $lines[] = '3. What tests exist for {target}?';
        $lines[] = '4. Suggest a safe refactoring plan step by step.';
        $lines[] = '5. What are the risks of this refactoring?';
        $lines[] = '```';
        $lines[] = '';

        // Prompt 4: Add feature
        $lines[] = '---';
        $lines[] = '## ✨ 4. Add a Feature';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'Read storage/app/beacon/developer-guide.md and storage/app/beacon/context.json.';
        $lines[] = '';
        $lines[] = 'I want to add a new feature: {feature}.';
        $lines[] = '';
        $lines[] = 'Based on existing patterns in the project:';
        $lines[] = '1. What model do I need to create? What attributes and relationships?';
        $lines[] = '2. What migration is needed?';
        $lines[] = '3. What controller and which methods should it have?';
        $lines[] = '4. What service class do I need?';
        $lines[] = '5. What form request for validation?';
        $lines[] = '6. What policy for authorization?';
        $lines[] = '7. What routes should be added?';
        $lines[] = '8. What views are needed?';
        $lines[] = '9. Do I need any events, jobs, or notifications?';
        $lines[] = '10. Follow the exact naming conventions used in this project.';
        $lines[] = '```';
        $lines[] = '';

        // Prompt 5: Optimize
        $lines[] = '---';
        $lines[] = '## ⚡ 5. Optimize Queries';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'Review storage/app/beacon/performance issues in context.json.';
        $lines[] = 'Then analyze the controllers and models for:';
        $lines[] = '';
        $lines[] = '1. N+1 query patterns — add eager loading where missing';
        $lines[] = '2. Heavy queries in loops — suggest chunking or cursor pagination';
        $lines[] = '3. Missing database indexes on frequently queried columns';
        $lines[] = '4. Controllers with too many responsibilities — suggest splitting';
        $lines[] = '5. Cache opportunities for expensive operations';
        $lines[] = '6. Model::all() calls — suggest pagination';
        $lines[] = '7. Suggest specific indexes to add';
        $lines[] = '```';
        $lines[] = '';

        // Prompt 6: Dead code
        $lines[] = '---';
        $lines[] = '## 🗑️ 6. Find Dead Code';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'Analyze this project and find:';
        $lines[] = '';
        $lines[] = '1. Routes that have no names and may not be in use';
        $lines[] = '2. Controllers or methods that may be unused';
        $lines[] = '3. Services or repositories that are never injected';
        $lines[] = '4. Unused imports in controllers and services';
        $lines[] = '5. Events that are never dispatched';
        $lines[] = '6. Jobs that are never dispatched';
        $lines[] = '7. Unused Blade views';
        $lines[] = '8. Unused policies or abilities';
        $lines[] = '';
        $lines[] = 'Use storage/app/beacon/dependency-graph.json for reference.';
        $lines[] = '```';
        $lines[] = '';

        // Prompt 7: Tests
        $lines[] = '---';
        $lines[] = '## 🧪 7. Generate Tests';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'Based on the project analysis in storage/app/beacon/context.json:';
        $lines[] = '';
        $lines[] = 'Generate PHPUnit tests for {target}:';
        $lines[] = '1. Unit tests for all public methods';
        $lines[] = '2. Feature tests for the main workflows';
        $lines[] = '3. Test database setup using model factories';
        $lines[] = '4. Authorization tests';
        $lines[] = '5. Validation tests for form requests';
        $lines[] = '6. Queue job tests';
        $lines[] = '7. Follow the existing test patterns in tests/';
        $lines[] = '```';
        $lines[] = '';

        // Prompt 8: Security
        $lines[] = '---';
        $lines[] = '## 🔒 8. Review Security';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'Review the security findings in storage/app/beacon/context.json';
        $lines[] = 'and perform a deeper analysis:';
        $lines[] = '';
        $lines[] = '1. Check all controllers for missing authorization';
        $lines[] = '2. Verify form requests have proper authorize() methods';
        $lines[] = '3. Check for mass assignment in all models';
        $lines[] = '4. Review API authentication (Sanctum/Passport/JWT)';
        $lines[] = '5. Check for SQL injection via raw queries';
        $lines[] = '6. Review file upload handling for security';
        $lines[] = '7. Check .env for exposed credentials';
        $lines[] = '8. Verify CSRF protection on all non-API routes';
        $lines[] = '```';
        $lines[] = '';

        // Prompt 9: Architecture
        $lines[] = '---';
        $lines[] = '## 🏗️ 9. Review Architecture';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'Read the architecture detection in storage/app/beacon/architecture.json';
        $lines[] = 'and storage/app/beacon/dependency-graph.json. Then:';
        $lines[] = '';
        $lines[] = '1. Is the detected architecture accurate?';
        $lines[] = '2. Are there inconsistencies in the pattern usage?';
        $lines[] = '3. Is the service layer consistent or are there gaps?';
        $lines[] = '4. Are repositories used consistently?';
        $lines[] = '5. Are there controllers that should be split?';
        $lines[] = '6. Are there circular dependencies?';
        $lines[] = '7. Suggest architectural improvements.';
        $lines[] = '```';
        $lines[] = '';

        // Prompt 10: Workflow
        $lines[] = '---';
        $lines[] = '## 🔄 10. Understand Workflow';
        $lines[] = '';
        $lines[] = '```';
        $lines[] = 'Read storage/app/beacon/workflows.json and explain the';
        $lines[] = '"{workflow}" workflow in detail:';
        $lines[] = '';
        $lines[] = '1. What triggers this workflow?';
        $lines[] = '2. What is the complete request lifecycle?';
        $lines[] = '3. What validation happens?';
        $lines[] = '4. What business logic is executed?';
        $lines[] = '5. What data is persisted?';
        $lines[] = '6. What events are fired?';
        $lines[] = '7. What notifications are sent?';
        $lines[] = '8. What are the side effects?';
        $lines[] = '9. What can go wrong and how is it handled?';
        $lines[] = '```';
        $lines[] = '';

        return [
            'ai_prompts' => [
                'content' => implode("\n", $lines),
                'prompt_count' => 10,
                'compatible_with' => ['ChatGPT', 'Claude', 'Gemini', 'Cline', 'Cursor', 'Copilot'],
            ],
        ];
    }
}