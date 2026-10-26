<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Actions;

use Domain\Knowledge\Retrieval\Services\ChunkVectorIndex;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds the HNSW indexes on the chunk embeddings in the kind the install
 * chose (ChunkVectorIndex), leaving alone an index already in that shape.
 * The names are fixed. The chunks create migration calls this to build them;
 * the start-up calls it through `chatvia:search:vector-indexes`, so a changed
 * EMBEDDING_INDEX rebuilds them on the next start.
 */
final readonly class ConfigureVectorIndexesAction
{
    /** Every chunk HNSW index: index name => embedding column. */
    public const array INDEXES = [
        'chunks_embedding_hnsw' => 'embedding',
        'chunks_questions_embedding_hnsw' => 'questions_embedding',
    ];

    public function __construct(private ChunkVectorIndex $shape) {}

    /**
     * @return Collection<int, string> the names of the indexes it built
     */
    public function execute(): Collection
    {
        return collect(self::INDEXES)
            ->reject(fn (string $column, string $index): bool => $this->isCurrent($index))
            ->each(function (string $column, string $index): void {
                DB::statement("DROP INDEX IF EXISTS {$index}");
                DB::statement("CREATE INDEX {$index} ON chunks USING hnsw ({$this->shape->expression($column)} {$this->shape->operatorClass()})");
            })
            ->keys()
            ->values();
    }

    /** The column type and size never change after install, so the operator class tells the kinds apart. */
    private function isCurrent(string $index): bool
    {
        $definition = (string) DB::scalar(
            'SELECT indexdef FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
            [$index],
        );

        return Str::contains($definition, " {$this->shape->operatorClass()})");
    }
}
