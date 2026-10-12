<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

/**
 * The built-in tokenizer: counts and splits text into tokens with the
 * encoding chosen in the admin panel (`chatvia.ai.tokenizer`, Settings →
 * Chunking), cl100k by default. It needs no outside service, so it is the
 * fallback when the chunker cannot count (Domain\Knowledge\Ingestion\Services\
 * TokenCounter) and the tokenizer of estimates that must be fast: the
 * built-in splitter, and prices when a provider reports no usage.
 */
class TikToken
{
    /** The encodings this tokenizer knows. */
    public const array ENCODINGS = ['cl100k_base', 'o200k_base', 'p50k_base', 'r50k_base'];

    /** @var array<string, Encoder> */
    private static array $encoders = [];

    private static ?EncoderProvider $provider = null;

    public static function encoding(): string
    {
        $encoding = (string) config('chatvia.ai.tokenizer', 'cl100k_base');

        return in_array($encoding, self::ENCODINGS, true) ? $encoding : 'cl100k_base';
    }

    private static function getEncoder(): Encoder
    {
        $encoding = self::encoding();

        return self::$encoders[$encoding] ??= (self::$provider ??= new EncoderProvider)->get($encoding);
    }

    public static function countTokens(string $text): int
    {
        return count(self::getEncoder()->encode($text));
    }

    /** @return list<int> */
    public static function encode(string $text): array
    {
        return self::getEncoder()->encode($text);
    }

    /** @param  array<int>  $tokens */
    public static function decode(array $tokens): string
    {
        return self::getEncoder()->decode($tokens);
    }
}
