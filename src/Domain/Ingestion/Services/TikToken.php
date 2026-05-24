<?php

namespace Domain\Ingestion\Services;

use Yethee\Tiktoken\Encoder;
use Yethee\Tiktoken\EncoderProvider;

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
