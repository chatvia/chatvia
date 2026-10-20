<?php

declare(strict_types=1);

use Domain\Knowledge\Retrieval\Actions\EnablePgTextsearchAction;
use Domain\Knowledge\Retrieval\Enums\LexicalDriver;
use Domain\Knowledge\Retrieval\Services\LexicalSearch;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Turning pg_textsearch on creates the extension and rebuilds the keyword
 * indexes as BM25 indexes, or changes nothing when the database cannot load
 * it. DDL runs inside the test transaction, so the database stays as it was.
 */
function eptaAvailable(): bool
{
    return DB::scalar("SELECT count(*) FROM pg_available_extensions WHERE name = 'pg_textsearch'") > 0;
}

function eptaIndexDefinition(string $index): string
{
    return (string) DB::scalar('SELECT indexdef FROM pg_indexes WHERE indexname = ?', [$index]);
}

it('creates the extension and rebuilds the keyword indexes as BM25 indexes', function (): void {
    if (! eptaAvailable()) {
        $this->markTestSkipped('pg_textsearch is not on this database server.');
    }

    config(['chatvia.search.lexical_driver' => 'auto']);

    app(EnablePgTextsearchAction::class)->execute();

    expect(app(LexicalSearch::class)->driver())->toBe(LexicalDriver::PgTextsearch)
        ->and(eptaIndexDefinition('chunks_search_text_lexical'))->toContain('USING bm25');
});

it('throws and keeps the built-in search when the database server lacks it', function (): void {
    if (eptaAvailable()) {
        $this->markTestSkipped('pg_textsearch is on this database server.');
    }

    config(['chatvia.search.lexical_driver' => 'auto']);
    $before = eptaIndexDefinition('chunks_search_text_lexical');

    expect(fn () => app(EnablePgTextsearchAction::class)->execute())->toThrow(QueryException::class)
        ->and(app(LexicalSearch::class)->driver())->toBe(LexicalDriver::Postgres)
        ->and(eptaIndexDefinition('chunks_search_text_lexical'))->toBe($before);
});
