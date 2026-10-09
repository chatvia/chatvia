<?php

namespace Domain\Knowledge\Ingestion\Services\Chunking;

use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Services\TokenSplitter;

/**
 * Text splitter that runs inside the app with no outside service. It packs
 * whole paragraphs (and, for very long paragraphs, whole sentences) into
 * chunks of about `chatvia.ai.local_splitter.chunk_tokens` tokens.
 */
class LocalTextSplitter implements TextSplitter
{
    public function split(string $text): array
    {
        if (trim($text) === '') {
            return [];
        }

        $splitter = new TokenSplitter(
            chunkSize: max((int) config('chatvia.ai.local_splitter.chunk_tokens', 400), 1),
            chunkOverlap: max((int) config('chatvia.ai.local_splitter.overlap_tokens', 0), 0),
        );

        return array_values(array_filter(
            array_map(static fn (PageData $chunk): string => trim($chunk->content), $splitter->split($text)),
            static fn (string $chunk): bool => $chunk !== '',
        ));
    }

    public function name(): string
    {
        return 'local';
    }
}
