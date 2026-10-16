<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Enums;

/**
 * How the lexical (keyword) half of hybrid search is served: pg_textsearch
 * gives true BM25 ranking but must be preloaded into Postgres, so managed
 * databases fall back to the built-in full-text search. The values are what
 * `SEARCH_LEXICAL_DRIVER` accepts besides `auto`.
 */
enum LexicalDriver: string
{
    case PgTextsearch = 'pg_textsearch';
    case Postgres = 'postgres';
}
