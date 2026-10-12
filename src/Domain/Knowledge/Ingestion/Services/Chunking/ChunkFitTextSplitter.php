<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking;

use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\ChunkFit\ChunkFitClient;

/**
 * Text splitter backed by the chunker (chunk.fit or self-hosted), with the
 * strategy and options set in the admin panel (Settings → Chunking).
 */
class ChunkFitTextSplitter implements TextSplitter
{
    public function __construct(
        private readonly ChunkFitClient $client,
    ) {}

    public function split(string $text): array
    {
        return $this->client->split($text);
    }

    public function name(): string
    {
        return 'chunkfit';
    }
}
