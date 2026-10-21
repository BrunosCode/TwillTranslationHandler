# Development Guide

## Requirements

- PHP 8.2+
- Laravel 11, 12 or 13
- Twill 3.x
- Composer
- Node.js (for Twill asset build)

## Setup

```bash
# Install dependencies
composer install

# Build the workbench environment (creates SQLite DB, runs migrations, seeds)
composer build
```

## Running the dev server

```bash
# Build + serve (recommended)
composer serve

# Or build and serve separately
composer build
php vendor/bin/testbench serve
```

The admin panel is available at `http://localhost:8000/admin`.

Default credentials: `test@test.test` / `test@test.test`

## Resetting the database

```bash
# Delete the SQLite database and rebuild
rm -f vendor/orchestra/testbench-core/laravel/database/database.sqlite
composer build
```

## Common commands

### Tests

```bash
# Run all tests
composer test

# Run with coverage
composer test-coverage

# Run a specific test file
vendor/bin/pest tests/TranslationModelTest.php

# Run a specific test
vendor/bin/pest --filter="can create a translation key"
```

> **Note:** Tests use an in-memory SQLite database with inline table definitions in `tests/TestCase.php`. They do not depend on published migrations or Twill's own migration files.

### Code style (Pint)

```bash
# Fix code style
composer format

# Or directly
vendor/bin/pint
```

### Static analysis (PHPStan)

```bash
# Run analysis
composer analyse

# Or with verbose output
vendor/bin/phpstan analyse --verbose --memory-limit 300M
```

### Lint (Pint + PHPStan together)

```bash
composer lint
```

### Artisan / Tinker via Testbench

```bash
# Run any artisan command
php vendor/bin/testbench <command>

# Examples
php vendor/bin/testbench migrate
php vendor/bin/testbench tinker
php vendor/bin/testbench route:list
php vendor/bin/testbench db:seed --class="Workbench\\Database\\Seeders\\DatabaseSeeder"
```

### Translation Handler commands

```bash
# Import translations from PHP files into the DB
php vendor/bin/testbench translation-handler:import --from=php --to=db

# Import with force (overwrites existing DB values)
php vendor/bin/testbench translation-handler:import --from=php --to=db --force

# Export DB translations to PHP files
php vendor/bin/testbench translation-handler:export --from=db --to=php

# Import from CSV into DB and PHP files
php vendor/bin/testbench translation-handler:import --from=csv --to=db
php vendor/bin/testbench translation-handler:export --from=db --to=php

# Get a single translation
php vendor/bin/testbench translation-handler:get --from=db test.key en

# Set a single translation
php vendor/bin/testbench translation-handler:set --to=db test.key en "value"
```

## Project structure

```
src/
├── DatabaseHandler.php                     # Custom DB handler (creates groups on import)
├── TranslationsCapsuleServiceProvider.php  # Registers Twill capsule + navigation
├── TwillTranslationHandlerServiceProvider.php  # Package config, views, admin routes
├── Http/Controllers/
│   └── TranslationToolsController.php      # Import/Export page controller
└── Twill/Capsules/Translations/            # Twill capsule (all modules)
    ├── Models/
    │   ├── Translation.php                 # translation_keys table
    │   ├── TranslationTranslation.php      # translation_values table
    │   └── TranslationGroup.php            # translation_groups table
    ├── Http/Controllers/
    │   ├── TranslationController.php       # Keys CRUD
    │   └── TranslationGroupController.php  # Groups listing + edit
    ├── Http/Requests/
    │   ├── TranslationRequest.php
    │   └── TranslationGroupRequest.php
    ├── Repositories/
    │   ├── TranslationRepository.php
    │   └── TranslationGroupRepository.php  # Inline textarea fields <-> translation_values
    ├── database/migrations/                # ALTER TABLE + CREATE translation_groups
    ├── resources/views/admin/
    │   ├── translations/
    │   │   └── form.blade.php              # Translation edit form (translated input per locale)
    │   └── translationGroups/
    │       └── form.blade.php              # Group edit form (inline textareas, no repeaters)
    └── routes/admin.php                    # translations + translationGroups modules

config/
└── translation-handler.php     # Package config (locales, file names, handlers, paths)

resources/views/tools/
└── index.blade.php             # Import/Export admin page

workbench/                      # Dev/test environment (orchestra/testbench)
├── config/
│   ├── twill.php
│   └── twill-navigation.php
├── database/seeders/           # Admin user seeder
└── routes/
```

## Database

This package uses three tables:

| Table | Created by | Purpose |
|-------|-----------|---------|
| `translation_keys` | laravel-translation-handler | Translation keys |
| `translation_values` | laravel-translation-handler | Per-locale values (FK to keys) |
| `translation_groups` | This package | Groups by key prefix |

Our capsule migrations add two columns required by Twill:

- `translation_keys.published` (default: `true`)
- `translation_values.active` (default: `true`)

These migrations include `hasTable`/`hasColumn` guards so they are safe to run in any order.

## Route name prefix

Twill 3 uses `twill.` as the default route name prefix (configurable via `twill.admin_route_name_prefix`). This package reads that config value everywhere it references routes, so it works with both `admin.` (Twill 2 style) and `twill.` (Twill 3 default).

In the workbench dev environment, `config/twill.php` sets the prefix. Tests override it to `admin.`.

## Adding a new module inside the Translations capsule

1. Create Model, Controller, Request, Repository under the existing `src/Twill/Capsules/Translations/` directories.

2. Add the route in `src/Twill/Capsules/Translations/routes/admin.php`:

```php
Route::module('newModuleName');
```

3. Add navigation in `TranslationsCapsuleServiceProvider::registerNavigation()` if needed.

4. Reset the database (see [Resetting the database](#resetting-the-database)).

## Updating dependencies

```bash
# Update all
composer update

# Update a specific package
composer update brunoscode/laravel-translation-handler
```
