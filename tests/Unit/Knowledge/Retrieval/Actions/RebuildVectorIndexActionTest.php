<?php

use Domain\Knowledge\Retrieval\Actions\RebuildVectorIndexAction;
use Domain\Knowledge\Retrieval\Enums\VectorIndexRebuild;
use Illuminate\Support\Facades\DB;

/**
 * The action counts the indexed rows, reads the index's live layout and
 * rebuilds it only when the layout must change or the rows doubled. Its DDL
 * (CREATE INDEX CONCURRENTLY) cannot run inside the test transaction, so the
 * database is faked: the count, the catalog row, and the statements sent.
 */

/**
 * @param  list<string>  $statements  filled with every DDL statement sent
 */
function fakeVectorIndexDatabase(int $rows, ?string $options, ?string $comment, array &$statements): void
{
    DB::shouldReceive('scalar')->andReturn($rows);
    DB::shouldReceive('selectOne')->andReturn($options === null ? null : (object) [
        'options' => json_encode(["options={$options}"]),
        'comment' => $comment,
    ]);
    DB::shouldReceive('statement')->andReturnUsing(function (string $sql) use (&$statements): bool {
        $statements[] = $sql;

        return true;
    });
}

function flatVectorIndexOptions(): string
{
    return "residual_quantization = true\n[build.internal]\nlists = []\n";
}

it('keeps a flat index flat while the table is small', function (): void {
    $statements = [];
    fakeVectorIndexDatabase(99_999, flatVectorIndexOptions(), null, $statements);

    $result = app(RebuildVectorIndexAction::class)->execute('chunks_embedding_vchordrq_optimized');

    expect($result->rebuilt)->toBeFalse()
        ->and($result->rows)->toBe(99_999)
        ->and($result->lists)->toBe([])
        ->and($statements)->toBe([]);
});

it('partitions a flat index once the table passes 100k rows, side by side', function (): void {
    $statements = [];
    fakeVectorIndexDatabase(150_000, flatVectorIndexOptions(), null, $statements);

    $result = app(RebuildVectorIndexAction::class)->execute('chunks_embedding_vchordrq_optimized');

    // round(4 * sqrt(150000)) = 1549 partitions; 3% of them probed by default.
    expect($result->rebuilt)->toBeTrue()
        ->and($result->lists)->toBe([1549])
        ->and($statements)->toHaveCount(5)
        ->and($statements[0])->toBe('DROP INDEX CONCURRENTLY IF EXISTS chunks_embedding_vchordrq_optimized_next')
        ->and($statements[1])->toStartWith('CREATE INDEX CONCURRENTLY chunks_embedding_vchordrq_optimized_next ON chunks USING vchordrq (embedding halfvec_l2_ops)')
        ->and($statements[1])->toContain('lists = [1549]')
        ->and($statements[1])->toContain("probes = '47'")
        ->and($statements[2])->toBe("COMMENT ON INDEX chunks_embedding_vchordrq_optimized_next IS 'rows=150000'")
        ->and($statements[3])->toBe('DROP INDEX CONCURRENTLY IF EXISTS chunks_embedding_vchordrq_optimized')
        ->and($statements[4])->toBe('ALTER INDEX chunks_embedding_vchordrq_optimized_next RENAME TO chunks_embedding_vchordrq_optimized');
});

it('keeps a partitioned index until the rows double since its build', function (int $rows, bool $rebuilt): void {
    $statements = [];
    fakeVectorIndexDatabase($rows, "residual_quantization = true\n[build.internal]\nlists = [1549]\n", 'rows=150000', $statements);

    $result = app(RebuildVectorIndexAction::class)->execute('chunks_questions_embedding_vchordrq');

    expect($result->rebuilt)->toBe($rebuilt)
        ->and($statements === [])->toBe(! $rebuilt);

    if ($rebuilt) {
        expect($statements[1])->toContain('(questions_embedding halfvec_l2_ops)')
            ->and($statements[1])->toContain('lists = [2191]');
    }
})->with([
    'grown 1.67x' => [250_000, false],
    'doubled' => [300_000, true],
]);

it('rebuilds a partitioned index whose build size is unknown', function (): void {
    // E.g. an index built by hand, or before the row count was recorded.
    $statements = [];
    fakeVectorIndexDatabase(200_000, "residual_quantization = true\n[build.internal]\nlists = [4096]\n", null, $statements);

    expect(app(RebuildVectorIndexAction::class)->execute('chunks_embedding_vchordrq_optimized')->lists)->toBe([1789])
        ->and($statements)->toHaveCount(5);
});

it('turns a partitioned index flat again when the table shrinks below 100k rows', function (): void {
    $statements = [];
    fakeVectorIndexDatabase(40_000, "residual_quantization = true\n[build.internal]\nlists = [1549]\n", 'rows=150000', $statements);

    $result = app(RebuildVectorIndexAction::class)->execute('chunks_embedding_vchordrq_optimized');

    expect($result->lists)->toBe([])
        ->and($statements[1])->toContain('lists = []')
        ->and($statements[1])->not->toContain('probes');
});

it('rebuilds a missing index, and any index on --force', function (bool $exists, VectorIndexRebuild $when): void {
    $statements = [];
    fakeVectorIndexDatabase(5_000, $exists ? flatVectorIndexOptions() : null, null, $statements);

    $result = app(RebuildVectorIndexAction::class)->execute('chunks_embedding_vchordrq_optimized', $when);

    expect($result->rebuilt)->toBeTrue()
        ->and($statements)->toHaveCount(5);
})->with([
    'missing' => [false, VectorIndexRebuild::WhenGrown],
    'fits, forced' => [true, VectorIndexRebuild::Always],
]);

it('after a re-index, rebuilds a partitioned index that fits its rows and keeps a flat one', function (string $options, int $rows, bool $rebuilt): void {
    $statements = [];
    fakeVectorIndexDatabase($rows, $options, 'rows=150000', $statements);

    $result = app(RebuildVectorIndexAction::class)->execute('chunks_embedding_vchordrq_optimized', VectorIndexRebuild::WhenPartitioned);

    expect($result->rebuilt)->toBe($rebuilt)
        ->and($statements)->toHaveCount($rebuilt ? 5 : 0);
})->with([
    'partitioned, same rows' => ["residual_quantization = true\n[build.internal]\nlists = [1549]\n", 150_000, true],
    'flat, small table' => ["residual_quantization = true\n[build.internal]\nlists = []\n", 5_000, false],
]);

it('refuses any other index name', function (): void {
    DB::shouldReceive('scalar')->never();
    DB::shouldReceive('statement')->never();

    expect(fn () => app(RebuildVectorIndexAction::class)->execute('chunks_pkey; DROP TABLE chunks'))
        ->toThrow(InvalidArgumentException::class);
});
