<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Enums;

use Domain\Knowledge\Ingestion\Actions\Chunks\EnhanceChunkAction;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * How the pages of a source are enhanced before they are stored as chunks
 * (see {@see EnhanceChunkAction}): not at all, from the page text only, or —
 * for document pages — with the neighbouring pages and the page's images and
 * tables. Text sources follow `chatvia.ai.chunker.enhance_text` (on by
 * default, they are short); files and websites follow
 * `chatvia.ai.chunker.enhance` (on by default too: search finds far more of
 * the right passages). The same switch decides the summary of the whole
 * source, so with both off ingestion makes no model calls.
 */
enum ChunkEnhancement: string
{
    case Off = 'off';
    case Text = 'text';
    case Document = 'document';

    /** Pages one chunk job stores when nothing is enhanced: one embedding request and one insert. */
    private const int PLAIN_PAGES_PER_JOB = 32;

    public static function for(SourceFile|SourceText|SourceWebsite $source): self
    {
        // A preset in .env may read "1" or "true".
        if ($source instanceof SourceText) {
            return filter_var(config('chatvia.ai.chunker.enhance_text'), FILTER_VALIDATE_BOOL) ? self::Text : self::Off;
        }

        if (! filter_var(config('chatvia.ai.chunker.enhance'), FILTER_VALIDATE_BOOL)) {
            return self::Off;
        }

        $isDocument = $source instanceof SourceFile && ! $source->isAudioFile() && ! $source->isVideoFile();

        return $isDocument ? self::Document : self::Text;
    }

    /**
     * How many pages one chunk job stores. An enhanced page costs model calls
     * and is retried on its own, so it gets a job of its own.
     */
    public function pagesPerJob(): int
    {
        return $this === self::Off ? self::PLAIN_PAGES_PER_JOB : 1;
    }

    /** Whether the source also gets a summary of the whole of it (a model call). */
    public function summarizes(): bool
    {
        return $this !== self::Off;
    }

    /** Whether the page's images and tables are needed (they are described). */
    public function needsMedia(): bool
    {
        return $this === self::Document;
    }
}
