<?php

namespace Domain\Knowledge\Retrieval\Data;

use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use stdClass;

/**
 * One chunk found by the knowledge-base search, with how it was found and how
 * relevant the relevance check found it.
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
        /** Cosine similarity to the query (the closer of the chunk's and its questions' vectors). */
        public ?float $similarity = null,
        /** The chunk's size in tokens, as stored. */
        public int $tokens = 0,
        /**
         * How relevant the relevance check found the chunk, 0..1: the
         * reranker's score, or the similarity check's. Null when no check ran.
         */
        public ?float $relevance = null,
        /** The day the chunk's text entered the knowledge base (Y-m-d), or null when unknown. */
        public ?string $added_on = null,
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
            metadata: filled($row->metadata) ? $row->metadata : null,
            search_type: (string) $row->search_type,
            score: (float) $row->score,
            similarity: isset($row->similarity) ? (float) $row->similarity : null,
            tokens: (int) ($row->tokens ?? 0),
            added_on: filled($row->created_at ?? null) ? Carbon::parse($row->created_at)->toDateString() : null,
        );
    }

    /**
     * The described images of the page: storage path => description.
     *
     * @return array<string, string>
     */
    public function images(): array
    {
        $metadata = json_decode($this->metadata ?? '', true);

        return is_array($metadata['images'] ?? null) ? $metadata['images'] : [];
    }

    public function withRelevance(float $relevance): self
    {
        return $this->rebuild(relevance: $relevance);
    }

    /** The same chunk with other text, such as the text the model reads. */
    public function withText(string $chunk): self
    {
        return $this->rebuild(chunk: $chunk);
    }

    /** The same chunk with one field replaced; DTOs never mutate. */
    private function rebuild(?string $chunk = null, ?float $relevance = null): self
    {
        return new self(
            id: $this->id,
            external_id: $this->external_id,
            chunk: $chunk ?? $this->chunk,
            chunk_index: $this->chunk_index,
            chunkable_type: $this->chunkable_type,
            chunkable_id: $this->chunkable_id,
            metadata: $this->metadata,
            search_type: $this->search_type,
            score: $this->score,
            similarity: $this->similarity,
            tokens: $this->tokens,
            relevance: $relevance ?? $this->relevance,
            added_on: $this->added_on,
        );
    }
}
