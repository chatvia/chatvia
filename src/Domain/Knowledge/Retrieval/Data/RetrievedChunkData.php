<?php

namespace Domain\Knowledge\Retrieval\Data;

use Spatie\LaravelData\Data;
use stdClass;

/**
 * One chunk found by the knowledge-base search, with how it was found and how
 * much to trust it.
 *
 * Property names follow the `chunks` columns (snake_case) because callers read
 * the search results by those names. `metadata` stays the JSON text the column
 * holds; {@see self::images()} decodes the part callers need.
 */
final class RetrievedChunkData extends Data
{
    public function __construct(
        public int $id,
        public ?string $external_id,
        public string $chunk,
        public int $chunk_index,
        /** The source's morph alias (for example `source_file`). */
        public string $chunkable_type,
        public int $chunkable_id,
        /** The chunk's metadata as stored (JSON text), or null. */
        public ?string $metadata,
        /** The channels that found it, joined with `+` (for example `vector+bm25`). */
        public string $search_type,
        /** The fused Reciprocal Rank Fusion score; small positive numbers, higher is better. */
        public float $score,
        /** Display confidence from the final rank (0.99 for the best, down to 0.50). */
        public ?float $confidence_score = null,
        /** The reranker's relevance score, when a reranker ordered the results. */
        public ?float $rerank_score = null,
    ) {}

    /**
     * Build from one row of the hybrid search SQL.
     */
    public static function fromRow(stdClass $row): self
    {
        return new self(
            id: (int) $row->id,
            external_id: $row->external_id,
            chunk: (string) $row->chunk,
            chunk_index: (int) $row->chunk_index,
            chunkable_type: (string) $row->chunkable_type,
            chunkable_id: (int) $row->chunkable_id,
            metadata: is_string($row->metadata) ? $row->metadata : null,
            search_type: (string) $row->search_type,
            score: (float) $row->score,
        );
    }

    /**
     * The described images of the page: storage path => description.
     *
     * @return array<string, string>
     */
    public function images(): array
    {
        $metadata = json_decode((string) $this->metadata, true);

        return is_array($metadata['images'] ?? null) ? $metadata['images'] : [];
    }

    public function withRerankScore(float $score): self
    {
        $copy = clone $this;
        $copy->rerank_score = $score;

        return $copy;
    }

    /**
     * The result as the model sees it: XML/HTML stripped from the text and the
     * display confidence for its final rank.
     */
    public function scored(string $chunk, float $confidence): self
    {
        $copy = clone $this;
        $copy->chunk = $chunk;
        $copy->confidence_score = $confidence;

        return $copy;
    }
}
