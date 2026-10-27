<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Media;

use Domain\Conversation\Enums\GeneratedMediaKind;
use Domain\Llm\Enums\ImageShape;
use Domain\Llm\Enums\VideoShape;
use Spatie\LaravelData\Data;

/**
 * What an agent asked to make in a chat: a picture or a video from a prompt
 * (in a shape), or an audio file of a text read aloud, or of the PDF the
 * chatter attached (read on the server, so the agent does not copy it).
 */
class MediaRequestData extends Data
{
    public function __construct(
        public GeneratedMediaKind $kind,
        /** The picture to draw or video to film, or the text to read. */
        public string $prompt,
        public ImageShape|VideoShape|null $shape = null,
        public bool $fromAttachment = false,
    ) {}

    public static function image(string $prompt, ImageShape $shape = ImageShape::Square): self
    {
        return new self(GeneratedMediaKind::Image, $prompt, $shape);
    }

    public static function video(string $prompt, VideoShape $shape = VideoShape::Landscape): self
    {
        return new self(GeneratedMediaKind::Video, $prompt, $shape);
    }

    public static function audio(string $text): self
    {
        return new self(GeneratedMediaKind::Audio, $text);
    }

    /** An audio file of the PDF attached to the chat; the text is read when the request is taken. */
    public static function audioOfAttachment(): self
    {
        return new self(GeneratedMediaKind::Audio, '', fromAttachment: true);
    }
}
