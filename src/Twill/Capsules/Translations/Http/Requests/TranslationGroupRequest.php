<?php

namespace BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Http\Requests;

use A17\Twill\Http\Requests\Admin\Request;
use BrunosCode\TranslationHandler\Data\TranslationOptions;
use BrunosCode\TranslationHandler\Facades\TranslationHandler;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\Translation;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Repositories\TranslationGroupRepository;
use Illuminate\Validation\Validator;

class TranslationGroupRequest extends Request
{
    public function rulesForCreate()
    {
        return [];
    }

    public function rulesForUpdate()
    {
        if ($this->boolean('allow_empty')) {
            return [];
        }

        $rules = [];
        foreach ($this->keys() as $key) {
            if (str_starts_with($key, 'trans_') && is_array($this->input($key))) {
                foreach (array_keys($this->input($key)) as $locale) {
                    $rules["{$key}.{$locale}"] = 'required|string';
                }
            }
        }

        return $rules;
    }

    /**
     * Run the checks the core applies on write before anything is saved, so
     * the editor gets a message instead of a save refused halfway.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $locales = config('translation-handler.locales', []);

            foreach ($this->all() as $field => $localeValues) {
                if (! str_starts_with($field, 'trans_') || ! is_array($localeValues)) {
                    continue;
                }

                foreach (array_keys($localeValues) as $locale) {
                    if (! in_array($locale, $locales, true)) {
                        $validator->errors()->add("{$field}.{$locale}", "The locale \"{$locale}\" is not exported to the lang files.");
                    }
                }
            }

            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $repository = app(TranslationGroupRepository::class);
            $translations = $repository->translationsFromFields($this->all());

            if ($translations->isEmpty()) {
                return;
            }

            try {
                // The lang files as the save would leave them.
                TranslationHandler::setOption('fileNames', $repository->fileNamesOf($translations))
                    ->get(TranslationOptions::DB)
                    ->replaceTranslations($translations)
                    ->assertNoParentLeafConflicts(TranslationHandler::getOption('keyDelimiter'));
            } catch (\InvalidArgumentException $e) {
                $validator->errors()->add('prefix', $e->getMessage());
            } finally {
                TranslationHandler::resetOptions();
            }
        });
    }

    public function attributes()
    {
        $attributes = [];

        $ids = [];
        foreach ($this->keys() as $key) {
            if (str_starts_with($key, 'trans_')) {
                $ids[] = (int) substr($key, 6);
            }
        }

        if (! empty($ids)) {
            Translation::whereIn('id', $ids)->pluck('key', 'id')->each(function ($translationKey, $id) use (&$attributes) {
                foreach (array_keys($this->input('trans_'.$id, [])) as $locale) {
                    $attributes["trans_{$id}.{$locale}"] = "{$translationKey} ({$locale})";
                }
            });
        }

        return $attributes;
    }
}
