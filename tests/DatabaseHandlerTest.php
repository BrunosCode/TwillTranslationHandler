<?php

use BrunosCode\TranslationHandler\Collections\TranslationCollection;
use BrunosCode\TranslationHandler\Data\Translation as TranslationData;
use BrunosCode\TranslationHandler\Data\TranslationOptions;
use BrunosCode\TranslationHandler\Facades\TranslationHandler;
use BrunosCode\TwillTranslationHandler\DatabaseHandler;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use Illuminate\Support\Facades\DB;

function makeHandler(): DatabaseHandler
{
    return new DatabaseHandler(new TranslationOptions);
}

function makeCollection(array $keys): TranslationCollection
{
    $collection = new TranslationCollection;

    foreach ($keys as $key) {
        $collection->push(new TranslationData($key, 'en', 'value'));
    }

    return $collection;
}

it('creates groups from translation key prefixes', function () {
    $handler = makeHandler();
    $db = app('db')->connection();

    $translations = makeCollection(['test.menu.home', 'test.menu.about']);

    $handler->handleInsert($db, $translations, 'test');

    expect(TranslationGroup::count())->toBe(2)
        ->and(TranslationGroup::where('prefix', 'test')->exists())->toBeTrue()
        ->and(TranslationGroup::where('prefix', 'test.menu')->exists())->toBeTrue();
});

it('creates hierarchical groups for deeply nested keys', function () {
    $handler = makeHandler();
    $db = app('db')->connection();

    $translations = makeCollection(['file.key1.key2.key3']);

    $handler->handleInsert($db, $translations, 'file');

    expect(TranslationGroup::count())->toBe(3)
        ->and(TranslationGroup::where('prefix', 'file')->exists())->toBeTrue()
        ->and(TranslationGroup::where('prefix', 'file.key1')->exists())->toBeTrue()
        ->and(TranslationGroup::where('prefix', 'file.key1.key2')->exists())->toBeTrue();
});

it('does not create duplicate groups', function () {
    $handler = makeHandler();
    $db = app('db')->connection();

    $translations = makeCollection([
        'test.menu.home',
        'test.menu.about',
        'test.footer.copyright',
    ]);

    $handler->handleInsert($db, $translations, 'test');

    expect(TranslationGroup::where('prefix', 'test')->count())->toBe(1);
});

it('does not create a group for single-part keys', function () {
    $handler = makeHandler();
    $db = app('db')->connection();

    $translations = makeCollection(['hello']);

    $handler->handleInsert($db, $translations, null);

    expect(TranslationGroup::count())->toBe(0);
});

it('handles multiple files creating shared prefixes', function () {
    $handler = makeHandler();
    $db = app('db')->connection();

    $batch1 = makeCollection(['app.title', 'app.description']);
    $handler->handleInsert($db, $batch1, 'app');

    $batch2 = makeCollection(['app.footer.text']);
    $handler->handleInsert($db, $batch2, 'app');

    expect(TranslationGroup::where('prefix', 'app')->count())->toBe(1)
        ->and(TranslationGroup::where('prefix', 'app.footer')->exists())->toBeTrue();
});

it('splits group prefixes with the runtime key delimiter', function () {
    TranslationHandler::setOption('keyDelimiter', '::');

    makeHandler()->handleInsert(app('db')->connection(), makeCollection(['test::menu::home']), 'test');

    TranslationHandler::resetOptions();

    expect(TranslationGroup::orderBy('prefix')->pluck('prefix')->all())->toBe(['test', 'test::menu']);
});

it('creates all missing groups with a single query', function () {
    TranslationGroup::create(['prefix' => 'test']);

    DB::enableQueryLog();

    makeHandler()->handleInsert(app('db')->connection(), makeCollection(['test.a.b.c', 'test.x.y']), 'test');

    $groupQueries = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'translation_groups'));

    expect($groupQueries)->toHaveCount(1)
        ->and(TranslationGroup::orderBy('prefix')->pluck('prefix')->all())->toBe(['test', 'test.a', 'test.a.b', 'test.x'])
        ->and(TranslationGroup::where('prefix', 'test.a')->first()->published)->toBeTruthy()
        ->and(TranslationGroup::where('prefix', 'test.a')->first()->created_at)->not->toBeNull();
});

it('deletes the groups left without keys after a sync', function () {
    $handler = makeHandler();

    $handler->put(makeCollection(['test.old.a', 'test.kept.b']));
    expect(TranslationGroup::orderBy('prefix')->pluck('prefix')->all())->toBe(['test', 'test.kept', 'test.old']);

    $handler->put(makeCollection(['test.kept.b', 'test.new.c']));

    expect(TranslationGroup::orderBy('prefix')->pluck('prefix')->all())->toBe(['test', 'test.kept', 'test.new'])
        ->and(TranslationGroup::withTrashed()->where('prefix', 'test.old')->exists())->toBeFalse();
});

it('keeps groups whose keys live outside the synced file names', function () {
    TranslationGroup::create(['prefix' => 'other']);
    TranslationGroup::create(['prefix' => 'other.nested']);
    Translation::create(['key' => 'other.nested.key']);

    makeHandler()->put(makeCollection(['test.a']));

    expect(TranslationGroup::orderBy('prefix')->pluck('prefix')->all())->toBe(['other', 'other.nested', 'test']);
});

it('deletes the groups of the file names it deletes', function () {
    $handler = makeHandler();
    $handler->put(makeCollection(['test.menu.home']));

    $handler->delete();

    expect(TranslationGroup::withTrashed()->count())->toBe(0);
});
