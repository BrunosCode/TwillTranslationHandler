<?php

use A17\Twill\Services\Listings\Filters\FreeTextSearch;
use A17\Twill\Services\Listings\TableColumn;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Http\Controllers\TranslationGroupController;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use Illuminate\Http\Request;

it('offers only the locales the translation handler exports in the group form', function () {
    config([
        'translatable.locales' => ['en', 'it', 'fr'],
        'translation-handler.locales' => ['en', 'it'],
    ]);

    $controller = app(TranslationGroupController::class);
    $formData = (fn () => $this->formData(Request::create('/')))->call($controller);

    expect(array_column($formData['languages'], 'value'))->toBe(['en', 'it']);
});

function searchGroups(string $term): array
{
    $controller = app(TranslationGroupController::class);
    [$query, $columns] = (fn () => [$this->searchQuery, $this->searchColumns])->call($controller);

    return FreeTextSearch::make()
        ->searchFor($term)
        ->searchQuery($query)
        ->searchColumns($columns)
        ->applyFilter(TranslationGroup::query())
        ->orderBy('prefix')
        ->pluck('prefix')
        ->all();
}

it('searches prefixes, keys and values from the listing search box', function () {
    TranslationGroup::create(['prefix' => 'menu']);
    TranslationGroup::create(['prefix' => 'a_b']);
    Translation::create(['key' => 'menu.home'])
        ->translations()->create(['locale' => 'en', 'value' => 'Homepage', 'active' => true]);

    expect(searchGroups('Homepage'))->toBe(['menu'])
        ->and(searchGroups('home'))->toBe(['menu'])
        ->and(searchGroups('_'))->toBe(['a_b']);
});

it('configures the group listing with the twill 3 api', function () {
    $controller = app(TranslationGroupController::class);

    // getIndexOption() also checks the user's permissions: read the options set up.
    $options = (fn () => collect(['create', 'delete', 'bulkDelete', 'publish', 'bulkPublish', 'feature', 'bulkFeature'])
        ->mapWithKeys(fn (string $option) => [$option => $this->indexOptions[$option] ?? $this->defaultIndexOptions[$option]])
        ->all())->call($controller);

    // The default columns are permission-aware: act as a user allowed everything.
    $columns = (function () {
        $this->user = new class
        {
            public function can(): bool
            {
                return true;
            }
        };

        return $this->getIndexTableColumns();
    })->call($controller);

    expect(array_filter($options))->toBe([])
        ->and($columns->map(fn (TableColumn $column) => $column->getField())->values()->all())->toContain('prefix', 'translations_count')
        ->and($columns->first(fn (TableColumn $column) => $column->isDefaultSort())?->getField())->toBe('prefix');
});
