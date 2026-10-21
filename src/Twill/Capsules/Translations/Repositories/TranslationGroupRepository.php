<?php

namespace BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories;

use A17\Twill\Models\Contracts\TwillModelContract;
use A17\Twill\Repositories\ModuleRepository;
use BrunosCode\TranslationHandler\Data\TranslationOptions;
use BrunosCode\TranslationHandler\Facades\TranslationHandler;
use BrunosCode\TwillTranslationHandler\Support\Like;
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
        // Listing hook: every listing query passes through filter().
        TranslationGroup::selectTranslationsCount($query);

        if (! empty($scopes['search'])) {
            $this->search($query, $scopes['search']);
            unset($scopes['search']);
        }

        return parent::filter($query, $scopes);
    }

    /**
     * Match groups whose prefix, keys or values contain the term, used by the
     * listing search box.
     *
     * @param  Builder<TranslationGroup>  $query
     * @return Builder<TranslationGroup>
     */
    public function search(Builder $query, string $search): Builder
    {
        [$keyInGroup, $bindings] = TranslationGroup::keyInGroupCondition();

        return $query->where(function ($q) use ($search, $keyInGroup, $bindings) {
            Like::contains($q, 'prefix', $search)
                ->orWhereExists(function ($sub) use ($search, $keyInGroup, $bindings) {
                    $sub->select(DB::raw(1))
                        ->from('translation_keys')
                        ->whereRaw($keyInGroup, $bindings);
                    Like::contains($sub, 'translation_keys.key', $search);
                })
                ->orWhereExists(function ($sub) use ($search, $keyInGroup, $bindings) {
                    $sub->select(DB::raw(1))
                        ->from('translation_values')
                        ->join('translation_keys', 'translation_keys.id', '=', 'translation_values.translation_key_id')
                        ->whereRaw($keyInGroup, $bindings);
                    Like::contains($sub, 'translation_values.value', $search);
                });
        });
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
