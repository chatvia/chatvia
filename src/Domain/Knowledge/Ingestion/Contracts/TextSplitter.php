<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Contracts;

use Domain\Knowledge\Ingestion\Enums\ContentKind;

/**
 * Splits text into ordered, retrieval-sized chunks for knowledge ingestion
 * (text files, ebooks, web pages, typed texts and transcripts), with the
 * strategy set for its kind of content. The built-in splitter does the
 * splitting, in-process; there is no other splitter.
 */
interface TextSplitter
{
    /**
     * @return list<string> The non-empty chunks, in reading order.
     */
    public function split(string $text, ContentKind $kind): array;

    /** Short name saved with the source it splits. */
    public function name(): string;
}
