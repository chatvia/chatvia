<?php

namespace Domain\Ingestion\Services\Search;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

final class Bm25Tokenizer
{
    public static function expression(string $text, string $model = 'llmlingua2'): Expression
    {
        $pdo = DB::connection()->getPdo();

        return DB::raw('tokenize('.$pdo->quote($text).', '.$pdo->quote($model).')');
    }
}
