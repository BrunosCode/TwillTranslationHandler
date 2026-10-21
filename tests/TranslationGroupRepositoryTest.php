<?php

use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories\TranslationGroupRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

beforeEach(function () {
    $this->repository = app(TranslationGroupRepository::class);
});

it('returns translation fields in getFormFields', function () {
    $group = TranslationGroup::create(['prefix' => 'menu']);

    $t1 = Translation::create(['key' => 'menu.home']);
    $t1->translations()->create(['locale' => 'en', 'value' => 'Home', 'active' => true]);
    $t1->translations()->create(['locale' => 'it', 'value' => 'Casa', 'active' => true]);

    $t2 = Translation::create(['key' => 'menu.about']);
    $t2->translations()->create(['locale' => 'en', 'value' => 'About', 'active' => true]);

    $fields = $this->repository->getFormFields($group);

    expect($fields)->toHaveKey('translations')
        ->and($fields['translations'])->toHaveKey('trans_'.$t1->id)
        ->and($fields['translations'])->toHaveKey('trans_'.$t2->id);
});

it('includes translated values in form fields', function () {
    $group = TranslationGroup::create(['prefix' => 'nav']);

    $t = Translation::create(['key' => 'nav.home']);
    $t->translations()->create(['locale' => 'en', 'value' => 'Home', 'active' => true]);
    $t->translations()->create(['locale' => 'it', 'value' => 'Casa', 'active' => true]);

    $fields = $this->repository->getFormFields($group);

    expect($fields['translations']['trans_'.$t->id])->toBe(['en' => 'Home', 'it' => 'Casa']);
});

it('syncs translation values on update', function () {
    $group = TranslationGroup::create(['prefix' => 'sync']);

    $t = Translation::create(['key' => 'sync.hello']);
    $t->translations()->create(['locale' => 'en', 'value' => 'Old', 'active' => true]);
    $t->translations()->create(['locale' => 'it', 'value' => 'Vecchio', 'active' => true]);

    $this->repository->update($group->id, [
        'prefix' => 'sync',
        'published' => true,
        'trans_'.$t->id => ['en' => 'New', 'it' => 'Nuovo'],
    ]);

    $t->refresh();
    $t->load('translations');

    expect($t->translate('en')->value)->toBe('New')
        ->and($t->translate('it')->value)->toBe('Nuovo');
});

it('handles group with no translations gracefully', function () {
    $group = TranslationGroup::create(['prefix' => 'empty']);

    $fields = $this->repository->getFormFields($group);

    expect($fields['translations'] ?? [])->toBeEmpty();
});

it('returns only existing locale values for partial translations', function () {
    $group = TranslationGroup::create(['prefix' => 'partial']);

    $t = Translation::create(['key' => 'partial.key']);
    $t->translations()->create(['locale' => 'en', 'value' => 'English only', 'active' => true]);

    $fields = $this->repository->getFormFields($group);

    expect($fields['translations']['trans_'.$t->id]['en'])->toBe('English only')
        ->and($fields['translations']['trans_'.$t->id])->not->toHaveKey('it');
});

it('treats like wildcards in the search and in the group prefix literally', function () {
    TranslationGroup::create(['prefix' => 'a_b']);
    TranslationGroup::create(['prefix' => 'plain']);
    Translation::create(['key' => 'axb.needle']);
    Translation::create(['key' => 'plain.text'])
        ->translations()->create(['locale' => 'en', 'value' => '100% sure', 'active' => true]);

    $search = fn (string $term) => $this->repository
        ->filter(TranslationGroup::query(), ['search' => $term])
        ->orderBy('prefix')
        ->pluck('prefix')
        ->all();

    expect($search('needle'))->toBe([])
        ->and($search('%'))->toBe(['plain'])
        ->and($search('_'))->toBe(['a_b']);
});

it('loads the translations count of the listing without a query per group', function () {
    foreach (['menu', 'nav', 'footer'] as $prefix) {
        TranslationGroup::create(['prefix' => $prefix]);
        Translation::create(['key' => $prefix.'.one']);
        Translation::create(['key' => $prefix.'.two']);
    }
    Translation::create(['key' => 'menu.three']);
    Translation::create(['key' => 'menu.gone'])->delete();

    DB::enableQueryLog();

    $counts = $this->repository->get([], [], [], -1)
        ->mapWithKeys(fn (TranslationGroup $group) => [$group->prefix => $group->translations_count])
        ->all();

    expect(DB::getQueryLog())->toHaveCount(1)
        ->and($counts)->toEqual(['menu' => 3, 'nav' => 2, 'footer' => 2])
        // Twill's status counters run count() on the same filtered query
        ->and($this->repository->filter(TranslationGroup::query(), ['search' => 'nav'])->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// update(): behaviour the save must preserve
// ---------------------------------------------------------------------------

function saveGroup(TranslationGroup $group, array $values): void
{
    $fields = ['prefix' => $group->prefix, 'published' => true];

    foreach ($values as $translationId => $localeValues) {
        $fields['trans_'.$translationId] = $localeValues;
    }

    app(TranslationGroupRepository::class)->update($group->id, $fields);
}

function storedValues(Translation $translation): array
{
    return $translation->translations()->orderBy('locale')->get(['locale', 'value', 'active'])
        ->mapWithKeys(fn ($tv) => [$tv->locale => [$tv->value, (bool) $tv->active]])
        ->all();
}

it('stores a null value as an empty string', function () {
    $group = TranslationGroup::create(['prefix' => 'save']);
    $t = Translation::create(['key' => 'save.key']);
    $t->translations()->create(['locale' => 'en', 'value' => 'Old', 'active' => true]);

    saveGroup($group, [$t->id => ['en' => null]]);

    expect(storedValues($t))->toBe(['en' => ['', true]]);
});

it('reactivates an inactive value it saves', function () {
    $group = TranslationGroup::create(['prefix' => 'save']);
    $t = Translation::create(['key' => 'save.key']);
    $t->translations()->create(['locale' => 'en', 'value' => 'Old', 'active' => false]);
    $t->translations()->create(['locale' => 'it', 'value' => 'Vecchio', 'active' => false]);

    saveGroup($group, [$t->id => ['en' => 'New']]);

    expect(storedValues($t))->toBe(['en' => ['New', true], 'it' => ['Vecchio', false]]);
});

it('creates the value of a locale the key did not have yet', function () {
    $group = TranslationGroup::create(['prefix' => 'save']);
    $t = Translation::create(['key' => 'save.key']);
    $t->translations()->create(['locale' => 'en', 'value' => 'Hello', 'active' => true]);

    saveGroup($group, [$t->id => ['en' => 'Hello', 'it' => 'Ciao']]);

    expect(storedValues($t))->toBe(['en' => ['Hello', true], 'it' => ['Ciao', true]]);
});

it('leaves the other keys of the file untouched', function () {
    $group = TranslationGroup::create(['prefix' => 'save.nested']);
    $sibling = Translation::create(['key' => 'save.sibling']);
    $sibling->translations()->create(['locale' => 'en', 'value' => 'Sibling', 'active' => false]);
    $t = Translation::create(['key' => 'save.nested.key']);
    $t->translations()->create(['locale' => 'en', 'value' => 'Old', 'active' => true]);

    saveGroup($group, [$t->id => ['en' => 'New']]);

    expect(storedValues($t))->toBe(['en' => ['New', true]])
        ->and(storedValues($sibling))->toBe(['en' => ['Sibling', false]]);
});

it('ignores fields of keys that do not exist', function () {
    $group = TranslationGroup::create(['prefix' => 'save']);

    saveGroup($group, [999 => ['en' => 'Ghost']]);

    expect(Translation::count())->toBe(0);
});

it('saves a whole group with a constant number of queries', function () {
    config(['translation-handler.phpPath' => $tmp = sys_get_temp_dir().'/th_save_'.uniqid()]);

    $group = TranslationGroup::create(['prefix' => 'save']);
    $values = [];

    foreach (range(1, 30) as $i) {
        $t = Translation::create(['key' => "save.k{$i}"]);
        $t->translations()->createMany([
            ['locale' => 'en', 'value' => 'old', 'active' => true],
            ['locale' => 'it', 'value' => 'vecchio', 'active' => true],
        ]);
        $values[$t->id] = ['en' => "new {$i}", 'it' => "nuovo {$i}"];
    }

    DB::enableQueryLog();

    saveGroup($group, $values);

    // Was two queries per value (about 155 here).
    expect(count(DB::getQueryLog()))->toBeLessThan(20)
        ->and(storedValues(Translation::where('key', 'save.k30')->first()))->toBe([
            'en' => ['new 30', true],
            'it' => ['nuovo 30', true],
        ]);

    File::deleteDirectory($tmp);
});
