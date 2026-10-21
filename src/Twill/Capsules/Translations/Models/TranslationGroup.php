<?php

namespace BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models;

use A17\Twill\Models\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * @property string $prefix
 * @property bool $published
 * @property-read int $translations_count
 * @property-read Collection<int, Translation> $translation_items
 */
class TranslationGroup extends Model
{
    protected $table = 'translation_groups';

    protected $fillable = [
        'prefix',
        'published',
    ];

    /**
     * @return Builder<Translation>
     */
    public function getTranslationsQuery(): Builder
    {
        $delimiter = config('translation-handler.keyDelimiter', '.');

        return Translation::where('key', 'like', $this->prefix.$delimiter.'%')
            ->orderBy('key');
    }

    /**
     * The lang file the group's keys live in: its first prefix segment.
     * A nested group ("auth.messages") is stored in "auth.php", not in a file of its own.
     */
    public function getFileName(): string
    {
        $delimiter = config('translation-handler.keyDelimiter', '.');

        return explode($delimiter, $this->prefix)[0];
    }

    /**
     * Accessor used by HandleRepeaters to iterate translation items.
     *
     * @return Collection<int, Translation>
     */
    public function getTranslationItemsAttribute(): Collection
    {
        return $this->getTranslationsQuery()->with('translations')->get();
    }

    public function getTranslationsCountAttribute(): int
    {
        return $this->getTranslationsQuery()->count();
    }
}
