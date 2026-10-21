<?php

use A17\Twill\Services\Listings\Filters\FreeTextSearch;
use A17\Twill\Services\Listings\TableColumn;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Http\Controllers\TranslationController;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;

/**
 * Run the listing search the way Twill 3 does (ModuleController::getIndexItems).
 */
function searchTranslations(string $term): array
{
    $controller = app(TranslationController::class);
    [$query, $columns] = (fn () => [$this->searchQuery, $this->searchColumns])->call($controller);

    return FreeTextSearch::make()
        ->searchFor($term)
        ->searchQuery($query)
        ->searchColumns($columns)
        ->applyFilter(Translation::query())
        ->orderBy('key')
        ->pluck('key')
        ->all();
}

it('searches keys and values from the listing search box', function () {
    Translation::create(['key' => 'test.greeting'])
        ->translations()->create(['locale' => 'en', 'value' => 'Hello world', 'active' => true]);
    Translation::create(['key' => 'test.other'])
        ->translations()->create(['locale' => 'en', 'value' => 'Bye', 'active' => true]);

    expect(searchTranslations('world'))->toBe(['test.greeting'])
        ->and(searchTranslations('other'))->toBe(['test.other']);
});

it('treats like wildcards in the listing search literally', function () {
    Translation::create(['key' => 'test.percent'])
        ->translations()->create(['locale' => 'en', 'value' => '100% sure', 'active' => true]);
    Translation::create(['key' => 'test.plain']);

    expect(searchTranslations('%'))->toBe(['test.percent']);
});

it('configures the listing with the twill 3 api', function () {
    $controller = app(TranslationController::class);

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
        ->and($columns->map(fn (TableColumn $column) => $column->getField())->values()->all())->toContain('key', 'value')
        ->and($columns->first(fn (TableColumn $column) => $column->isDefaultSort())?->getField())->toBe('key');
});
