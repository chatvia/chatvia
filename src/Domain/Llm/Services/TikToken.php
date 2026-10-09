<?php

namespace Domain\Llm\Services;

use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

/**
 * Counts and splits text into tokens with one shared tokenizer (cl100k, as for
 * GPT-4). Used for estimates — chunk sizes, prices when a provider reports no
 * usage — so every estimate counts the same way.
 */
class TikToken
{
    private static ?Encoder $encoder = null;

    private static function getEncoder(): Encoder
    {
        if (self::$encoder === null) {
            $provider = new EncoderProvider;
            self::$encoder = $provider->getForModel('gpt-4');
        }

        return self::$encoder;
    }

    public static function countTokens(string $text): int
    {
        return count(self::getEncoder()->encode($text));
    }

    public static function encode(string $text): array
    {
        return self::getEncoder()->encode($text);
    }

    public static function decode(array $tokens): string
    {
        return self::getEncoder()->decode($tokens);
    }
}
