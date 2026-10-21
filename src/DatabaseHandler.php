<?php

namespace BrunosCode\TwillTranslationHandler;

use BrunosCode\TranslationHandler\Collections\TranslationCollection;
use BrunosCode\TranslationHandler\DatabaseHandler as BaseDatabaseHandler;
use BrunosCode\TranslationHandler\Facades\TranslationHandler;
use BrunosCode\TranslationHandler\Interfaces\DatabaseHandlerInterface;
use BrunosCode\TwillTranslationHandler\Twill\Capsules\Translations\Models\TranslationGroup;
use Illuminate\Database\Connection;
use Illuminate\Support\Collection;

class DatabaseHandler extends BaseDatabaseHandler implements DatabaseHandlerInterface
{
    public function put(TranslationCollection $translations, ?string $connection = null): int
    {
        $counter = parent::put($translations, $connection);

        $this->pruneGroups($this->getDB($connection));

        return $counter;
    }

    public function delete(?string $connection = null, bool $hardDelete = false): int
    {
        $counter = parent::delete($connection, $hardDelete);

        $this->pruneGroups($this->getDB($connection));

        return $counter;
    }

    public function handleInsert(Connection $db, TranslationCollection $translations, ?string $filename = null, ?Collection $dbKeys = null): int
    {
        $this->syncGroups($translations);

        return parent::handleInsert($db, $translations, $filename, $dbKeys);
    }

    /**
     * Delete the groups no live key belongs to any more. A group is only a
     * container for a key prefix, so it is removed for good, not soft-deleted.
     */
    protected function pruneGroups(Connection $db): void
    {
        [$keyInGroup, $bindings] = TranslationGroup::keyInGroupCondition(TranslationHandler::getOption('keyDelimiter'));

        $db->table('translation_groups')
            ->whereNotExists(fn ($query) => $query
                ->select($db->raw('1'))
                ->from('translation_keys')
                ->whereRaw($keyInGroup, $bindings)
                ->whereNull('translation_keys.deleted_at'))
            ->delete();
    }

    protected function syncGroups(TranslationCollection $translations): void
    {
        // The service builds this handler with its current options, so the
        // facade returns the delimiter in use, including runtime overrides.
        $delimiter = TranslationHandler::getOption('keyDelimiter');
        $prefixes = collect();

        $translations->pluck('key')->unique()->each(function (string $key) use ($prefixes, $delimiter) {
            $parts = explode($delimiter, $key);

            // Create groups for all prefixes except the full key
            // e.g. "file.key1.key2.key3" -> groups: "file", "file.key1", "file.key1.key2"
            array_pop($parts);

            $prefix = '';
            foreach ($parts as $part) {
                $prefix = $prefix === '' ? $part : $prefix.$delimiter.$part;
                $prefixes[$prefix] = true;
            }
        });

        if ($prefixes->isEmpty()) {
            return;
        }

        $now = now();

        // One statement for all prefixes: existing groups are skipped by the
        // unique index on prefix.
        TranslationGroup::query()->insertOrIgnore(
            $prefixes->keys()->map(fn (string $prefix) => [
                'prefix' => $prefix,
                'published' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all()
        );
    }
}
