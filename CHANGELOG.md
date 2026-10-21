# Changelog

All notable changes to `TwillTranslationHandler` will be documented in this file.

## v2.4.0 — Core 2.8 alignment & Laravel 13 - 2026-10-21

**Full changelog:** [v2.3.0...v2.4.0](https://github.com/BrunosCode/TwillTranslationHandler/compare/v2.3.0...v2.4.0)

### New

- **Laravel 13 support.** `laravel/framework ^11.0|^12.0|^13.0`; Laravel 13 needs Twill 3.6+ and PHP 8.3+. The CI matrix adds Laravel 13 on PHP 8.3 / 8.4 (Testbench 11, Pest 4).
- **`laravel-translation-handler` updated to `^2.8`.** Exports refuse a key that is both a value and a parent (`auth.a` + `auth.a.b`) instead of writing a broken file, and CSV exports neutralise spreadsheet formulas (`csvEscapeFormulas`, on by default): a value starting with `=`, `+`, `-` or `@` is written with a leading `'`, which the import strips again.

### Bug fixes

- **Nested groups are exported to the right lang file.** Saving a nested group (e.g. `auth.messages`) from the admin, or importing a CSV into it, wrote `lang/{locale}/auth.messages.php` and never updated `auth.php`, the file Laravel reads. The export now targets the group's first prefix segment. Delete any stray `lang/*/*.*.php` files the old behaviour left behind.
- **Failed exports after an admin save are reported.** Saving a key swallowed export errors without logging them; saving a group returned a 500 after the values were already stored. Both now keep the values, report the exception through the exception handler and reset the handler options.
- **The group editor offers only the exported locales.** It used Twill's `translatable.locales`, so a value saved for a locale missing from `translation-handler.locales` stayed in the database and never reached a lang file. The key editor still uses `translatable.locales`: keep the two lists identical.
- **CSV delimiter validated.** An invalid `csv_delimiter` returned a 500 (`ValueError` from `fputcsv()`); it is now a validation error. All CSV actions reset the handler options in a `finally` block.
- **`%` and `_` are matched literally** in group prefixes and in the admin search. A group `a_b` no longer lists the keys of `axb`.
- **Complete published config.** `config/translation-handler.php` now contains every core option (`phpPint`, `csvEscapeFormulas`, `jsonFileName`, `jsonNested`, `jsonFormat`, `check`, `checkIncludeFrameworkKeys`, `checkerClass`) and `fileNames` defaults to `['messages']` instead of the `['test']` placeholder. Publish this config, not the core's: both use the same file name.

### Performance

- Groups are created with a single `insertOrIgnore` per import instead of a `firstOrCreate` per prefix, splitting keys with the runtime `keyDelimiter` option.
- The groups listing loads `translations_count` with a subquery instead of a `COUNT` per row.

### Housekeeping

- Tests for the real migrations (empty database, after the core stubs, re-run, rollback).
- Removed a stray SQLite schema dump (`translation_keys`) from the repository root; real package description in `composer.json`.
- GitHub Actions: `actions/checkout` v7, `dependabot/fetch-metadata` v3.1.0.

## v2.3.0 — Ship Boost guidelines & skills - 2026-06-16

- **Ship Laravel Boost guidelines and skills.** `php artisan boost:install` now discovers `twill-translation-handler` and offers to install an AI guideline plus the `translation-handler-mcp` and `translation-handler-development` skills. Boost only discovers the guidelines/skills of *direct* Composer dependencies, so the core `laravel-translation-handler` package — a transitive dependency in a Twill app — was previously skipped entirely; this package now re-exports its Boost resources. The content is read live from the installed core package at install time (a thin Blade wrapper around the core `core.blade.php` and `SKILL.md` files), so it stays in sync with the installed `laravel-translation-handler` version without duplication. A Twill-specific section covers editing translations from the admin.

## v2.2.0 — Laravel Boost support - 2026-06-16

- Bump `brunoscode/laravel-translation-handler` to `^2.6` (v2.6.1).
- **Laravel Boost support.** With the optional `laravel/boost ^2.0` package installed, the core package's translation MCP tools (list, find, set, delete, sort, sync, check) become available to AI agents — documented in the new README section.
- README house-style pass aligned with [LaravelTranslationHandler](https://github.com/BrunosCode/LaravelTranslationHandler): added a table of contents, a blockquote tagline, the code-style and license badges, and a Contributing section; replaced the deprecated bare `translation-handler` command with `translation-handler:sync` in the deploy and production-sync examples.

## v2.1.2 - 2026-06-04

- Bump `brunoscode/laravel-translation-handler` to `^2.4` (v2.4.0), bringing the new `translation-handler:check` command, linter-friendly file writes, and the `false|int` return semantics for `sync()` / `import()` / `export()`.
- Adapt the empty-export test to the new `export()` semantics: since v2.3 `export()` returns `false` and writes nothing when the source has no translations, so `TranslationToolsController::exportToCsv` now redirects back with an error instead of returning an empty headers-only CSV download.

## v2.1.1 - 2026-05-11

- Update author email
- Bump `brunoscode/laravel-translation-handler` to `^2.2`

## v2.1.0 — Laravel 12 + PHP 8.4 - 2026-05-09

### New

- **Laravel 12 support.** Framework constraint widened to `^11.0|^12.0`. Twill 3.5+ pulls the right `cartalyst/tags` and dependency tree under L12 thanks to `composer update -W` in CI.
- **PHP 8.4 support.** Tested and confirmed. CI matrix now covers PHP 8.2 / 8.3 / 8.4 × Laravel 11 / 12 on Ubuntu and Windows.
- **`laravel-translation-handler` updated to `^2.1`** (v2.1.2).

### Bug fixes

- **Null values in form save no longer cause errors.** Twill can pass `null` for inactive-locale fields when saving a translation or translation group. Both `TranslationRepository::update` and `TranslationGroupRepository::update` now normalise `null` to `''` before delegating to the parent repository.

### Test infrastructure

- **Removed `uses(RefreshDatabase::class)` from all tests.** Testbench 10 (required for L12) routed `RefreshDatabase` through `artisan('migrate:fresh')`, which crashed because L12's `Command::run()` accesses `$this->laravel` and a Twill command was being resolved before the container was ready. With SQLite in-memory, each test gets a fresh app — `TestCase::defineDatabaseMigrations()` and `tearDown()` provide isolation without artisan.
- **CI workflow:** `composer require` split into framework + dev steps to keep `orchestra/testbench` in `require-dev` (avoids a Windows interactive prompt). `composer update -W` added to allow transitive upgrades (cartalyst/tags v14→v15, etc.).
- **PHPStan:** `--memory-limit=2G` passed in CI (default 128M crashed under phpstan v2). View-string false-positive on package-registered view added to baseline.

### Dev dependency constraints widened

| Package | Before | After |
|---|---|---|
| `larastan/larastan` | `^2.9` | `^2.9 \|\| ^3.0` |
| `pestphp/pest` | `^2.34` | `^2.34 \|\| ^3.0` |
| `pestphp/pest-plugin-laravel` | `^2.3` | `^2.3 \|\| ^3.0` |
| `pestphp/pest-plugin-arch` | `^2.7` | `^2.7 \|\| ^3.0` |
| `phpstan/phpstan-deprecation-rules` | `^1.1` | `^1.1 \|\| ^2.0` |
| `phpstan/phpstan-phpunit` | `^1.3` | `^1.3 \|\| ^2.0` |
| `orchestra/testbench` | `^9.0.0\|\|^8.22.0` | `^9.0.0 || ^10.0.0

## v2.0.2 - 2026-05-09

### Bug fixes

- **Null values persisted to `translation_values.value` when an editor cleared a field.**
  Clearing a locale value in the Keys or Groups form could send `null` to the repository,
  which was stored as-is in the database instead of an empty string.
  Both `TranslationRepository` and `TranslationGroupRepository` now normalise any `null`
  field value to `''` before persisting.

### Documentation

- Added **Editorial interface** section to the README covering the Keys and Groups edit
  forms (fields, read-only behaviour, auto-sync on save), the `allow_empty` checkbox and
  its effect on validation in each module, and the null-to-empty-string normalisation rule.

## v2.0.1 - 2026-03-31

### Bug fixes

- **`CannotCombineNavigationBuilderWithLegacyConfig` exception on install.** Apps using the `TwillNavigation` builder API would crash at boot because the package unconditionally registered the Translations entries via the legacy `twill-navigation` config array. Both APIs cannot coexist in Twill 3.

### New config flag: `legacy-twill-navigation`

```php
// config/translation-handler.php
'legacy-twill-navigation' => true, // default — behaviour unchanged from v2.0





```
Set to `false` if your app uses the `TwillNavigation` builder and register the navigation entries yourself. See the [Navigation](README.md#navigation) section in the README for both approaches.

### Other changes

- Package migrations are now auto-loaded via `loadMigrationsFrom()` — no manual step required after `composer require`.
- Tests added for both flag states (`legacy-twill-navigation = true/false`), verifying that the `twill-navigation` config is populated or left untouched accordingly, and that routes remain registered in both cases.

## v2.0.0 — Twill 3 + Laravel 11 - 2026-03-19

> **Twill 2 / Laravel 10 users:** stay on the [`v1.x`](https://github.com/brunoscode/twill-translation-handler/tree/v1.x) releases.

### Breaking changes

| Requirement | v1.x | v2.0 |
|---|---|---|
| PHP | 8.1+ | **8.2+** |
| Laravel | 10 | **11** |
| Twill | 2.x | **3.x** |
| laravel-translation-handler | ^1.0 | **^2.0** |

### Twill 3 compatibility

- All repository and controller methods updated to match Twill 3 contracts: `TwillModelContract` return type on `update`, strict signatures on `afterSave`, `filter`, `getFormFields`, `indexItemData`.
- Route name prefix is now read from `config('twill.admin_route_name_prefix', 'admin.')` everywhere — works with both Twill 3's default `twill.` prefix and custom values.

### Bug fixes

- **`importGroupCsv` ignored the selected CSV delimiter.** Uploading a group CSV with `,` or `⇥` as delimiter always fell back to `;` and failed. The delimiter is now forwarded correctly from the request.
- **Runtime `setOption` overrides ignored on Laravel 11.** Laravel 11's IoC container removed automatic conversion of positional parameters to named ones, causing `getCsvHandler()` / `getPhpHandler()` etc. to resolve a fresh `TranslationOptions` from config instead of using the modified one. Fixed in `laravel-translation-handler` v2.0.2 (named parameters now used explicitly).

### Tests

Rewrote the test infrastructure to be fully compatible with Laravel 11 + SQLite (Twill's default migrations include a cascade migration that fails on SQLite in Laravel 11 — all tables are now defined inline in `TestCase`).

Added `TranslationToolsControllerTest` with 14 cases covering:

- `importFromCsv` and `importGroupCsv` with `;`, `,` and `⇥` delimiters
- `exportToCsv` and `exportGroupCsv` download responses
- Error paths: mismatched delimiter, missing file, non-existent group

> The 404 test for unknown group uses a model-level assertion rather than an HTTP request to avoid Twill attempting to render its error view (which requires compiled assets not present in the test pipeline).

### Upgrade from v1.x

```bash
composer require brunoscode/twill-translation-handler:^2.0 area17/twill:^3.0 laravel/framework:^11.0
php artisan migrate






```
## v1.0.0 - 2026-03-18

First stable release of **Twill Translation Handler** — a Twill 2 capsule for managing Laravel translations directly from the admin panel.

### Features

- **Translation keys** — browse and edit all translation keys stored in the database, with per-locale values editable inline.
- **Groups** — translations are automatically organized by prefix (e.g. `messages`, `messages.validation`). Intermediate groups are created automatically on import. Each group can be edited as a single form with all matching keys and their locale values exposed directly as textareas.
- **Search** — filter translations by key and value in both the keys list and the groups list.
- **Auto-sync to PHP files** — saving a translation key or a group automatically writes the corresponding PHP language file, keeping the filesystem always in sync with the database.
- **CSV import / export** — upload a CSV to import translations into the database (and PHP files), or download all translations as CSV from the tools page.
- **Per-group CSV export** — download a filtered CSV for a single group directly from its edit page (GET request, no nested form).
- **Allow empty values flag** — each group form exposes a checkbox to bypass validation and allow saving empty translation values.
- **Validation with translation keys** — form validation errors on the group page display the actual translation key (`messages.welcome (it) is required`) instead of the internal field name.
- **Automatic navigation** — a *Translations* entry with three sub-pages (Keys, Groups, Import / Export) is registered in the Twill sidebar automatically at boot. Position in the sidebar can be controlled via a placeholder in `config/twill-navigation.php`.

### Requirements

- PHP 8.2 or 8.3
- Laravel 10
- Twill 2.x
- [brunoscode/laravel-translation-handler](https://github.com/BrunosCode/LaravelTranslationHandler) ^1.0

> Support for Twill 3 and Laravel 11 is planned for a future release.
**Full Changelog**: https://github.com/BrunosCode/TwillTranslationHandler/compare/v0.2...v1
