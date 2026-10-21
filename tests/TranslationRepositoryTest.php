<?php

use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories\TranslationRepository;

beforeEach(function () {
    $this->repository = app(TranslationRepository::class);
});

it('can create a translation via repository', function () {
    $translation = $this->repository->create([
        'key' => 'test.repo.create',
        'published' => true,
        'languages' => [
            [
                'shortlabel' => 'EN',
                'label' => 'English',
                'value' => 'en',
                'published' => true,
            ],
            [
                'shortlabel' => 'IT',
                'label' => 'Italian',
                'value' => 'it',
                'published' => true,
            ],
        ],
    ]);

    expect($translation->key)->toBe('test.repo.create')
        ->and($translation->published)->toBeTrue();
});

it('can update a translation value directly', function () {
    $translation = Translation::create([
        'key' => 'test.repo.update',
        'published' => true,
    ]);

    $translation->translations()->create([
        'locale' => 'en',
        'value' => 'Old value',
        'active' => true,
    ]);

    $translationValue = $translation->translations()->where('locale', 'en')->first();
    $translationValue->update(['value' => 'New value']);

    $translation->refresh();
    $translation->load('translations');

    expect($translation->translate('en')->value)->toBe('New value');
});

it('can get all translations via repository', function () {
    Translation::create(['key' => 'test.list.a', 'published' => true]);
    Translation::create(['key' => 'test.list.b', 'published' => true]);
    Translation::create(['key' => 'test.list.c', 'published' => true]);

    $all = $this->repository->listAll();

    expect($all)->toHaveCount(3);
});

it('can delete a translation via repository', function () {
    $translation = Translation::create(['key' => 'test.delete']);

    $this->repository->delete($translation->id);

    expect(Translation::count())->toBe(0)
        ->and(Translation::withTrashed()->count())->toBe(1);
});

it('treats like wildcards in the search literally', function () {
    Translation::create(['key' => 'test.percent'])
        ->translations()->create(['locale' => 'en', 'value' => '100% sure', 'active' => true]);
    Translation::create(['key' => 'test.plain'])
        ->translations()->create(['locale' => 'en', 'value' => 'Plain', 'active' => true]);
    Translation::create(['key' => 'test.under_score']);

    $search = fn (string $term) => $this->repository
        ->filter(Translation::query(), ['search' => $term])
        ->orderBy('key')
        ->pluck('key')
        ->all();

    expect($search('%'))->toBe(['test.percent'])
        ->and($search('_'))->toBe(['test.under_score']);
});
