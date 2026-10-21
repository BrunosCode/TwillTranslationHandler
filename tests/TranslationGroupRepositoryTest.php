<?php

use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories\TranslationGroupRepository;
use Illuminate\Support\Facades\DB;

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
