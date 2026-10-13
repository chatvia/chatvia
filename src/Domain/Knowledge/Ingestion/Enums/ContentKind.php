<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Enums;

/**
 * The kinds of text the splitter splits, each with its own strategy: the
 * structure of the text decides where a chunk should end. Markdown has
 * headings and blocks, a typed text or .txt file has paragraphs, a
 * transcript is one long run of sentences. Documents read page by page (PDF,
 * Word, slides, images) are not split: each page is one chunk.
 *
 * The strategy for each kind is set in the admin panel (Settings → Chunking,
 * Advanced); a strategy that does not suit the kind falls back to the
 * kind's default.
 */
enum ContentKind: string
{
    case Markdown = 'markdown';
    case Text = 'text';
    case Transcript = 'transcript';

    public function label(): string
    {
        return match ($this) {
            self::Markdown => 'Markdown',
            self::Text => 'Text',
            self::Transcript => 'Transcript',
        };
    }

    /** Which sources are of this kind, for the admin panel. */
    public function sources(): string
    {
        return match ($this) {
            self::Markdown => 'Web pages, Markdown files and ebooks',
            self::Text => 'Typed texts and .txt files',
            self::Transcript => 'Audio and video transcripts',
        };
    }

    public function defaultStrategy(): SplitStrategy
    {
        return match ($this) {
            self::Markdown => SplitStrategy::Markdown,
            self::Text => SplitStrategy::Recursive,
            self::Transcript => SplitStrategy::Sentence,
        };
    }

    /**
     * The strategies that suit this kind, the default first.
     *
     * @return list<SplitStrategy>
     */
    public function strategies(): array
    {
        $default = $this->defaultStrategy();
        $others = array_filter(SplitStrategy::cases(), fn (SplitStrategy $strategy): bool => $strategy !== $default && $strategy->suits($this));

        return [$default, ...array_values($others)];
    }

    /** The strategy set for this kind, or its default when none (or an unsuitable one) is set. */
    public function strategy(): SplitStrategy
    {
        $configured = SplitStrategy::tryFrom((string) config("chatvia.ai.chunking.strategies.{$this->value}"));

        return $configured instanceof SplitStrategy && $configured->suits($this) ? $configured : $this->defaultStrategy();
    }
}
