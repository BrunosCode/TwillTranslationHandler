<?php

namespace BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Http\Controllers;

use A17\Twill\Http\Controllers\Admin\ModuleController;
use A17\Twill\Services\Listings\Columns\Text;
use A17\Twill\Services\Listings\TableColumn;
use A17\Twill\Services\Listings\TableColumns;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories\TranslationRepository;
use Illuminate\Database\Eloquent\Builder;

class TranslationController extends ModuleController
{
    protected $moduleName = 'translations';

    protected function setUpController(): void
    {
        $this->setTitleColumnKey('key');
        $this->setTitleColumnLabel('Key');
        $this->setTitleFormKey('key');

        // Keys are created by imports only, and the lang files own them.
        $this->disableCreate();
        $this->disableDelete();
        $this->disableBulkDelete();
        $this->disablePublish();
        $this->disableBulkPublish();
        $this->disableEditor();

        // The search box matches keys and values through the repository,
        // instead of Twill's default LIKE on the title column.
        $this->setSearchColumns([]);
        $this->setSearchQuery(fn (Builder $query, string $search) => app(TranslationRepository::class)->search($query, $search));
    }

    protected function getIndexTableColumns(): TableColumns
    {
        $columns = parent::getIndexTableColumns();

        $columns->first(fn (TableColumn $column) => $column->getField() === 'key')?->sortByDefault();

        return $columns;
    }

    protected function additionalIndexTableColumns(): TableColumns
    {
        return TableColumns::make([
            Text::make()->field('value')->title('Value')->renderHtml(),
        ]);
    }
}
