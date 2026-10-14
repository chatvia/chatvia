<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Enums;

/**
 * The unit of the chunk size and overlap (admin panel, Settings → Knowledge):
 * tokens of the chosen tokenizer, or characters as a reader sees them. The
 * value is stored in the settings, so never rename a case value.
 */
enum ChunkSizeUnit: string
{
    case Tokens = 'tokens';
    case Characters = 'characters';

    /** The unit set in config (`chatvia.ai.chunking.unit`), tokens when none. */
    public static function configured(): self
    {
        return self::tryFrom((string) config('chatvia.ai.chunking.unit')) ?? self::Tokens;
    }

    public function label(): string
    {
        return match ($this) {
            self::Tokens => 'Tokens',
            self::Characters => 'Characters',
        };
    }

    /** The unit after a number, e.g. "400 tokens". */
    public function suffix(): string
    {
        return match ($this) {
            self::Tokens => 'tokens',
            self::Characters => 'characters',
        };
    }

    /** The chunk size when none is set: about the same text in either unit. */
    public function defaultSize(): int
    {
        return match ($this) {
            self::Tokens => 400,
            self::Characters => 1600,
        };
    }

    public function minSize(): int
    {
        return match ($this) {
            self::Tokens => 50,
            self::Characters => 200,
        };
    }

    public function maxSize(): int
    {
        return match ($this) {
            self::Tokens => 8000,
            self::Characters => 32000,
        };
    }

    public function maxOverlap(): int
    {
        return match ($this) {
            self::Tokens => 2000,
            self::Characters => 8000,
        };
    }
}
