<?php

namespace BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories;

use A17\Twill\Models\Contracts\TwillModelContract;
use A17\Twill\Repositories\ModuleRepository;
use BrunosCode\TranslationHandler\Data\TranslationOptions;
use BrunosCode\TranslationHandler\Facades\TranslationHandler;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationTranslation;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TranslationGroupRepository extends ModuleRepository
{
    public function __construct(TranslationGroup $model)
    {
        $this->model = $model;
    }

    public function filter(Builder $query, array $scopes = []): Builder
    {
        if (! empty($scopes['search'])) {
            $search = $scopes['search'];
            $delimiter = config('translation-handler.keyDelimiter', '.');
            $driver = DB::connection()->getDriverName();

            $concatKeyExpr = $driver === 'sqlite'
                ? "translation_keys.key LIKE (translation_groups.prefix || ? || '%')"
                : "translation_keys.key LIKE CONCAT(translation_groups.prefix, ?, '%')";

            $query->where(function ($q) use ($search, $delimiter, $concatKeyExpr) {
                $q->where('prefix', 'like', "%{$search}%")
                    ->orWhereExists(function ($sub) use ($search, $delimiter, $concatKeyExpr) {
                        $sub->select(DB::raw(1))
                            ->from('translation_keys')
                            ->whereRaw($concatKeyExpr, [$delimiter])
                            ->where('translation_keys.key', 'like', "%{$search}%");
                    })
                    ->orWhereExists(function ($sub) use ($search, $delimiter, $concatKeyExpr) {
                        $sub->select(DB::raw(1))
                            ->from('translation_values')
                            ->join('translation_keys', 'translation_keys.id', '=', 'translation_values.translation_key_id')
                            ->whereRaw($concatKeyExpr, [$delimiter])
                            ->where('translation_values.value', 'like', "%{$search}%");
                    });
            });
            unset($scopes['search']);
        }

        return parent::filter($query, $scopes);
    }

    public function getFormFields(TwillModelContract $object): array
    {
        /** @var TranslationGroup $object */
        $fields = parent::getFormFields($object);

        foreach ($object->translation_items as $translation) {
            /** @var TranslationTranslation $tv */
            foreach ($translation->translations as $tv) {
                $fields['translations']['trans_'.$translation->id][$tv->locale] = $tv->value ?? '';
            }
        }

        return $fields;
    }

    public function update(int|string $id, array $fields): TwillModelContract
    {
        unset($fields['allow_empty']);

        foreach ($fields as $fieldName => $localeValues) {
            if (! str_starts_with($fieldName, 'trans_') || ! is_array($localeValues)) {
                continue;
            }

            $translationId = (int) substr($fieldName, 6);
            $translation = Translation::find($translationId);

            if (! $translation) {
                continue;
            }

            foreach ($localeValues as $locale => $value) {
                $translation->translations()->updateOrCreate(
                    ['locale' => $locale],
                    ['value' => $value ?? '', 'active' => true]
                );
            }

            unset($fields[$fieldName]);
        }

        unset($fields['repeaters'], $fields['blocks']);

        array_walk_recursive($fields, function (&$v) {
            if (is_null($v)) {
                $v = '';
            }
        });

        $result = parent::update($id, $fields);

        $group = TranslationGroup::findOrFail($id);

        try {
            TranslationHandler::setOption('fileNames', [$group->getFileName()])
                ->export(TranslationOptions::DB, TranslationOptions::PHP, true);
        } catch (\Throwable $e) {
            // The values are saved: a failed export must not lose them, but it
            // leaves the lang files stale, so it has to reach the logs.
            report($e);
        } finally {
            TranslationHandler::resetOptions();
        }

        return $result;
    }
}
