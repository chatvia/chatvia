<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Services;

use Domain\Knowledge\Retrieval\Enums\LexicalDriver;
use Domain\Knowledge\Retrieval\Exceptions\InvalidLexicalSearchException;
use Illuminate\Support\Facades\DB;

/**
 * The one place that knows how the lexical (keyword) channels of hybrid search
 * are written in SQL. Two drivers (`chatvia.search.lexical_driver`, default
 * auto-detected): pg_textsearch ranks with true BM25 through its `bm25`
 * indexes, and `postgres` — for managed databases that cannot preload
 * extensions — ranks with the built-in full-text search over GIN indexes
 * (ConfigureLexicalIndexesAction keeps the indexes in the driver's shape).
 *
 * Every score this service builds is ascending (lower is better: BM25 scores
 * come out negated, ts_rank is negated to match) and 0 when the row shares no
 * term with the query — wrap it in `NULLIF(…, 0)` to drop non-matches. Both
 * drivers tokenize with one Postgres text search configuration
 * (`chatvia.search.text_config`, default `simple`: language-agnostic, no
 * stemming — content and questions arrive in any language).
 */
final class LexicalSearch
{
    private ?LexicalDriver $resolved = null;

    public function driver(): LexicalDriver
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $value = config('chatvia.search.lexical_driver', 'auto');
        $configured = is_string($value) ? strtolower(trim($value)) : '';

        if ($configured !== '' && $configured !== 'auto') {
            return $this->resolved = LexicalDriver::tryFrom($configured)
                ?? throw InvalidLexicalSearchException::driver($value);
        }

        return $this->resolved = $this->detect();
    }

    /** The text search configuration both drivers tokenize with. */
    public function textConfig(): string
    {
        $value = config('chatvia.search.text_config', 'simple');

        if (! is_string($value) || ! preg_match('/^[a-z_][a-z0-9_]*(\.[a-z_][a-z0-9_]*)?$/', $value)) {
            throw InvalidLexicalSearchException::textConfig($value);
        }

        return $value;
    }

    /**
     * What to bind for the query text of {@see queryExpression()} and
     * {@see matchCondition()}. The built-in search ANDs the words of a query
     * (websearch_to_tsquery), which would demand every word of a natural
     * question; keyword search ranks on any shared term (as BM25 does), so
     * the words are joined with OR.
     */
    public function queryText(string $query): string
    {
        return match ($this->driver()) {
            LexicalDriver::PgTextsearch => $query,
            LexicalDriver::Postgres => implode(' OR ', preg_split('/\s+/', trim($query), flags: PREG_SPLIT_NO_EMPTY) ?: []),
        };
    }

    /**
     * The parsed query, computed once (e.g. in a CTE) and scored against many
     * rows with {@see score()}. Binds one `?`: the query text, through
     * {@see queryText()}.
     */
    public function queryExpression(string $index): string
    {
        return match ($this->driver()) {
            LexicalDriver::PgTextsearch => "to_bm25query(?, '{$index}')",
            LexicalDriver::Postgres => "websearch_to_tsquery('{$this->textConfig()}', ?)",
        };
    }

    /**
     * A row's score against a {@see queryExpression()} reference. Binds
     * nothing. The built-in search needs the explicit match guard: ts_rank
     * gives a non-matching row 1e-20, not 0, which would tie every row into
     * the channel instead of leaving it out.
     */
    public function score(string $column, string $queryReference): string
    {
        $config = $this->textConfig();

        return match ($this->driver()) {
            LexicalDriver::PgTextsearch => "{$column} <@> {$queryReference}",
            LexicalDriver::Postgres => "CASE WHEN to_tsvector('{$config}', {$column}) @@ {$queryReference}"
                ." THEN -ts_rank(to_tsvector('{$config}', {$column}), {$queryReference}) END",
        };
    }

    /** {@see score()} with the query inline. Binds one `?`: the query text. */
    public function distance(string $column, string $index): string
    {
        return $this->score($column, $this->queryExpression($index));
    }

    /**
     * An extra WHERE condition that lets the driver's index find the matching
     * rows (binds one `?`, the query text), or null when ordering by the score
     * already scans the index (pg_textsearch).
     */
    public function matchCondition(string $column): ?string
    {
        return match ($this->driver()) {
            LexicalDriver::PgTextsearch => null,
            LexicalDriver::Postgres => "to_tsvector('{$this->textConfig()}', {$column}) @@ websearch_to_tsquery('{$this->textConfig()}', ?)",
        };
    }

    private function detect(): LexicalDriver
    {
        $installed = (bool) DB::scalar(
            "SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'pg_textsearch')",
        );

        return $installed ? LexicalDriver::PgTextsearch : LexicalDriver::Postgres;
    }
}
