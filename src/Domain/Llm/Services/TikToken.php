<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

/**
 * The built-in tokenizer: counts the tokens of a text with the encoding
 * chosen in the admin panel (`chatvia.ai.tokenizer`, Settings → Splitting),
 * cl100k by default. It counts the stored size of every chunk, measures
 * chunks when they are sized in tokens, and estimates prices when a provider
 * reports no usage.
 */
class TikToken
{
    /** The encodings this tokenizer knows, with the models that use them. */
    public const array ENCODINGS = [
        'cl100k_base' => 'cl100k_base (GPT-4, GPT-3.5)',
        'o200k_base' => 'o200k_base (GPT-4o and newer)',
        'p50k_base' => 'p50k_base (Codex, text-davinci)',
        'r50k_base' => 'r50k_base (GPT-3)',
    ];

    /**
     * Loaded BPE encoders by encoding name. Safe as a static cache on a
     * long-lived worker: an encoder is immutable once built.
     *
     * @var array<string, Encoder>
     */
    private static array $encoders = [];

    /** One shared provider; it only loads vocabulary files, which never change. */
    private static ?EncoderProvider $provider = null;

    /** The configured encoding, or cl100k_base when it is not one of {@see ENCODINGS}. */
    public static function encoding(?string $encoding = null): string
    {
        $encoding ??= (string) config('chatvia.ai.tokenizer', 'cl100k_base');

        return array_key_exists($encoding, self::ENCODINGS) ? $encoding : 'cl100k_base';
    }

    public static function countTokens(string $text, ?string $encoding = null): int
    {
        return count(self::encoder($encoding)->encode($text));
    }

    /**
     * The bytes of each token of the text, in order: joined, they are the
     * text again. A token can hold part of a multi-byte character, so one
     * piece on its own is not always valid UTF-8.
     *
     * @return list<string>
     */
    public static function tokenBytes(string $text, ?string $encoding = null): array
    {
        $encoder = self::encoder($encoding);

        return array_map(static fn (int $token): string => $encoder->decode([$token]), $encoder->encode($text));
    }

    private static function encoder(?string $encoding): Encoder
    {
        $encoding = self::encoding($encoding);

        return self::$encoders[$encoding] ??= (self::$provider ??= new EncoderProvider)->get($encoding);
    }
}
