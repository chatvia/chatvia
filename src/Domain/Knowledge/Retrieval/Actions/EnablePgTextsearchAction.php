<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Actions;

use Domain\Knowledge\Retrieval\Services\LexicalSearch;
use Domain\Knowledge\Retrieval\Services\PgTextsearchSupport;
use Illuminate\Support\Facades\DB;

/**
 * Creates the pg_textsearch extension on a database that has it loaded, then
 * rebuilds the keyword indexes as BM25 indexes. Without the rebuild, search
 * would switch to pg_textsearch (the `auto` driver follows the extension)
 * while the indexes still had the built-in search's shape. A database that
 * cannot load it, or a user who may not create extensions, throws.
 */
final readonly class EnablePgTextsearchAction
{
    public function __construct(
        private LexicalSearch $lexical,
        private ConfigureLexicalIndexesAction $indexes,
    ) {}

    public function execute(): void
    {
        DB::transaction(function (): void {
            DB::statement('CREATE EXTENSION IF NOT EXISTS '.PgTextsearchSupport::EXTENSION);

            $this->lexical->forget();
            $this->indexes->execute();
        });
    }
}
