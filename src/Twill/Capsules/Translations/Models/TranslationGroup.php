<?php

namespace BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models;

use A17\Twill\Models\Model;
use BrunosCode\TwillTranslationHandler\Support\Like;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

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

        return Like::startsWith(Translation::query(), 'key', $this->prefix.$delimiter)
            ->orderBy('key');
    }

    /**
     * SQL condition matching translation_keys rows to the translation_groups
     * row of the outer query, for correlated subqueries.
     *
     * @return array{0: string, 1: array<int, string>} the SQL and its bindings
     */
    public static function keyInGroupCondition(?string $delimiter = null): array
    {
        $delimiter ??= config('translation-handler.keyDelimiter', '.');
        $prefix = Like::escapeColumn('translation_groups.prefix');

        $sql = DB::connection()->getDriverName() === 'sqlite'
            ? "translation_keys.key LIKE ({$prefix} || ? || '%') ESCAPE '!'"
            : "translation_keys.key LIKE CONCAT({$prefix}, ?, '%') ESCAPE '!'";

        return [$sql, [Like::escape($delimiter)]];
    }

    /**
     * Select translations_count with a subquery, so a listing does not run a
     * COUNT per group through the accessor.
     *
     * @param  Builder<TranslationGroup>  $query
     * @return Builder<TranslationGroup>
     */
    public static function selectTranslationsCount(Builder $query): Builder
    {
        [$condition, $bindings] = self::keyInGroupCondition();

        if ($query->getQuery()->columns === null) {
            $query->select('translation_groups.*');
        }

        return $query->selectSub(
            DB::table('translation_keys')
                ->selectRaw('COUNT(*)')
                ->whereRaw($condition, $bindings)
                ->whereNull('translation_keys.deleted_at'),
            'translations_count'
        );
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
        if (array_key_exists('translations_count', $this->attributes)) {
            return (int) $this->attributes['translations_count'];
        }

        return $this->getTranslationsQuery()->count();
    }
}
