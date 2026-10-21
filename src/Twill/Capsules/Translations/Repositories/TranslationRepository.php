<?php

namespace BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories;

use A17\Twill\Models\Contracts\TwillModelContract;
use A17\Twill\Repositories\Behaviors\HandleTranslations;
use A17\Twill\Repositories\ModuleRepository;
use BrunosCode\TranslationHandler\Data\TranslationOptions;
use BrunosCode\TranslationHandler\Facades\TranslationHandler;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use Illuminate\Database\Eloquent\Builder;

class TranslationRepository extends ModuleRepository
{
    use HandleTranslations;

    public function __construct(Translation $model)
    {
        $this->model = $model;
    }

    public function filter(Builder $query, array $scopes = []): Builder
    {
        if (! empty($scopes['search'])) {
            $search = $scopes['search'];
            $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhereHas('translations', function ($q) use ($search) {
                        $q->where('value', 'like', "%{$search}%");
                    });
            });
            unset($scopes['search']);
        }

        return parent::filter($query, $scopes);
    }

    public function update(int|string $id, array $fields): TwillModelContract
    {
        unset($fields['allow_empty']);

        if (isset($fields['translations'])) {
            array_walk_recursive($fields['translations'], function (&$v) {
                if (is_null($v)) {
                    $v = '';
                }
            });
        }

        return parent::update($id, $fields);
    }

    public function afterSave(TwillModelContract $object, array $fields): void
    {
        /** @var Translation $object */
        try {
            $delimiter = config('translation-handler.keyDelimiter', '.');
            $prefix = explode($delimiter, $object->key)[0];

            TranslationHandler::setOption('fileNames', [$prefix])
                ->export(TranslationOptions::DB, TranslationOptions::PHP, true);
        } catch (\Throwable $e) {
            // The value is saved: a failed export must not lose it, but it
            // leaves the lang files stale, so it has to reach the logs.
            report($e);
        } finally {
            TranslationHandler::resetOptions();
        }

        parent::afterSave($object, $fields);
    }
}
