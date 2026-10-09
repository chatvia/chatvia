<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Contracts;

/**
 * Splits plain text or Markdown into ordered, retrieval-sized chunks for
 * knowledge ingestion (text files, ebooks and crawled web pages). The
 * implementation is chosen from config (`chatvia.ai.text_splitter`), so an
 * install can use a hosted splitting service or the built-in local splitter.
 */
interface TextSplitter
{
    /**
     * @return list<string> The non-empty chunks, in reading order.
     */
    public function split(string $text): array;

    /** Short name saved with the source and its chunks (for example "chunkfit" or "local"). */
    public function name(): string;
}
