<?php

namespace Domain\Knowledge\Ingestion\Services\Chunking;

use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;

/**
 * Text splitter backed by the hosted chunk.fit service. It splits by section
 * title and merges small neighbouring sections, which suits Markdown from web
 * pages and ebooks.
 */
class ChunkFitTextSplitter implements TextSplitter
{
    public function __construct(
        private readonly ChunkFitClient $client,
    ) {}

    public function split(string $text): array
    {
        return $this->client->splitByTitle($text);
    }

    public function name(): string
    {
        return 'chunkfit';
    }
}
