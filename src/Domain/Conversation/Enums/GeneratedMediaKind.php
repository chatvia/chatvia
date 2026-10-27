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
}
