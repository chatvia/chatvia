<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Messages;

use Spatie\LaravelData\Data;

/**
 * A reply read aloud: the saved audio file the hosted chat plays, and its type.
 */
class MessageSpeechData extends Data
{
    public function __construct(
        public string $disk,
        public string $path,
        public string $mimeType,
    ) {}
}
