<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Contracts;

/**
 * One level of the recursive splitter: where a text may be split (between
 * paragraphs, lines, sentences, words). The pieces are lossless, so joined
 * they are the text again; whitespace stays at the end of the piece before.
 */
interface Separator
{
    /**
     * @return list<string> the non-empty pieces, in order
     */
    public function split(string $text): array;
}
