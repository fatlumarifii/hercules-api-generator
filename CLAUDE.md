# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laravel package (`itslh/hercules-api-generator`) that automatically generates Postman collections from Laravel routes and FormRequest validation rules. Supports cloud sync via Postman API and local file export, with git hooks for automatic updates.

- **PHP:** 8.0–8.4 | **Laravel:** 9.x–12.x
- **Namespace:** `Hercules\ApiGenerator\` → `src/`
- **No database, routes, or middleware** — operates entirely via artisan commands

## Commands

```bash
# Run tests
composer test

# Run a single test file
vendor/bin/phpunit tests/Unit/RouteParserTest.php

# Run a single test method
vendor/bin/phpunit --filter=test_method_name

# Run by suite
vendor/bin/phpunit --testsuite=Unit
vendor/bin/phpunit --testsuite=Feature

# Code formatting (Laravel Pint)
composer format          # auto-fix
composer format-check    # check only (CI)

# Test coverage
composer test-coverage
```

## Architecture

### Service Provider (`HerculesApiGeneratorServiceProvider`)
Entry point. Merges config, publishes config file (tag: `hercules-api-generator-config`), registers `SetupCommand` for console.

### Command: `api:setup` (`src/Commands/SetupCommand.php`)
Interactive wizard that orchestrates all services. Handles Postman/file-export setup, API key validation, git hook installation, .env updates. Supports `--non-interactive` flag for CI/CD.

### Services (the core logic)

- **RouteParser** — Parses Laravel routes via Route facade. Filters by prefix, middleware, exclude patterns. Groups by controller, prefix, or none. Uses reflection to detect FormRequest type-hints on controller methods.

- **ValidationParser** — Instantiates FormRequest classes (with mocked dependencies for complex constructors), extracts validation rules, and generates field metadata with example values. Handles string rules, Rule objects, nested dot notation fields.

- **PostmanCollectionBuilder** — Builds Postman Collection v2.0 JSON. Creates folder structure based on route grouping, generates request items with headers/body/params. Manages version history with configurable limit.

- **PostmanApiService** — Postman API integration (CRUD collections, workspaces). Smart merge preserves manual edits (descriptions, auth, headers, pre-request scripts) when updating collections.

### Data Flow
`Routes → RouteParser → ValidationParser (for request bodies) → PostmanCollectionBuilder → PostmanApiService (cloud) or file export`

## Configuration

Main config: `config/hercules-api-generator.php` (publishable). Key sections: client type (postman/file-export), Postman API credentials, route filtering, git hooks, merge strategy, request body generation.

Sensitive values come from `.env` (e.g., `POSTMAN_API_KEY`, `POSTMAN_COLLECTION_ID`).

## Testing

Uses Orchestra Testbench for Laravel package testing. Base `TestCase` sets up default config and defines test routes (`api/test`, `api/test/{id}`).

## Code Style

Laravel Pint with strict preset. All files use `declare(strict_types=1)`. Enforced in CI via `composer format-check`.
