<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

/**
 * The shape of a picture to make. Each service names it its own way: OpenAI
 * by pixel size, Google and OpenRouter by aspect ratio.
 */
enum ImageShape: string
{
    case Square = 'square';
    case Landscape = 'landscape';
    case Portrait = 'portrait';

    /** OpenAI's size, one its image models all take. */
    public function size(): string
    {
        return match ($this) {
            self::Square => '1024x1024',
            self::Landscape => '1536x1024',
            self::Portrait => '1024x1536',
        };
    }

    /** The aspect ratio, as Google and OpenRouter take it. */
    public function aspectRatio(): string
    {
        return match ($this) {
            self::Square => '1:1',
            self::Landscape => '3:2',
            self::Portrait => '2:3',
        };
    }
}
