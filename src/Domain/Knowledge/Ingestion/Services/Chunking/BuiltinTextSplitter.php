<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking;

use Domain\Knowledge\Ingestion\Contracts\Separator;
use Domain\Knowledge\Ingestion\Contracts\TextSplitter;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkingConfigData;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Enums\SplitStrategy;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\MarkdownSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\PatternSeparator;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\RecursiveSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\SentenceSeparator;

/**
 * The text splitter: splits each kind of content with the strategy, chunk
 * size, overlap and unit set in the admin panel (Settings → Knowledge). It
 * runs in-process, with no outside service.
 */
class BuiltinTextSplitter implements TextSplitter
{
    public function split(string $text, ContentKind $kind): array
    {
        return $this->splitWith($text, ChunkingConfigData::forKind($kind));
    }

    /**
     * Split with the given setup, such as the admin panel's unsaved form.
     *
     * @return list<string> the non-empty chunks, in reading order
     */
    public function splitWith(string $text, ChunkingConfigData $config): array
    {
        $text = mb_scrub($text, 'UTF-8');

        if (blank($text)) {
            return [];
        }

        $splitter = new RecursiveSplitter($config->measure(), $config->size, $config->overlap);

        if ($config->strategy === SplitStrategy::Markdown) {
            return new MarkdownSplitter($splitter)->split($text);
        }

        return $splitter->split($text, $this->separators($config->strategy));
    }

    public function name(): string
    {
        return 'builtin';
    }

    /**
     * Where each strategy may split, from the coarsest place to the finest;
     * fixed windows come after the last.
     *
     * @return list<Separator>
     */
    private function separators(SplitStrategy $strategy): array
    {
        return match ($strategy) {
            SplitStrategy::Markdown, SplitStrategy::Recursive => [PatternSeparator::paragraphs(), PatternSeparator::lines(), new SentenceSeparator, PatternSeparator::words()],
            SplitStrategy::Sentence => [new SentenceSeparator, PatternSeparator::words()],
            SplitStrategy::Fixed => [],
        };
    }
}
