<?php

declare(strict_types=1);

use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * HybridChunkSearch::configureAccuracy() interpolates the vchordrq settings
 * into `SET LOCAL` (SET takes no bind parameters), so the values must be cast
 * to numbers first — a config string can never reach the SQL as text.
 *
 * Probes must match the shape of both vector indexes (none for a flat index,
 * one value for a one-level partitioned index), so they follow the live
 * partitions, read from the catalog and cached; the tests seed that cache.
 */
function vchordAccuracyStatements(string $mode, array $search): array
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

function vchordSeedIndexLists(array $embedding, array $questions): void
{
    Cache::put('vector-index:lists:chunks_embedding_vchordrq_optimized', $embedding, 60);
    Cache::put('vector-index:lists:chunks_questions_embedding_vchordrq', $questions, 60);
}

it('casts epsilon to a number and sets no probes while the indexes are flat', function (): void {
    // The install builds both indexes flat (lists = []), read from the catalog.
    DB::transaction(function (): void {
        app(HybridChunkSearch::class)->configureAccuracy('default', [
            'search' => [
                'probes_percent' => ['default' => 3],
                'epsilon' => ['default' => '1.5 ; SELECT 1'],
            ],
        ]);

        expect((float) DB::selectOne('SHOW vchordrq.epsilon')->{'vchordrq.epsilon'})->toBe(1.5)
            ->and(DB::selectOne('SHOW vchordrq.probes')->{'vchordrq.probes'})->toBe('');
    });
});

it('probes a share of the partitions, enough for the larger index', function (): void {
    vchordSeedIndexLists([2000], [1000]);

    $statements = vchordAccuracyStatements('default', [
        'probes_percent' => ['default' => '3; DROP TABLE chunks'],
        'epsilon' => ['default' => 1.9],
    ]);

    expect($statements)->toBe([
        'SET LOCAL vchordrq.epsilon = 1.9',
        "SET LOCAL vchordrq.probes = '60'",
    ]);
});

it('uses the share of the requested mode and falls back to the default mode', function (): void {
    vchordSeedIndexLists([2000], [2000]);
    $search = [
        'probes_percent' => ['default' => 3, 'high_accuracy' => 6],
        'epsilon' => ['default' => 1.9, 'high_accuracy' => 2.5],
    ];

    expect(vchordAccuracyStatements('high_accuracy', $search))->toBe([
        'SET LOCAL vchordrq.epsilon = 2.5',
        "SET LOCAL vchordrq.probes = '120'",
    ])->and(vchordAccuracyStatements('unknown_mode', $search))->toBe([
        'SET LOCAL vchordrq.epsilon = 1.9',
        "SET LOCAL vchordrq.probes = '60'",
    ]);
});

it('never probes fewer than one partition, nor sets an epsilon outside its range', function (): void {
    vchordSeedIndexLists([1024], [1024]);

    expect(vchordAccuracyStatements('default', [
        'probes_percent' => ['default' => 0],
        'epsilon' => ['default' => 9],
    ]))->toBe([
        'SET LOCAL vchordrq.epsilon = 4',
        "SET LOCAL vchordrq.probes = '1'",
    ]);
});

it('leaves probes to each index when one is flat and the other partitioned', function (): void {
    // One GUC value cannot fit both shapes; each index then uses the default
    // probes stored on it when it was built.
    vchordSeedIndexLists([2000], []);

    expect(vchordAccuracyStatements('default', [
        'probes_percent' => ['default' => 3],
        'epsilon' => ['default' => 1.9],
    ]))->toBe(['SET LOCAL vchordrq.epsilon = 1.9']);
});
