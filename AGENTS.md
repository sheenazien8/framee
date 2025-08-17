# Agent Guidelines for PhotoBox Laravel Project

## Build & Test Commands
- **Dev Server**: `composer dev` (runs Laravel server, queue, logs, and Vite concurrently)
- **Frontend Build**: `npm run build` / `npm run dev`
- **Tests**: `composer test` or `php artisan test` (uses Pest framework)
- **Single Test**: `php artisan test --filter="test_name"`
- **Code Style**: `vendor/bin/pint` (Laravel Pint for PHP formatting)

## Code Style Guidelines
- **PHP**: PSR-4 autoloading, use strict types, follow Laravel conventions
- **Namespaces**: `App\` for app/, `Database\Factories\` for factories, `Tests\` for tests
- **Models**: Use Eloquent, type-hint properties, use `protected $fillable` arrays
- **Controllers**: Extend base `Controller` class, use dependency injection
- **Tests**: Use Pest syntax (`test('description', function() {})`), prefer Feature tests
- **Frontend**: TailwindCSS v4, Vite build system, ES modules
- **Imports**: Group use statements, follow PSR-12 standards
- **Error Handling**: Use Laravel exceptions, type-hint return types where possible

## Project Structure
- Laravel 12 with PHP 8.2+, Pest testing, TailwindCSS, Vite
- No Cursor rules or Copilot instructions found