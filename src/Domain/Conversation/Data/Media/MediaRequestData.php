<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Media;

use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Llm\Enums\ImageShape;
use Spatie\LaravelData\Data;

/**
 * What an agent asked to make in a chat: a picture from a prompt (in a
 * shape), or an audio file of a text read aloud.
 */
class MediaRequestData extends Data
{
    public function __construct(
        public GeneratedMediaKind $kind,
        /** The picture to draw, or the text to read. */
        public string $prompt,
        public ?ImageShape $shape = null,
    ) {}

    public static function image(string $prompt, ImageShape $shape = ImageShape::Square): self
    {
        return new self(GeneratedMediaKind::Image, $prompt, $shape);
    }

    public static function audio(string $text): self
    {
        return new self(GeneratedMediaKind::Audio, $text);
    }
}
