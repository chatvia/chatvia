<?php

declare(strict_types=1);

namespace Domain\Conversation\Enums;

/**
 * What an agent made for a chatter: a picture, a spoken audio file or a
 * short video. The
 * value is also the artifact kind the chat renders, and both are stored
 * (`chat_generated_media.kind`, `metadata.artifacts[].kind`), so they must
 * not change.
 */
enum GeneratedMediaKind: string
{
    case Image = 'image';
    case Audio = 'audio';
    case Video = 'video';

    /** The usual file extension, for a link made before the file exists. */
    public function extension(): string
    {
        return match ($this) {
            self::Image => 'png',
            self::Audio => 'mp3',
            self::Video => 'mp4',
        };
    }
}
