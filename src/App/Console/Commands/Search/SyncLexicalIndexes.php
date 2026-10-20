<?php

declare(strict_types=1);

namespace App\Console\Commands\Search;

use Domain\Knowledge\Retrieval\Actions\ConfigureLexicalIndexesAction;
use Domain\Knowledge\Retrieval\Services\LexicalSearch;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Rebuilds the lexical-search indexes in the shape of the current driver and
 * text configuration (LexicalSearch). Run it after changing
 * SEARCH_LEXICAL_DRIVER or SEARCH_TEXT_CONFIG — the migrations build the
 * indexes for whatever was set at install time.
 */
#[Description('Rebuild the lexical-search indexes for the configured driver (pg_textsearch or the built-in full-text search).')]
#[Signature('chatvia:search:lexical-indexes')]
class SyncLexicalIndexes extends Command
{
    public function handle(ConfigureLexicalIndexesAction $configure, LexicalSearch $lexical): int
    {
        $this->components->info("Driver: {$lexical->driver()->value}, text configuration: {$lexical->textConfig()}");

        foreach (ConfigureLexicalIndexesAction::INDEXES as $table => $indexes) {
            $this->components->task($table, fn () => $configure->execute($table));
        }

        return self::SUCCESS;
    }
}
