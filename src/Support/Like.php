<?php

namespace BrunosCode\TwillTranslationHandler\Support;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * LIKE matching with the wildcards (% and _) of user input and key prefixes
 * taken literally. `ESCAPE '!'` is portable across SQLite, MySQL and
 * PostgreSQL, as in the core DatabaseHandler.
 */
final class Like
{
    public static function escape(string $value): string
    {
        return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value);
    }

    /**
     * SQL expression escaping a column value the same way as escape().
     */
    public static function escapeColumn(string $column): string
    {
        return "REPLACE(REPLACE(REPLACE({$column}, '!', '!!'), '%', '!%'), '_', '!_')";
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function contains(Builder $query, string $column, string $value, string $boolean = 'and'): Builder
    {
        return self::where($query, $column, '%'.self::escape($value).'%', $boolean);
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    public static function startsWith(Builder $query, string $column, string $value, string $boolean = 'and'): Builder
    {
        return self::where($query, $column, self::escape($value).'%', $boolean);
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    private static function where(Builder $query, string $column, string $pattern, string $boolean): Builder
    {
        $query->whereRaw($query->getGrammar()->wrap($column)." LIKE ? ESCAPE '!'", [$pattern], $boolean);

        return $query;
    }
}
