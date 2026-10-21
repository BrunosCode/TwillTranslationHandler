# Twill Translation Handler

> A [Twill CMS](https://twillcms.com) capsule for managing Laravel translations directly from the admin panel. Built on top of [LaravelTranslationHandler](https://github.com/BrunosCode/LaravelTranslationHandler).

[![Latest Version on Packagist](https://img.shields.io/packagist/v/brunoscode/twill-translation-handler.svg?style=flat-square)](https://packagist.org/packages/brunoscode/twill-translation-handler)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/BrunosCode/TwillTranslationHandler/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/BrunosCode/TwillTranslationHandler/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/BrunosCode/TwillTranslationHandler/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/BrunosCode/TwillTranslationHandler/actions?query=workflow%3A%22Fix+PHP+code+style+issues%22+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/brunoscode/twill-translation-handler.svg?style=flat-square)](https://packagist.org/packages/brunoscode/twill-translation-handler)
[![License](https://img.shields.io/packagist/l/brunoscode/twill-translation-handler.svg?style=flat-square)](LICENSE.md)

> **Twill compatibility:** Currently supports **Twill 3** (Laravel 11–13, PHP 8.2+). Twill 2 support is available in the `v0.x` releases.

## Table of Contents

- [Supported versions](#supported-versions)
- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Navigation](#navigation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Editorial interface](#editorial-interface)
- [Deployment](#deployment)
- [Syncing translations from production](#syncing-translations-from-production)
- [AI integration (Laravel Boost)](#ai-integration-laravel-boost)
- [Database Structure](#database-structure)
- [Testing](#testing)
- [Changelog](#changelog)
- [Credits](#credits)
- [Contributing](#contributing)
- [License](#license)

## Supported versions

| Package version | PHP | Laravel | Twill |
|-----------------|-----|---------|-------|
| **v2.4** | 8.2 · 8.3 · 8.4 | 11 · 12 · 13 | 3.x (3.6+ on Laravel 13) |
| v2.2 | 8.2 · 8.3 · 8.4 | 11 · 12 | 3.x |
| v2.0 | 8.2 · 8.3 | 11 | 3.x |
| v1.x | 8.1+ | 10 | 2.x |

## Features

- **Translation key management** — Browse and edit all translation keys and their per-locale values from a clean Twill module interface.
- **Group-based organization** — Translations are automatically grouped by prefix (e.g. `messages`, `messages.validation`). Groups are created automatically on import and provide a focused editing view via Twill repeaters.
- **Multi-locale support** — Edit translations for all configured locales side by side. Individual locale values can be marked active or inactive.
- **Import / Export** — Sync translations between the database and CSV files:
  - Import from PHP files to populate the database
  - Export all translations to CSV or upload a CSV to import
  - Export a single group as CSV directly from its edit page
- **Automatic group syncing** — When translations are imported, the `DatabaseHandler` automatically creates all intermediate group levels (e.g. importing `messages.validation.required` creates both `messages` and `messages.validation` groups).
- **Auto-sync to PHP files** — Saving a translation key or a group automatically exports the affected PHP language file, keeping the filesystem always in sync with the database.

## Requirements

| Laravel | PHP | Twill |
|---------|-----|-------|
| 11 | 8.2 · 8.3 · 8.4 | 3.x |
| 12 | 8.2 · 8.3 · 8.4 | 3.x |
| 13 | 8.3 · 8.4 | 3.6+ |

## Installation

```bash
composer require brunoscode/twill-translation-handler
```

Run the migrations:

```bash
php artisan migrate
```

The package loads its own migrations: they create `translation_keys` and `translation_values` when missing, or add the Twill columns to the tables of an existing LaravelTranslationHandler install. Do not publish the core migrations afterwards: they create the same tables without checking whether they exist, so `migrate` would fail.

Publish the config file:

```bash
php artisan vendor:publish --tag="twill-translation-handler-config"
```

Both this package and LaravelTranslationHandler publish `config/translation-handler.php`. Publish **this** one: it holds every core option plus the Twill defaults (`dbHandlerClass`, database as source of truth, `legacy-twill-navigation`). Do not also run the core's `translation-handler-config` tag, it would overwrite the file with the core defaults.

Optionally publish the views to customise them:

```bash
php artisan vendor:publish --tag="twill-translation-handler-views"
```

## Navigation

The package provides three sub-pages:

- **Translations** — browse and edit individual translation keys
- **Groups** — edit translations grouped by prefix
- **Import / Export** — sync translations via CSV

### TwillNavigation builder

If your app uses the newer `TwillNavigation` builder API, disable the automatic registration in `config/translation-handler.php`:

```php
'legacy-twill-navigation' => false,
```

Then register the entries in your `AppServiceProvider` (or wherever you build your Twill navigation):

```php
use A17\Twill\Facades\TwillNavigation;
use A17\Twill\View\Components\Navigation\NavigationLink;

TwillNavigation::addLink(
    NavigationLink::make()->title('Translations')
        ->forModule('translations')
        ->doNotAddSelfAsFirstChild()
        ->setChildren([
            NavigationLink::make()->title('Translations')->forModule('translations'),
            NavigationLink::make()->title('Groups')->forModule('translationGroups'),
            NavigationLink::make()->title('Import / Export')->forRoute('twill.translations.translationTools.index'),
        ])
);
```

If you have a custom `admin_route_name_prefix`, replace `twill.` accordingly:

```php
NavigationLink::make()->title('Import / Export')
    ->forRoute(config('twill.admin_route_name_prefix', 'twill.') . 'translations.translationTools.index'),
```

### Legacy twill-navigation config array

By default the package registers the navigation automatically via the legacy `twill-navigation` config array. The package will merge the `translations` key into the array at boot. To control where it appears in the sidebar, add an empty placeholder in the right position — PHP arrays preserve the insertion order of existing keys:

```php
// config/twill-navigation.php

return [
    'dashboard' => [
        'title' => 'Dashboard',
        'route' => 'admin.dashboard',
    ],

    // Placeholder — position is preserved, structure is filled in by the package
    'translations' => [],

    'pages' => [
        'title' => 'Pages',
        'route' => 'admin.pages.index',
    ],
];
```

## Configuration

```php
// config/translation-handler.php

return [
    'legacy-twill-navigation' => true,  // Register the menu via twill-navigation (see Navigation)

    'keyDelimiter' => '.',              // Separator used in nested translation keys

    'fileNames' => ['messages'],        // Lang files managed from the admin (without .php)
    'locales'   => ['en', 'it'],        // Locales exported to the lang files

    // The database is the source of truth
    'defaultImportFrom' => TranslationOptions::PHP,
    'defaultImportTo'   => TranslationOptions::DB,
    'defaultExportFrom' => TranslationOptions::DB,
    'defaultExportTo'   => TranslationOptions::PHP,

    'dbHandlerClass' => DatabaseHandler::class, // Twill handler: also creates the groups

    'phpPath'           => lang_path(),          // Path to PHP language files
    'csvDelimiter'      => ';',                  // Default CSV column delimiter
    'csvEscapeFormulas' => true,                 // Neutralise spreadsheet formulas in CSV exports
    'csvFileName'       => 'translations',       // Download name of the admin CSV export
    'csvPath'           => storage_path('lang'), // Must be writable by the web server
    // ... plus the JSON and `check` options of the core
];
```

See [LaravelTranslationHandler](https://github.com/BrunosCode/LaravelTranslationHandler) for the full list of configuration options.

`locales` decides which locales are exported to the lang files, and the group edit page offers exactly those. The key edit page uses Twill's `translatable.locales` instead: keep the two lists identical, otherwise a value saved for a locale missing from `locales` stays in the database and never reaches a lang file.

## Usage

Once installed, a **Translations** section will appear in the Twill admin navigation with three pages:

### Keys

Lists all translation keys stored in the database. Each key can be opened to edit its translated value for every configured locale. Translation keys are managed exclusively through import — manual creation is intentionally disabled to keep the database in sync with source files.

### Groups

Lists all translation groups, auto-generated from the key prefixes. Opening a group shows all matching keys in a repeater, allowing batch editing of every locale's value from a single form.

### Import / Export

A tools page with actions to sync translations:

| Action | Description |
|--------|-------------|
| Import from CSV | Uploads a CSV file, imports all translations into the database, and writes the PHP language files |
| Export to CSV | Downloads all database translations as a CSV file |

Per-group CSV export is also available directly from each group's edit page.

#### CSV import rules

- The file does not need to contain all keys — only the keys present in the file will be updated.
- All locale columns must be present in the file header.
- Empty values will overwrite the existing translation with an empty string.

> PHP language files are kept in sync automatically: every import (CSV via the tools page or per-group) writes the affected PHP files immediately after updating the database. Saving a single key or group from the edit form also triggers the same export.

## Editorial interface

### Editing a key

Opening a translation key shows:

| Field | Behaviour |
|-------|-----------|
| **Key** | Read-only. Keys are created only via import. |
| **Value** | Translatable textarea — one tab per configured locale. |
| **Allow empty** | Checkbox (sidebar). See [below](#allow-empty). |

Saving a key immediately exports the affected PHP language file.

### Editing a group

Opening a group shows the **prefix** (read-only) and one textarea per translation key that belongs to the group. Each textarea is translatable — every configured locale is available as a tab. A **Download CSV** button lets editors export the group directly from the edit page.

Field naming: each textarea is internally keyed as `trans_<id>` where `<id>` is the `translation_keys.id`. This is transparent to editors but relevant if you extend the form.

Saving a group writes all locale values and immediately exports the affected PHP language file.

### allow_empty

Both modules show an **Allow empty** checkbox in the sidebar. Its effect is on form validation:

| Module | `allow_empty` unchecked | `allow_empty` checked |
|--------|------------------------|----------------------|
| **Keys** | Value is `required` for every locale | Value is `nullable` — empty strings are accepted |
| **Groups** | Every locale value of every key is `required` | No validation — any combination of values is accepted |

When a value reaches the repository as `null` (e.g. the editor cleared a field), it is normalised to an empty string before being persisted. This means the database never stores `null` in `translation_values.value` through a form save.

## Deployment

The **database is the source of truth** for translation values. PHP language files are used only to carry new keys and new locales between environments.

A typical deploy script:

```bash
php artisan migrate

# Add new keys and locales from PHP files to the DB, without overwriting existing DB values
php artisan translation-handler:sync php db

# Overwrite PHP files with the authoritative DB values
php artisan translation-handler:sync db php --force
```

The first command (without `--force`) inserts only keys and locale entries that do not yet exist in the DB — existing values are left untouched. The second command rewrites the PHP files so they always reflect the DB state.

> **First deploy / fresh environment:** if the DB is empty, seed it from the PHP files:
> ```bash
> php artisan translation-handler:sync php db --force
> ```

## Syncing translations from production

When content editors have updated translations directly in production or staging, you can pull those changes back into the repository:

1. **Download the CSV** from the production admin panel via _Translations → Import / Export → Export to CSV_.

2. **Copy the file** into your project (e.g. `storage/lang/translations.csv`) and import it to regenerate the PHP files:

```bash
php artisan translation-handler:sync csv php --force
```

3. **Commit the updated PHP files** and push:

```bash
git add lang/
git commit -m "chore: sync translations from production"
git push
```

The next deploy will import the updated PHP files into any environment whose DB does not yet have those values.

## AI integration (Laravel Boost)

The underlying [LaravelTranslationHandler](https://github.com/BrunosCode/LaravelTranslationHandler) exposes its translation operations as [Model Context Protocol](https://modelcontextprotocol.io) tools through [Laravel Boost](https://github.com/laravel/boost). Boost is an **optional** dependency — install it only if you want an AI agent (e.g. in your editor) to read and edit translations directly.

```bash
composer require laravel/boost:^2.0
```

Once Boost is installed the core package auto-registers the translation MCP tools — no configuration. Any MCP-compatible agent (Claude, Cursor, GitHub Copilot, …) can then browse and edit translations directly. They cover the same operations as the admin panel and CLI:

- **Read** — `get-translation-config-tool`, `list-translation-groups-tool`, `list-translations-tool`, `find-translation-tool`
- **Write** — `set-translation-tool`, `set-all-locales-translation-tool`, `set-translation-group-tool`, `sync-translations-tool`, `delete-translation-tool`, `delete-translation-group-tool`
- **Maintenance** — `sort-translations-tool`, `check-translations-tool` (reports keys used in code but missing per locale)

The tools are registered by the core package and operate on the same database and language files described above. See the [LaravelTranslationHandler](https://github.com/BrunosCode/LaravelTranslationHandler) README for the full tool reference.

### Guidelines and skills

Running `php artisan boost:install` and selecting **twill-translation-handler (guidelines, skills)** installs an AI guideline plus two skills — `translation-handler-mcp` (the agent workflow) and `translation-handler-development` (writing custom PHP) — into your editor's agent. This package ships them because Boost only discovers the guidelines and skills of *direct* Composer dependencies: in a Twill app the core `laravel-translation-handler` package is a transitive dependency, so its own Boost resources would never be offered. The content is read live from the installed core package at install time, so it always reflects the installed `laravel-translation-handler` version, plus a Twill-specific note on editing translations from the admin.

## Database Structure

| Table | Description |
|-------|-------------|
| `translation_keys` | Translation keys (`key`, `published`) |
| `translation_values` | Per-locale values (`locale`, `value`, `active`) |
| `translation_groups` | Group prefixes (`prefix`, `published`) |

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG.md](CHANGELOG.md) for what has changed recently.

## Credits

- [BrunosCode](https://github.com/BrunosCode)
- [All Contributors](https://github.com/BrunosCode/TwillTranslationHandler/graphs/contributors)

## Contributing

Contributions are welcome! Please submit a pull request or open an issue to discuss what you would like to change.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
