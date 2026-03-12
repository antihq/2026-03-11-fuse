# AGENTS.md

Coding agent instructions for this Laravel 12 + Livewire 4 project.

## Build/Lint/Test Commands

```bash
# Development (runs server, queue, logs, vite concurrently)
composer dev

# Build assets
npm run build
npm run dev

# Lint (Pint with Laravel preset)
composer lint              # Format all files
composer lint:check        # Check without fixing
./vendor/bin/pint --parallel --dirty  # Changed files only

# Test (Pest)
composer test              # Clear config + lint check + tests
./vendor/bin/pest          # Run all tests
./vendor/bin/pest --filter="test_name"     # Single test by name
./vendor/bin/pest tests/Feature/Auth/AuthenticationTest.php  # Single file
./vendor/bin/pest --parallel              # Parallel execution

# Database
php artisan migrate
php artisan migrate:fresh   # Reset database (dev only)
```

## Stack

- **Backend**: Laravel 12, PHP 8.2+, Laravel Fortify
- **Frontend**: Livewire 4, Flux UI, Tailwind CSS 4, Vite
- **Testing**: Pest 4 with RefreshDatabase

## Project Structure

```
app/
├── Http/Controllers/    # Thin controllers, delegate to services
├── Jobs/                # Queue jobs (pass IDs, not models)
├── Models/              # Eloquent models with casts() method
├── Services/            # Business logic services
├── Rules/               # Custom validation rules
└── Livewire/Actions/    # Livewire action classes

resources/views/
├── pages/               # Livewire page components (⚡*.blade.php)
├── components/          # Blade components
└── layouts/             # Layout templates

routes/
├── web.php              # Web routes (Route::livewire() for pages)
└── console.php          # Scheduled commands
```

## Code Style

- **Formatter**: Laravel Pint (laravel preset) - run `composer lint` before commits
- **Indentation**: 4 spaces
