<?php

namespace Domain\Knowledge\Retrieval\Services;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

/**
 * Builds the SQL expression that turns a text into a BM25 sparse vector with
 * the database's tokenizer (pg_tokenizer, `llmlingua2` model). Used wherever a
 * BM25 column is written, and it must match the tokenizer the search query
 * uses.
 */
final class Bm25Tokenizer
{
    public static function expression(string $text, string $model = 'llmlingua2'): Expression
    {
        $pdo = DB::connection()->getPdo();

        return DB::raw('tokenize('.$pdo->quote($text).', '.$pdo->quote($model).')');
    }
}
