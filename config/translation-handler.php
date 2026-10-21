<?php

use BrunosCode\TranslationHandler\CsvFileHandler;
use BrunosCode\TranslationHandler\Data\TranslationOptions;
use BrunosCode\TranslationHandler\JsonFileHandler;
use BrunosCode\TranslationHandler\PhpFileHandler;
use BrunosCode\TranslationHandler\TranslationChecker;
use BrunosCode\TwillTranslationHandler\DatabaseHandler;

// config for BrunosCode/TranslationHandler, with the Twill defaults.
// It replaces the core config file: publish this one, not the core's.

return [
    /*
    |--------------------------------------------------------------------------
    | Legacy Twill navigation
    |--------------------------------------------------------------------------
    | Set to true to auto-register the Translations entries via the legacy
    | twill-navigation config array. Leave false if your app uses the
    | TwillNavigation builder API and you register the entries yourself.
    */
    'legacy-twill-navigation' => true,

    'keyDelimiter' => '.',

    // The lang files (lang/{locale}/{name}.php, without extension) managed
    // from the admin. Keys outside these files are not imported or exported.
    'fileNames' => ['messages'],
    // The locales exported to the lang files and offered in the group editor.
    // Keep this list identical to Twill's translatable.locales.
    'locales' => ['en', 'it'],

    // The database is the source of truth: the admin edits it and exports
    // to the PHP files after every save.
    'defaultImportFrom' => TranslationOptions::PHP,
    'defaultImportTo' => TranslationOptions::DB,
    'defaultExportFrom' => TranslationOptions::DB,
    'defaultExportTo' => TranslationOptions::PHP,

    'phpHandlerClass' => PhpFileHandler::class,
    'csvHandlerClass' => CsvFileHandler::class,
    'jsonHandlerClass' => JsonFileHandler::class,
    // The Twill handler also creates the translation groups on import.
    'dbHandlerClass' => DatabaseHandler::class,

    // if phpFormat is true exported PHP arrays use short syntax ([] instead of array())
    'phpFormat' => false,
    // if phpPint is true exported PHP files are formatted with Pint after writing.
    // The step is skipped silently when no Pint binary (laravel/pint) is found.
    'phpPint' => false,
    'phpPath' => lang_path(),

    'csvDelimiter' => ';',
    // if csvEscapeFormulas is true, values starting with =, +, -, @, tab or
    // carriage return are written with a leading ' so spreadsheet apps do not
    // evaluate them as formulas. The quote is stripped again on import.
    'csvEscapeFormulas' => true,
    // The admin downloads exports with this name; its working files in csvPath
    // get a random suffix per request, so concurrent editors do not collide.
    'csvFileName' => 'translations',
    // Must be writable by the web server: the admin CSV import and export
    // go through this directory.
    'csvPath' => storage_path('lang'),

    'jsonPath' => lang_path(),
    // if jsonFileName is empty locale will be used
    // if jsonFileName is not empty locale will be used as folder
    'jsonFileName' => '',
    // if jsonNested is true json output will be nested as php file
    'jsonNested' => false,
    // if jsonFormat is true json output will be formatted
    'jsonFormat' => true,

    // Source scanned by translation-handler:check / check-translations-tool.
    // Each key is a "side"; paths are relative to the base path (absolute ok).
    // See the core README for the optional per-side `patterns` entry.
    'check' => [
        'backend' => [
            'paths' => ['app', 'resources/views', 'routes', 'database'],
            'extensions' => ['php'],
        ],
        'frontend' => [
            'paths' => ['resources/js'],
            'extensions' => ['ts', 'tsx', 'js', 'jsx'],
        ],
    ],

    // When true, the keys of Laravel's bundled lang files (auth, pagination,
    // passwords, validation) count as defined during a check.
    'checkIncludeFrameworkKeys' => false,

    // Swap in a subclass of TranslationChecker to customise the scanning
    // regexes (override patternsFor()) or the defined-key resolution.
    'checkerClass' => TranslationChecker::class,
];
