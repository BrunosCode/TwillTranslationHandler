<?php

namespace BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories;

use A17\Twill\Models\Contracts\TwillModelContract;
use A17\Twill\Repositories\ModuleRepository;
use BrunosCode\TranslationHandler\Collections\TranslationCollection;
use BrunosCode\TranslationHandler\Data\Translation as TranslationData;
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

        $translations = $this->translationsFromFields($fields);

        foreach (array_keys($fields) as $fieldName) {
            if (str_starts_with($fieldName, 'trans_')) {
                unset($fields[$fieldName]);
            }
        }

        unset($fields['repeaters'], $fields['blocks']);

        array_walk_recursive($fields, function (&$v) {
            if (is_null($v)) {
                $v = '';
            }
        });

        $result = parent::update($id, $fields);

        $fileNames = $this->fileNamesOf($translations);

        if ($translations->isNotEmpty()) {
            // TranslationGroupRequest has already run the core checks (locales,
            // key conflicts), so set() is not expected to refuse the values.
            try {
                TranslationHandler::setOption('fileNames', $fileNames)
                    ->set($translations, TranslationOptions::DB, force: true);
            } finally {
                TranslationHandler::resetOptions();
            }

            $this->activate($translations);
        }

        try {
            TranslationHandler::setOption('fileNames', $fileNames ?: [TranslationGroup::findOrFail($id)->getFileName()])
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

    /**
     * The values submitted by the group form (trans_{keyId}[locale]) of the
     * keys that exist, as a core collection. A null value becomes ''.
     */
    public function translationsFromFields(array $fields): TranslationCollection
    {
        $values = [];

        foreach ($fields as $fieldName => $localeValues) {
            if (str_starts_with($fieldName, 'trans_') && is_array($localeValues)) {
                $values[(int) substr($fieldName, 6)] = $localeValues;
            }
        }

        $keys = Translation::query()->whereIn('id', array_keys($values))->pluck('key', 'id');

        $translations = new TranslationCollection;

        foreach ($values as $id => $localeValues) {
            if (! $keys->has($id)) {
                continue;
            }

            foreach ($localeValues as $locale => $value) {
                $translations->push(new TranslationData($keys[$id], (string) $locale, $value ?? ''));
            }
        }

        return $translations;
    }

    /**
     * The lang files the translations belong to: the first segment of each key.
     *
     * @return array<int, string>
     */
    public function fileNamesOf(TranslationCollection $translations): array
    {
        $delimiter = TranslationHandler::getOption('keyDelimiter');

        return $translations
            ->map(fn (TranslationData $translation) => explode($delimiter, $translation->key)[0])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * The core does not know Twill's "active" flag: a value saved from the
     * admin is active again.
     */
    protected function activate(TranslationCollection $translations): void
    {
        $translations
            ->groupBy(fn (TranslationData $translation) => $translation->locale)
            ->each(function ($localeTranslations, string $locale) {
                TranslationTranslation::query()
                    ->where('locale', $locale)
                    ->whereIn('translation_key_id', Translation::query()
                        ->select('id')
                        ->whereIn('key', $localeTranslations->pluck('key')->all()))
                    ->update(['active' => true]);
            });
    }
}
