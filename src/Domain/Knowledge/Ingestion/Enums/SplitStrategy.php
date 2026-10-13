<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Enums;

/**
 * How a kind of content is split into chunks. Each kind offers the
 * strategies that suit it ({@see ContentKind}); every strategy fills chunks
 * up to the chunk size, in the unit set in the admin panel
 * ({@see ChunkSizeUnit}). The value is stored in the settings, so never
 * rename a case value.
 */
enum SplitStrategy: string
{
    case Markdown = 'markdown';
    case Recursive = 'recursive';
    case Sentence = 'sentence';
    case Fixed = 'fixed';

    /** What the strategy does, in a few words, e.g. "Whole sentences". */
    public function label(): string
    {
        return match ($this) {
            self::Markdown => 'One section per heading',
            self::Recursive => 'Paragraphs, then sentences',
            self::Sentence => 'Whole sentences',
            self::Fixed => 'Fixed size',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Markdown => 'One chunk per heading section, small sections together. Long sections are split between paragraphs, lists, code blocks and tables, and every chunk starts with the headings above it.',
            self::Recursive => 'Splits at paragraphs, then lines, sentences and words, until every chunk fits.',
            self::Sentence => 'Packs whole sentences into chunks up to the chunk size.',
            self::Fixed => 'Cuts the text every chunk size, whatever its structure.',
        };
    }

    /**
     * Whether the strategy fits this kind of content: the heading strategy
     * only fits Markdown, and splitting Markdown by sentences or fixed sizes
     * would ignore the headings it has.
     */
    public function suits(ContentKind $kind): bool
    {
        return match ($this) {
            self::Markdown => $kind === ContentKind::Markdown,
            self::Recursive => true,
            self::Sentence, self::Fixed => $kind !== ContentKind::Markdown,
        };
    }
}
