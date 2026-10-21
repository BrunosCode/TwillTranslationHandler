<?php

use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function makeCsv(array $rows, string $delimiter): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'csv_test_');
    $fp = fopen($path, 'w');
    foreach ($rows as $row) {
        fputcsv($fp, $row, $delimiter);
    }
    fclose($fp);

    return new UploadedFile($path, 'translations.csv', 'text/csv', null, true);
}

// ---------------------------------------------------------------------------
// Setup
// ---------------------------------------------------------------------------

beforeEach(function () {
    $this->tmpDir = sys_get_temp_dir().'/th_test_'.uniqid();
    File::ensureDirectoryExists($this->tmpDir);

    config([
        'translation-handler.phpPath' => $this->tmpDir,
        'translation-handler.csvPath' => $this->tmpDir,
    ]);
});

afterEach(function () {
    File::deleteDirectory($this->tmpDir);
});

// ---------------------------------------------------------------------------
// importFromCsv — delimiter handling (core regression)
// ---------------------------------------------------------------------------

it('imports csv with semicolon delimiter and stores translations in db', function () {
    $file = makeCsv([
        ['key', 'en', 'it'],
        ['test.hello', 'Hello', 'Ciao'],
        ['test.bye', 'Bye', 'Arrivederci'],
    ], ';');

    $this->withoutMiddleware()
        ->post(route('admin.translations.translationTools.importFromCsv'), [
            'csv_file' => $file,
            'csv_delimiter' => ';',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(Translation::where('key', 'test.hello')->exists())->toBeTrue()
        ->and(Translation::where('key', 'test.bye')->exists())->toBeTrue();
});

it('imports csv with comma delimiter and stores translations in db', function () {
    $file = makeCsv([
        ['key', 'en', 'it'],
        ['test.hello', 'Hello', 'Ciao'],
        ['test.bye', 'Bye', 'Arrivederci'],
    ], ',');

    $this->withoutMiddleware()
        ->post(route('admin.translations.translationTools.importFromCsv'), [
            'csv_file' => $file,
            'csv_delimiter' => ',',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(Translation::where('key', 'test.hello')->exists())->toBeTrue()
        ->and(Translation::where('key', 'test.bye')->exists())->toBeTrue();
});

it('imports csv with tab delimiter and stores translations in db', function () {
    $file = makeCsv([
        ['key', 'en', 'it'],
        ['test.hello', 'Hello', 'Ciao'],
    ], "\t");

    $this->withoutMiddleware()
        ->post(route('admin.translations.translationTools.importFromCsv'), [
            'csv_file' => $file,
            'csv_delimiter' => "\t",
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(Translation::where('key', 'test.hello')->exists())->toBeTrue();
});

it('imports correct locale values when using comma delimiter', function () {
    $file = makeCsv([
        ['key', 'en', 'it'],
        ['test.greeting', 'Hello world', 'Ciao mondo'],
    ], ',');

    $this->withoutMiddleware()
        ->post(route('admin.translations.translationTools.importFromCsv'), [
            'csv_file' => $file,
            'csv_delimiter' => ',',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    $translation = Translation::where('key', 'test.greeting')->firstOrFail();

    expect($translation->translate('en')->value)->toBe('Hello world')
        ->and($translation->translate('it')->value)->toBe('Ciao mondo');
});

it('returns error when delimiter does not match the uploaded file', function () {
    // File uses comma, but request declares semicolon → parse fails
    $file = makeCsv([
        ['key', 'en', 'it'],
        ['test.hello', 'Hello', 'Ciao'],
    ], ',');

    $this->withoutMiddleware()
        ->post(route('admin.translations.translationTools.importFromCsv'), [
            'csv_file' => $file,
            'csv_delimiter' => ';',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Translation::count())->toBe(0);
});

it('returns validation error when no file is uploaded', function () {
    $this->withoutMiddleware()
        ->post(route('admin.translations.translationTools.importFromCsv'), [
            'csv_delimiter' => ';',
        ])
        ->assertSessionHasErrors('csv_file');

    expect(Translation::count())->toBe(0);
});

// ---------------------------------------------------------------------------
// exportToCsv
// ---------------------------------------------------------------------------

it('exports translations to csv and returns a download with semicolon delimiter', function () {
    Translation::create(['key' => 'test.hello', 'published' => true])
        ->translations()->createMany([
            ['locale' => 'en', 'value' => 'Hello', 'active' => true],
            ['locale' => 'it', 'value' => 'Ciao', 'active' => true],
        ]);

    $response = $this->withoutMiddleware()
        ->post(route('admin.translations.translationTools.exportToCsv'), [
            'csv_delimiter' => ';',
        ]);

    $response->assertOk();
    $response->assertDownload();

    $csvPath = config('translation-handler.csvPath').DIRECTORY_SEPARATOR.config('translation-handler.csvFileName', 'translations').'.csv';
    expect(File::exists($csvPath) || $response->headers->get('content-disposition') !== null)->toBeTrue();
});

it('exports translations to csv with comma delimiter', function () {
    Translation::create(['key' => 'test.hello', 'published' => true])
        ->translations()->createMany([
            ['locale' => 'en', 'value' => 'Hello', 'active' => true],
            ['locale' => 'it', 'value' => 'Ciao', 'active' => true],
        ]);

    $response = $this->withoutMiddleware()
        ->post(route('admin.translations.translationTools.exportToCsv'), [
            'csv_delimiter' => ',',
        ]);

    $response->assertOk();
    $response->assertDownload();
});

it('redirects with an error when db has no translations to export', function () {
    // Since laravel-translation-handler v2.3, export() returns false and writes
    // nothing when the source has no translations, so no CSV file is generated and
    // the controller redirects back with an error instead of returning a download.
    $this->withoutMiddleware()
        ->post(route('admin.translations.translationTools.exportToCsv'), [
            'csv_delimiter' => ';',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');
});

// ---------------------------------------------------------------------------
// exportGroupCsv / importGroupCsv
// ---------------------------------------------------------------------------

it('exports a single group as csv', function () {
    $group = TranslationGroup::create(['prefix' => 'test', 'published' => true]);

    Translation::create(['key' => 'test.hello', 'published' => true])
        ->translations()->createMany([
            ['locale' => 'en', 'value' => 'Hello', 'active' => true],
            ['locale' => 'it', 'value' => 'Ciao', 'active' => true],
        ]);

    $response = $this->withoutMiddleware()
        ->get(route('admin.translations.translationGroups.exportCsv', $group->id));

    $response->assertOk();
    $response->assertDownload();
});

it('imports group csv with comma delimiter', function () {
    $group = TranslationGroup::create(['prefix' => 'test', 'published' => true]);

    $file = makeCsv([
        ['key', 'en', 'it'],
        ['test.greeting', 'Hello', 'Ciao'],
    ], ',');

    $this->withoutMiddleware()
        ->post(route('admin.translations.translationGroups.importCsv', $group->id), [
            'csv_file' => $file,
            'csv_delimiter' => ',',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(Translation::where('key', 'test.greeting')->exists())->toBeTrue();
});

it('imports group csv with semicolon delimiter', function () {
    $group = TranslationGroup::create(['prefix' => 'test', 'published' => true]);

    $file = makeCsv([
        ['key', 'en', 'it'],
        ['test.greeting', 'Hello', 'Ciao'],
    ], ';');

    $this->withoutMiddleware()
        ->post(route('admin.translations.translationGroups.importCsv', $group->id), [
            'csv_file' => $file,
            'csv_delimiter' => ';',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(Translation::where('key', 'test.greeting')->exists())->toBeTrue();
});

it('returns error on group import when delimiter does not match file', function () {
    $group = TranslationGroup::create(['prefix' => 'test', 'published' => true]);

    $file = makeCsv([
        ['key', 'en', 'it'],
        ['test.greeting', 'Hello', 'Ciao'],
    ], ',');

    $this->withoutMiddleware()
        ->post(route('admin.translations.translationGroups.importCsv', $group->id), [
            'csv_file' => $file,
            'csv_delimiter' => ';',
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(Translation::count())->toBe(0);
});

it('throws ModelNotFoundException when exporting a non-existent group', function () {
    expect(fn () => TranslationGroup::findOrFail(999))
        ->toThrow(ModelNotFoundException::class);
});

it('exports a nested group csv import into the top-level php file', function () {
    Translation::create(['key' => 'auth.failed', 'published' => true])
        ->translations()->createMany([
            ['locale' => 'en', 'value' => 'Failed', 'active' => true],
            ['locale' => 'it', 'value' => 'Fallito', 'active' => true],
        ]);
    $group = TranslationGroup::create(['prefix' => 'auth.messages', 'published' => true]);

    $file = makeCsv([
        ['key', 'en', 'it'],
        ['auth.messages.hello', 'Hello', 'Ciao'],
    ], ';');

    $this->withoutMiddleware()
        ->post(route('admin.translations.translationGroups.importCsv', $group->id), [
            'csv_file' => $file,
            'csv_delimiter' => ';',
        ])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(File::exists($this->tmpDir.'/en/auth.messages.php'))->toBeFalse()
        ->and(require $this->tmpDir.'/en/auth.php')->toBe([
            'failed' => 'Failed',
            'messages' => ['hello' => 'Hello'],
        ]);
});
