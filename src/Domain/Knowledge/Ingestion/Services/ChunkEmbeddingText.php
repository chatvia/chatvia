<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services;

use Domain\Knowledge\Ingestion\Actions\Chunks\EnhanceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\PageVisualsData;
use Domain\Knowledge\Ingestion\Models\Chunk;

/**
 * The text a chunk's embedding is made from, in one place, so ingestion and a
 * re-index (after a change of the embedding model) embed the same text.
 *
 * A page is embedded with its summary first and the descriptions of its
 * images and tables last (contextual retrieval, see
 * {@see EnhanceChunkAction}); the summary chunk of a source (chunk index 0)
 * as plain text, without its HTML.
 */
final class ChunkEmbeddingText
{
    public static function forPage(string $summary, string $content, string $visuals = ''): string
    {
        if ($visuals !== '') {
            $content .= "\n\n".$visuals;
        }

        return $summary !== '' ? $summary."\n\n".$content : $content;
    }

    public static function forSummary(string $summary): string
    {
        return strip_tags($summary);
    }

    /** The text of a stored chunk, from what its row holds. */
    public static function of(Chunk $chunk): string
    {
        if ((int) $chunk->chunk_index === 0) {
            return self::forSummary($chunk->chunk);
        }

        return self::forPage(
            (string) $chunk->summary,
            $chunk->chunk,
            PageVisualsData::fromMetadata($chunk->metadata ?? [])->embeddingText(),
        );
    }
}
