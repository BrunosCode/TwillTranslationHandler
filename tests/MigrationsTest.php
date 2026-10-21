<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

// TestCase creates the tables inline: drop them so the real migrations run
// against an empty database (or one holding only the core tables).
beforeEach(function () {
    Schema::disableForeignKeyConstraints();
    Schema::dropIfExists('translation_values');
    Schema::dropIfExists('translation_keys');
    Schema::dropIfExists('translation_groups');
    Schema::enableForeignKeyConstraints();
});

function runMigrations(string $glob, string $direction = 'up'): void
{
    $files = File::glob($glob);

    if ($direction === 'down') {
        $files = array_reverse($files);
    }

    foreach ($files as $file) {
        (require $file)->{$direction}();
    }
}

function twillMigrations(): string
{
    return __DIR__.'/../src/Twill/Capsules/Translations/database/migrations/*.php';
}

function coreMigrations(): string
{
    return __DIR__.'/../vendor/brunoscode/laravel-translation-handler/database/migrations/create_translation_{keys,values}_table.php.stub';
}

function expectTwillSchema(): void
{
    expect(Schema::hasColumns('translation_keys', ['id', 'key', 'published', 'created_at', 'deleted_at']))->toBeTrue()
        ->and(Schema::hasColumns('translation_values', ['id', 'translation_key_id', 'locale', 'value', 'active', 'deleted_at']))->toBeTrue()
        ->and(Schema::hasColumns('translation_groups', ['id', 'prefix', 'published', 'deleted_at']))->toBeTrue();
}

it('creates the whole schema when the core tables do not exist', function () {
    runMigrations(twillMigrations());

    expectTwillSchema();
});

it('adds the twill columns to the tables created by the core migrations', function () {
    $stubs = File::glob(coreMigrations(), GLOB_BRACE);
    expect($stubs)->toHaveCount(2);

    foreach ($stubs as $stub) {
        (require $stub)->up();
    }

    expect(Schema::hasColumn('translation_keys', 'published'))->toBeFalse();

    runMigrations(twillMigrations());

    expectTwillSchema();
});

it('can run again on a complete schema', function () {
    runMigrations(twillMigrations());
    runMigrations(twillMigrations());

    expectTwillSchema();
});

it('rolls back to the core schema', function () {
    runMigrations(twillMigrations());
    runMigrations(twillMigrations(), 'down');

    expect(Schema::hasTable('translation_groups'))->toBeFalse()
        ->and(Schema::hasColumn('translation_keys', 'published'))->toBeFalse()
        ->and(Schema::hasColumn('translation_values', 'active'))->toBeFalse()
        ->and(Schema::hasColumns('translation_values', ['translation_key_id', 'locale', 'value']))->toBeTrue();
});
