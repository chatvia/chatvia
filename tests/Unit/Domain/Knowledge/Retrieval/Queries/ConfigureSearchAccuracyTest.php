<?php

declare(strict_types=1);

use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

/**
 * HybridChunkSearch::configureAccuracy() interpolates the HNSW settings into
 * `SET LOCAL` (SET takes no bind parameters), so the values must be cast to
 * numbers first — a config string can never reach the SQL as text.
 */
function hnswAccuracyStatements(string $mode, array $search): array
{
    $statements = [];
    DB::listen(function (QueryExecuted $query) use (&$statements): void {
        $statements[] = $query->sql;
    });

    DB::transaction(function () use ($mode, $search): void {
        app(HybridChunkSearch::class)->configureAccuracy($mode, ['search' => $search]);
    });

    return array_values(array_filter($statements, fn (string $sql): bool => str_starts_with($sql, 'SET LOCAL')));
}

it('casts ef_search to a number and turns on iterative scans', function (): void {
    DB::transaction(function (): void {
        app(HybridChunkSearch::class)->configureAccuracy('default', [
            'search' => [
                'ef_search' => ['default' => '150 ; SELECT 1'],
            ],
        ]);

        expect((int) DB::selectOne('SHOW hnsw.ef_search')->{'hnsw.ef_search'})->toBe(150)
            ->and(DB::selectOne('SHOW hnsw.iterative_scan')->{'hnsw.iterative_scan'})->toBe('relaxed_order');
    });
});

it('uses the ef_search of the requested mode and falls back to the default mode', function (): void {
    $search = ['ef_search' => ['default' => 100, 'high_accuracy' => 300]];

    expect(hnswAccuracyStatements('high_accuracy', $search))->toBe([
        'SET LOCAL hnsw.ef_search = 300',
        'SET LOCAL hnsw.iterative_scan = relaxed_order',
    ])->and(hnswAccuracyStatements('unknown_mode', $search))->toBe([
        'SET LOCAL hnsw.ef_search = 100',
        'SET LOCAL hnsw.iterative_scan = relaxed_order',
    ]);
});

it('keeps ef_search inside the range the index accepts', function (): void {
    expect(hnswAccuracyStatements('default', ['ef_search' => ['default' => 0]]))->toBe([
        'SET LOCAL hnsw.ef_search = 1',
        'SET LOCAL hnsw.iterative_scan = relaxed_order',
    ])->and(hnswAccuracyStatements('default', ['ef_search' => ['default' => 100000]]))->toBe([
        'SET LOCAL hnsw.ef_search = 1000',
        'SET LOCAL hnsw.iterative_scan = relaxed_order',
    ]);
});
