<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Domain\Knowledge\Ingestion\Enums\ChunkerOption;
use Domain\Knowledge\Ingestion\Enums\ChunkerStrategy;
use Spatie\LaravelData\Data;

/**
 * How the chunker splits text: the strategy, the tokenizer and the strategy's
 * options. A null option is not sent, so the chunker uses its own default.
 * Built from config (set in the admin panel, Settings → Chunking) or, for a
 * preview, from the panel's form before it is saved.
 */
final class ChunkerConfigData extends Data
{
    /**
     * @param  list<string>|null  $separators
     */
    public function __construct(
        public ChunkerStrategy $strategy,
        public string $encoding = 'cl100k_base',
        public ?int $chunkSize = null,
        public ?int $chunkOverlap = null,
        public ?string $lengthUnit = null,
        public ?string $language = null,
        public ?array $separators = null,
        public ?string $separator = null,
        public ?int $maxHeaderLevel = null,
        public ?bool $stripHeaders = null,
        public ?int $bufferSize = null,
        public ?int $breakpointPercentileThreshold = null,
        public ?int $minSentencesPerChunk = null,
        public ?int $minCharactersPerChunk = null,
        public ?float $threshold = null,
        public ?int $similarityWindow = null,
    ) {}

    /**
     * The setup in `services.chunkfit`, with the tokenizer of the install
     * (`chatvia.ai.tokenizer`). An unknown strategy falls back to the
     * recursive splitter.
     */
    public static function fromConfig(): self
    {
        $options = config('services.chunkfit.options');

        return self::fromOptions(
            ChunkerStrategy::tryFrom((string) config('services.chunkfit.strategy')) ?? ChunkerStrategy::LangchainRecursive,
            (string) (config('chatvia.ai.tokenizer') ?: 'cl100k_base'),
            is_array($options) ? $options : [],
        );
    }

    /**
     * @param  array<string, mixed>  $options  By the chunker's option name.
     */
    public static function fromOptions(ChunkerStrategy $strategy, string $encoding, array $options): self
    {
        $int = fn (ChunkerOption $option): ?int => is_numeric($options[$option->value] ?? null) ? (int) $options[$option->value] : null;
        $string = fn (ChunkerOption $option): ?string => filled($options[$option->value] ?? null) && is_scalar($options[$option->value]) ? (string) $options[$option->value] : null;
        $separators = $options[ChunkerOption::Separators->value] ?? null;

        return new self(
            strategy: $strategy,
            encoding: $encoding,
            chunkSize: $int(ChunkerOption::ChunkSize),
            chunkOverlap: $int(ChunkerOption::ChunkOverlap),
            lengthUnit: $string(ChunkerOption::LengthUnit),
            language: $string(ChunkerOption::Language),
            separators: is_array($separators) && $separators !== [] ? array_values(array_map(strval(...), $separators)) : null,
            separator: $string(ChunkerOption::Separator),
            maxHeaderLevel: $int(ChunkerOption::MaxHeaderLevel),
            stripHeaders: is_bool($options[ChunkerOption::StripHeaders->value] ?? null) ? $options[ChunkerOption::StripHeaders->value] : null,
            bufferSize: $int(ChunkerOption::BufferSize),
            breakpointPercentileThreshold: $int(ChunkerOption::BreakpointPercentileThreshold),
            minSentencesPerChunk: $int(ChunkerOption::MinSentencesPerChunk),
            minCharactersPerChunk: $int(ChunkerOption::MinCharactersPerChunk),
            threshold: is_numeric($options[ChunkerOption::Threshold->value] ?? null) ? (float) $options[ChunkerOption::Threshold->value] : null,
            similarityWindow: $int(ChunkerOption::SimilarityWindow),
        );
    }

    /**
     * The `chunker` object of a chunk request: the engine, the strategy, and
     * the options this strategy accepts that are set. Separators may be typed
     * with escapes ("\n\n"), which become the real characters.
     *
     * @return array<string, mixed>
     */
    public function toChunker(): array
    {
        $chunker = ['engine' => $this->strategy->engine(), 'strategy' => $this->strategy->strategy()];

        foreach ($this->strategy->options() as $option) {
            $value = $this->value($option);

            if ($value !== null) {
                $chunker[$option->value] = $value;
            }
        }

        return $chunker;
    }

    /**
     * @return string|int|float|bool|list<string>|null
     */
    public function value(ChunkerOption $option): string|int|float|bool|array|null
    {
        return match ($option) {
            ChunkerOption::ChunkSize => $this->chunkSize,
            ChunkerOption::ChunkOverlap => $this->chunkOverlap,
            ChunkerOption::LengthUnit => $this->lengthUnit,
            ChunkerOption::Language => $this->language,
            ChunkerOption::Separators => $this->separators === null ? null : array_map($this->unescape(...), $this->separators),
            ChunkerOption::Separator => $this->separator === null ? null : $this->unescape($this->separator),
            ChunkerOption::MaxHeaderLevel => $this->maxHeaderLevel,
            ChunkerOption::StripHeaders => $this->stripHeaders,
            ChunkerOption::BufferSize => $this->bufferSize,
            ChunkerOption::BreakpointPercentileThreshold => $this->breakpointPercentileThreshold,
            ChunkerOption::MinSentencesPerChunk => $this->minSentencesPerChunk,
            ChunkerOption::MinCharactersPerChunk => $this->minCharactersPerChunk,
            ChunkerOption::Threshold => $this->threshold,
            ChunkerOption::SimilarityWindow => $this->similarityWindow,
        };
    }

    private function unescape(string $value): string
    {
        return str_replace(['\n', '\r', '\t'], ["\n", "\r", "\t"], $value);
    }
}
