<?php

declare(strict_types=1);

use Domain\Knowledge\Retrieval\Enums\LexicalDriver;
use Domain\Knowledge\Retrieval\Exceptions\InvalidLexicalSearchException;
use Domain\Knowledge\Retrieval\Services\LexicalSearch;

/**
 * The SQL fragments of the two lexical drivers. The fragments are
 * interpolated into every hybrid-search query, so their exact shape (and the
 * refusal of an unknown driver or a non-identifier text configuration) is
 * what keeps config values out of the SQL as text.
 */
function lxsSearch(string $driver, string $config = 'simple'): LexicalSearch
{
    config(['chatvia.search.lexical_driver' => $driver, 'chatvia.search.text_config' => $config]);

    return new LexicalSearch;
}

it('builds the SQL fragments of each driver', function (string $driver, string $config, LexicalDriver $resolved, string $query, string $score, ?string $match): void {
    $lexical = lxsSearch($driver, $config);

    expect($lexical->driver())->toBe($resolved)
        ->and($lexical->queryExpression('chunks_search_text_lexical'))->toBe($query)
        ->and($lexical->score('c.search_text', 'q.terms'))->toBe($score)
        ->and($lexical->matchCondition('c.search_text'))->toBe($match);
})->with([
    'pg_textsearch: a bm25 query, the <@> score, no extra condition' => ['pg_textsearch', 'simple', LexicalDriver::PgTextsearch,
        "to_bm25query(?, 'chunks_search_text_lexical')",
        'c.search_text <@> q.terms',
        null,
    ],
    'built-in: a tsquery in the configured text search, a negated ts_rank, an @@ condition' => ['postgres', 'english', LexicalDriver::Postgres,
        "websearch_to_tsquery('english', ?)",
        "CASE WHEN to_tsvector('english', c.search_text) @@ q.terms THEN -ts_rank(to_tsvector('english', c.search_text), q.terms) END",
        "to_tsvector('english', c.search_text) @@ websearch_to_tsquery('english', ?)",
    ],
]);

it('refuses an unknown driver and a text configuration that is not a plain identifier', function (): void {
    expect(fn () => lxsSearch('elastic')->driver())->toThrow(InvalidLexicalSearchException::class)
        ->and(fn () => lxsSearch('postgres', "simple'; DROP TABLE chunks; --")->textConfig())->toThrow(InvalidLexicalSearchException::class);
});
