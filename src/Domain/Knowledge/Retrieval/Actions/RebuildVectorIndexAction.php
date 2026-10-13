<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Actions;

use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Retrieval\Data\VectorIndexRebuildData;
use Domain\Knowledge\Retrieval\Data\VectorIndexStateData;
use Domain\Knowledge\Retrieval\Enums\VectorIndexRebuild;
use Domain\Knowledge\Retrieval\Services\VectorIndexLayout;
use Domain\Knowledge\Retrieval\Services\VectorIndexSizing;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Sizes one vchordrq vector index on `chunks` for the rows it holds now, and
 * rebuilds it when that changes.
 *
 * vchordrq trains its partitions (k-means) when the index is built, from the
 * rows that exist then. The install builds a flat index (no partitions), which
 * suits an empty or small table; once the table passes 100k rows the index is
 * rebuilt with about 4·√N partitions ({@see VectorIndexSizing}), and again each
 * time the rows double, so the partitions follow the data. A flat index needs
 * no training, so it is never rebuilt while it stays flat.
 *
 * The rebuild runs side by side: a new index is built CONCURRENTLY next to the
 * old one, then swapped in, so search and ingestion keep running. The row count
 * it was built for is kept in the index comment, and the default search probes
 * are stored on the index itself (a VectorChord fallback parameter), so every
 * query probes a share that matches its partitions.
 */
class RebuildVectorIndexAction
{
    /** The vchordrq indexes on chunks, and the column each one covers. */
    public const array INDEXES = [
        'chunks_embedding_vchordrq_optimized' => 'embedding',
        'chunks_questions_embedding_vchordrq' => 'questions_embedding',
    ];

    public function __construct(
        private readonly VectorIndexSizing $sizing,
        private readonly VectorIndexLayout $layout,
        private readonly EmbeddingStorage $storage,
    ) {}

    public function execute(string $index, VectorIndexRebuild $when = VectorIndexRebuild::WhenGrown): VectorIndexRebuildData
    {
        // Index and column names are interpolated (DDL takes no bindings), so
        // only the known indexes are accepted.
        $column = self::INDEXES[$index] ?? throw new InvalidArgumentException("Unknown vector index: {$index}");

        $rows = (int) DB::scalar("SELECT count(*) FROM chunks WHERE {$column} IS NOT NULL");
        $lists = $this->sizing->lists($rows);
        $current = $this->layout->read($index);

        if ($when === VectorIndexRebuild::WhenGrown && $current instanceof VectorIndexStateData && ! $this->needsRebuild($current, $lists, $rows)) {
            return new VectorIndexRebuildData(index: $index, rows: $rows, lists: $current->lists, rebuilt: false);
        }

        $this->rebuild($index, $column, $lists, $rows);
        $this->layout->forget($index);

        return new VectorIndexRebuildData(index: $index, rows: $rows, lists: $lists, rebuilt: true);
    }

    /**
     * @param  list<int>  $lists  the partitions the current row count calls for
     */
    private function needsRebuild(VectorIndexStateData $current, array $lists, int $rows): bool
    {
        // Flat now and flat still: there are no trained partitions to go stale.
        if ($current->lists === [] && $lists === []) {
            return false;
        }

        // Crossing the flat/partitioned line in either direction.
        if (($current->lists === []) !== ($lists === [])) {
            return true;
        }

        // Partitioned: retrain once the rows doubled since the build (or when
        // the build size is unknown, e.g. an index this action did not build).
        return $current->builtForRows === null || $rows >= 2 * $current->builtForRows;
    }

    /**
     * Build `{index}_next` beside the live index, then swap it in. A failed
     * concurrent build leaves an invalid `_next` index behind; the next run
     * drops it first.
     *
     * @param  list<int>  $lists
     */
    private function rebuild(string $index, string $column, array $lists, int $rows): void
    {
        $next = $index.'_next';
        $operatorClass = $this->storage->type()->operatorClass();
        $options = $this->sizing->options(
            $lists,
            $this->storage->dimensions(),
            (int) config('chatvia.vector.build_threads', 4),
            (int) config('chatvia.vector.build_memory_mb', 2048),
        );

        $parameters = "options = '{$options}'";

        if ($lists !== []) {
            // The default-mode probes travel with the index, so a query that
            // sets none (or cannot: two indexes of different shapes in one
            // statement) still probes a share that matches these partitions.
            $percent = (float) config('chatvia.vector.search.probes_percent.default', 3);
            $parameters .= ", probes = '".$this->sizing->probes($lists, $percent)."'";
        }

        DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$next}");
        DB::statement("CREATE INDEX CONCURRENTLY {$next} ON chunks USING vchordrq ({$column} {$operatorClass}) WITH ({$parameters})");
        DB::statement("COMMENT ON INDEX {$next} IS 'rows={$rows}'");
        DB::statement("DROP INDEX CONCURRENTLY IF EXISTS {$index}");
        DB::statement("ALTER INDEX {$next} RENAME TO {$index}");
    }
}
