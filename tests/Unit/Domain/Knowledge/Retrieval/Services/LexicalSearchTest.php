<?php

declare(strict_types=1);

use Domain\Knowledge\Retrieval\Enums\LexicalDriver;
use Domain\Knowledge\Retrieval\Exceptions\InvalidLexicalSearchException;
use Domain\Knowledge\Retrieval\Services\LexicalSearch;
use Illuminate\Support\Facades\DB;

/**
 * The driver resolution and the SQL fragments of the two lexical drivers. The
 * fragments are interpolated into every hybrid-search query, so their exact
 * shape (and the refusal of non-identifier text configurations) is what keeps
 * config values out of the SQL as text.
 */
function lxsSearch(string $driver, string $config = 'simple'): LexicalSearch
{
    config(['chatvia.search.lexical_driver' => $driver, 'chatvia.search.text_config' => $config]);

    return new LexicalSearch;
}

it('builds pg_textsearch SQL: a bm25 query, the <@> score, no extra condition', function (): void {
    $lexical = lxsSearch('pg_textsearch');

    expect($lexical->driver())->toBe(LexicalDriver::PgTextsearch)
        ->and($lexical->queryExpression('chunks_search_text_lexical'))->toBe("to_bm25query(?, 'chunks_search_text_lexical')")
        ->and($lexical->score('c.search_text', 'q.terms'))->toBe('c.search_text <@> q.terms')
        ->and($lexical->distance('c.search_text', 'chunks_search_text_lexical'))
        ->toBe("c.search_text <@> to_bm25query(?, 'chunks_search_text_lexical')")
        ->and($lexical->matchCondition('c.search_text'))->toBeNull();
});

it('builds built-in full-text SQL: a tsquery, a negated ts_rank, an @@ condition', function (): void {
    $lexical = lxsSearch('postgres');

    expect($lexical->driver())->toBe(LexicalDriver::Postgres)
        ->and($lexical->queryExpression('chunks_search_text_lexical'))->toBe("websearch_to_tsquery('simple', ?)")
        ->and($lexical->score('c.search_text', 'q.terms'))
        ->toBe("CASE WHEN to_tsvector('simple', c.search_text) @@ q.terms THEN -ts_rank(to_tsvector('simple', c.search_text), q.terms) END")
        ->and($lexical->matchCondition('c.search_text'))
        ->toBe("to_tsvector('simple', c.search_text) @@ websearch_to_tsquery('simple', ?)");
});

it('joins the query words with OR for the built-in search, as BM25 ranks on any shared term', function (): void {
    expect(lxsSearch('postgres')->queryText("What is  the\nrefund policy?"))->toBe('What OR is OR the OR refund OR policy?')
        ->and(lxsSearch('pg_textsearch')->queryText('What is the refund policy?'))->toBe('What is the refund policy?');
});

it('tokenizes with the configured text search configuration', function (): void {
    $lexical = lxsSearch('postgres', 'english');

    expect($lexical->queryExpression('x'))->toBe("websearch_to_tsquery('english', ?)");
});

it('resolves auto from the installed extensions, once', function (): void {
    $installed = (bool) DB::scalar("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_textsearch')");

    $lexical = lxsSearch('auto');

    expect($lexical->driver())->toBe($installed ? LexicalDriver::PgTextsearch : LexicalDriver::Postgres)
        // Memoized: a config change after the first resolution changes nothing.
        ->and(tap($lexical, fn () => config(['chatvia.search.lexical_driver' => 'postgres']))->driver())
        ->toBe($installed ? LexicalDriver::PgTextsearch : LexicalDriver::Postgres);
});

it('refuses an unknown driver', function (): void {
    lxsSearch('elastic')->driver();
})->throws(InvalidLexicalSearchException::class);

it('refuses a text configuration that is not a plain identifier', function (): void {
    lxsSearch('postgres', "simple'; DROP TABLE chunks; --")->textConfig();
})->throws(InvalidLexicalSearchException::class);
