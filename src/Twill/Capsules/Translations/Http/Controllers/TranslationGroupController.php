<?php

namespace BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Http\Controllers;

use A17\Twill\Http\Controllers\Admin\ModuleController;
use A17\Twill\Services\Listings\Columns\Text;
use A17\Twill\Services\Listings\TableColumn;
use A17\Twill\Services\Listings\TableColumns;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories\TranslationGroupRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class TranslationGroupController extends ModuleController
{
    protected $moduleName = 'translationGroups';

    protected function setUpController(): void
    {
        $this->setTitleColumnKey('prefix');
        $this->setTitleColumnLabel('Prefix');
        $this->setTitleFormKey('prefix');

        // Groups follow the keys: imports create and delete them.
        $this->disableCreate();
        $this->disableDelete();
        $this->disableBulkDelete();
        $this->disablePublish();
        $this->disableBulkPublish();
        $this->disableEditor();

        // The search box matches prefixes, keys and values through the
        // repository, instead of Twill's default LIKE on the title column.
        $this->setSearchColumns([]);
        $this->setSearchQuery(fn (Builder $query, string $search) => app(TranslationGroupRepository::class)->search($query, $search));
    }

    protected function getViewPrefix(): ?string
    {
        return 'Translations.resources.views.admin.translationGroups';
    }

    protected function formData($request)
    {
        // The locales the core exports to lang files: a value saved for any
        // other locale would stay in the database and never reach a file.
        $locales = config('translation-handler.locales', ['en']);

        return [
            'translate' => true,
            'controlLanguagesPublication' => false,
            'languages' => Collection::make($locales)->map(function ($locale) {
                return [
                    'shortlabel' => strtoupper($locale),
                    'label' => $locale,
                    'value' => $locale,
                    'published' => true,
                ];
            })->values()->toArray(),
        ];
    }

    protected function getIndexTableColumns(): TableColumns
    {
        $columns = parent::getIndexTableColumns();

        $columns->first(fn (TableColumn $column) => $column->getField() === 'prefix')?->sortByDefault();

        return $columns;
    }

    protected function additionalIndexTableColumns(): TableColumns
    {
        // Loaded by TranslationGroupRepository::filter() with a subquery.
        return TableColumns::make([
            Text::make()->field('translations_count')->title('Translations'),
        ]);
    }
}
