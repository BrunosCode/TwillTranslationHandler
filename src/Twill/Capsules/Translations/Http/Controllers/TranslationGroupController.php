<?php

namespace BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Http\Controllers;

use A17\Twill\Http\Controllers\Admin\ModuleController;
use A17\Twill\Models\Contracts\TwillModelContract;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use Illuminate\Support\Collection;

class TranslationGroupController extends ModuleController
{
    protected $moduleName = 'translationGroups';

    protected $titleColumnKey = 'prefix';

    protected $titleFormKey = 'prefix';

    protected $disableEditor = true;

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

    protected $indexColumns = [
        'prefix' => [
            'title' => 'Prefix',
            'field' => 'prefix',
            'sort' => true,
        ],
        'translations_count' => [
            'title' => 'Translations',
            'field' => 'translations_count',
        ],
    ];

    protected $defaultOrders = ['prefix' => 'asc'];

    protected $defaultFilters = ['search' => 'search'];

    protected $indexOptions = [
        'create' => false,
        'publish' => false,
        'bulkPublish' => false,
        'feature' => false,
        'bulkFeature' => false,
        'delete' => false,
        'bulkDelete' => false,
    ];

    protected function indexItemData(TwillModelContract $item): array
    {
        /** @var TranslationGroup $item */
        return [
            'translations_count' => $item->translations_count,
        ];
    }
}
