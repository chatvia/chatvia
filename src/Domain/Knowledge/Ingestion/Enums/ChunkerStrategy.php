<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Enums;

/**
 * The chunking strategies of the chunker (github.com/chatvia/chunker), as
 * `engine.strategy`. Each one knows the options it accepts and the chunker's
 * default for each, so Chatvia sends only valid options and the admin panel
 * shows the right fields. The value is stored, so never rename a case value.
 */
enum ChunkerStrategy: string
{
    case LangchainRecursive = 'langchain.recursive';
    case LangchainCharacter = 'langchain.character';
    case LangchainToken = 'langchain.token';
    case LangchainMarkdown = 'langchain.markdown';
    case LlamaIndexSentence = 'llamaindex.sentence';
    case LlamaIndexToken = 'llamaindex.token';
    case LlamaIndexSemantic = 'llamaindex.semantic';
    case LlamaIndexMarkdown = 'llamaindex.markdown';
    case ChonkieToken = 'chonkie.token';
    case ChonkieSentence = 'chonkie.sentence';
    case ChonkieRecursive = 'chonkie.recursive';
    case ChonkieSemantic = 'chonkie.semantic';

    /** The library behind the strategy, as the chunker names it. */
    public function engine(): string
    {
        return explode('.', $this->value, 2)[0];
    }

    /** The strategy's name within its engine. */
    public function strategy(): string
    {
        return explode('.', $this->value, 2)[1];
    }

    public static function engineLabel(string $engine): string
    {
        return match ($engine) {
            'langchain' => 'LangChain',
            'llamaindex' => 'LlamaIndex',
            'chonkie' => 'Chonkie',
            default => $engine,
        };
    }

    /**
     * @return list<self>
     */
    public static function forEngine(string $engine): array
    {
        return array_values(array_filter(self::cases(), fn (self $strategy): bool => $strategy->engine() === $engine));
    }

    public function label(): string
    {
        return match ($this) {
            self::LangchainRecursive, self::ChonkieRecursive => 'Recursive',
            self::LangchainCharacter => 'Character',
            self::LangchainToken, self::LlamaIndexToken, self::ChonkieToken => 'Fixed tokens',
            self::LangchainMarkdown, self::LlamaIndexMarkdown => 'Markdown headings',
            self::LlamaIndexSentence, self::ChonkieSentence => 'Sentences',
            self::LlamaIndexSemantic, self::ChonkieSemantic => 'Semantic',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::LangchainRecursive => 'Splits on paragraphs, then lines, sentences and words, until every chunk fits. Set a language for headings or code. A good default.',
            self::LangchainCharacter => 'Splits on one separator (a blank line by default), then joins the pieces up to the chunk size.',
            self::LangchainToken => 'Cuts the text into windows of a fixed number of tokens, ignoring its structure.',
            self::LangchainMarkdown => 'One chunk per Markdown section; each chunk keeps its headings. Long sections can be split again.',
            self::LlamaIndexSentence => 'Packs whole sentences into chunks up to the chunk size.',
            self::LlamaIndexToken => 'Chunks of a fixed number of tokens, cut between words.',
            self::LlamaIndexSemantic => 'Starts a new chunk where the meaning changes, found by comparing embeddings of neighbouring sentences.',
            self::LlamaIndexMarkdown => 'One chunk per Markdown section, with the path of its headings.',
            self::ChonkieToken => 'Cuts the text into windows of a fixed number of tokens.',
            self::ChonkieSentence => 'Packs whole sentences into chunks up to the chunk size.',
            self::ChonkieRecursive => 'Splits on paragraphs, sentences, punctuation and words, until every chunk fits.',
            self::ChonkieSemantic => 'Keeps sentences together while their meaning stays close, found with embeddings.',
        };
    }

    /** Whether the chunker needs its own embeddings model for this strategy. */
    public function requiresEmbeddings(): bool
    {
        return in_array($this, [self::LlamaIndexSemantic, self::ChonkieSemantic], true);
    }

    /**
     * The options the strategy accepts, in the order the admin panel shows
     * them.
     *
     * @return list<ChunkerOption>
     */
    public function options(): array
    {
        return match ($this) {
            self::LangchainRecursive => [ChunkerOption::ChunkSize, ChunkerOption::ChunkOverlap, ChunkerOption::LengthUnit, ChunkerOption::Language, ChunkerOption::Separators],
            self::LangchainCharacter => [ChunkerOption::ChunkSize, ChunkerOption::ChunkOverlap, ChunkerOption::LengthUnit, ChunkerOption::Separator],
            self::LangchainToken, self::LlamaIndexSentence, self::LlamaIndexToken, self::ChonkieToken => [ChunkerOption::ChunkSize, ChunkerOption::ChunkOverlap],
            self::LangchainMarkdown => [ChunkerOption::MaxHeaderLevel, ChunkerOption::StripHeaders, ChunkerOption::ChunkSize, ChunkerOption::ChunkOverlap],
            self::LlamaIndexSemantic => [ChunkerOption::BufferSize, ChunkerOption::BreakpointPercentileThreshold],
            self::LlamaIndexMarkdown => [],
            self::ChonkieSentence => [ChunkerOption::ChunkSize, ChunkerOption::ChunkOverlap, ChunkerOption::MinSentencesPerChunk],
            self::ChonkieRecursive => [ChunkerOption::ChunkSize, ChunkerOption::MinCharactersPerChunk],
            self::ChonkieSemantic => [ChunkerOption::ChunkSize, ChunkerOption::Threshold, ChunkerOption::SimilarityWindow, ChunkerOption::MinSentencesPerChunk],
        };
    }

    public function accepts(ChunkerOption $option): bool
    {
        return in_array($option, $this->options(), true);
    }

    /**
     * The value the chunker uses when the option is not sent. Null when the
     * option is off by default (Markdown sections are not split again).
     */
    public function defaultFor(ChunkerOption $option): string|int|float|bool|null
    {
        return match ($option) {
            ChunkerOption::ChunkSize => $this === self::LangchainMarkdown ? null : 512,
            ChunkerOption::ChunkOverlap => match ($this->engine()) {
                'chonkie' => 0,
                default => $this === self::LangchainMarkdown ? 0 : 64,
            },
            ChunkerOption::LengthUnit => 'tokens',
            ChunkerOption::Separator => '\n\n',
            ChunkerOption::MaxHeaderLevel => 3,
            ChunkerOption::StripHeaders => false,
            ChunkerOption::BufferSize => 1,
            ChunkerOption::BreakpointPercentileThreshold => 95,
            ChunkerOption::MinSentencesPerChunk => 1,
            ChunkerOption::MinCharactersPerChunk => 24,
            ChunkerOption::Threshold => 0.8,
            ChunkerOption::SimilarityWindow => 3,
            ChunkerOption::Separators, ChunkerOption::Language => null,
        };
    }
}
