<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Media;

use Spatie\LaravelData\Data;

/**
 * The text of a PDF a chatter attached to the chat, as an audio file reads
 * it: the file's name, and its text (empty for a scan with no text layer).
 */
class AttachedTextData extends Data
{
    public function __construct(
        public string $filename,
        public string $text,
    ) {}
}
