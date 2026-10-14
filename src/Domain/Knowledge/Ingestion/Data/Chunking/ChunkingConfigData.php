<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Domain\Knowledge\Ingestion\Contracts\ChunkMeasure;
use Domain\Knowledge\Ingestion\Enums\ChunkSizeUnit;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Enums\SplitStrategy;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\CharacterMeasure;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\TokenMeasure;
use Domain\Llm\Services\TikToken;
use Spatie\LaravelData\Data;

/**
 * How one kind of content is split: the kind's strategy, the chunk size and
 * overlap in their unit, and the tokenizer when the unit is tokens. Built
 * from config (set in the admin panel, Settings → Knowledge) or, for a
 * preview, from the panel's form before it is saved.
 */
final class ChunkingConfigData extends Data
{
    public function __construct(
        public ContentKind $kind,
        public SplitStrategy $strategy,
        public ChunkSizeUnit $unit,
        public int $size,
        public int $overlap = 0,
        public string $encoding = 'cl100k_base',
    ) {}

    /** The setup for this kind of content in `chatvia.ai.chunking` and `chatvia.ai.tokenizer`. */
    public static function forKind(ContentKind $kind): self
    {
        $unit = ChunkSizeUnit::configured();
        $size = config('chatvia.ai.chunking.size');
        $overlap = config('chatvia.ai.chunking.overlap');

        return new self(
            kind: $kind,
            strategy: $kind->strategy(),
            unit: $unit,
            size: is_numeric($size) && (int) $size > 0 ? (int) $size : $unit->defaultSize(),
            overlap: is_numeric($overlap) ? max(0, (int) $overlap) : 0,
            encoding: TikToken::encoding(),
        );
    }

    /** How chunk lengths are measured: in tokens of the tokenizer, or in characters. */
    public function measure(): ChunkMeasure
    {
        return match ($this->unit) {
            ChunkSizeUnit::Tokens => new TokenMeasure($this->encoding),
            ChunkSizeUnit::Characters => new CharacterMeasure,
        };
    }

    /**
     * A short hash of everything that shapes the chunks (strategy, unit,
     * size, overlap, tokenizer), so a text split with other settings is not
     * reused.
     */
    public function fingerprint(): string
    {
        return hash('xxh128', json_encode([
            $this->kind->value,
            $this->strategy->value,
            $this->unit->value,
            $this->size,
            $this->overlap,
            $this->unit === ChunkSizeUnit::Tokens ? $this->encoding : null,
        ], JSON_THROW_ON_ERROR));
    }
}
