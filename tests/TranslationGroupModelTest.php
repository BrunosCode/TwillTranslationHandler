<?php

use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use Illuminate\Database\QueryException;

it('can create a translation group', function () {
    $group = TranslationGroup::create(['prefix' => 'menu']);
    $group->refresh();

    expect($group->prefix)->toBe('menu')
        ->and((bool) $group->published)->toBeTrue();
});

it('enforces unique prefix', function () {
    TranslationGroup::create(['prefix' => 'menu']);
    TranslationGroup::create(['prefix' => 'menu']);
})->throws(QueryException::class);

it('has published default to true', function () {
    $group = TranslationGroup::create(['prefix' => 'test']);
    $group->refresh();

    expect((bool) $group->published)->toBeTrue();
});

it('supports soft deletes', function () {
    $group = TranslationGroup::create(['prefix' => 'test']);
    $group->delete();

    expect(TranslationGroup::count())->toBe(0)
        ->and(TranslationGroup::withTrashed()->count())->toBe(1);
});

it('returns translations matching prefix', function () {
    $group = TranslationGroup::create(['prefix' => 'menu']);

    Translation::create(['key' => 'menu.home']);
    Translation::create(['key' => 'menu.about']);
    Translation::create(['key' => 'other.key']);

    expect($group->getTranslationsQuery()->count())->toBe(2);
});

it('returns translations count accessor', function () {
    $group = TranslationGroup::create(['prefix' => 'nav']);

    Translation::create(['key' => 'nav.home']);
    Translation::create(['key' => 'nav.about']);
    Translation::create(['key' => 'nav.contact']);

    expect($group->translations_count)->toBe(3);
});

it('returns zero when no translations match', function () {
    $group = TranslationGroup::create(['prefix' => 'empty']);

    expect($group->translations_count)->toBe(0);
});

it('matches nested keys under prefix', function () {
    $group = TranslationGroup::create(['prefix' => 'menu']);

    Translation::create(['key' => 'menu.items.home']);
    Translation::create(['key' => 'menu.items.about']);
    Translation::create(['key' => 'menu.title']);

    expect($group->getTranslationsQuery()->count())->toBe(3);
});

it('does not match keys that only start with prefix string', function () {
    $group = TranslationGroup::create(['prefix' => 'menu']);

    Translation::create(['key' => 'menubar.test']);
    Translation::create(['key' => 'menu.real']);

    expect($group->getTranslationsQuery()->count())->toBe(1);
});

it('resolves the lang file name from the first prefix segment', function () {
    expect((new TranslationGroup(['prefix' => 'auth']))->getFileName())->toBe('auth')
        ->and((new TranslationGroup(['prefix' => 'auth.messages.errors']))->getFileName())->toBe('auth');
});
