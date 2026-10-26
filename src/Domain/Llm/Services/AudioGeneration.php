<?php

declare(strict_types=1);

namespace Domain\Llm\Services;

use Domain\Llm\Contracts\Speaker;

/**
 * The speech service that makes the audio files agents create in chats. It
 * is picked on its own (Settings → AI models → Audio generation,
 * `chatvia.ai.audio_generator`), so it can differ from the one that reads
 * replies aloud, and it is off until an admin picks one.
 */
final readonly class AudioGeneration
{
    public function __construct(
        public Speaker $speaker,
    ) {}

    public function isEnabled(): bool
    {
        return $this->speaker->isEnabled();
    }

    /** The service and model that read, stored with each file made. */
    public function name(): string
    {
        return $this->speaker->name();
    }
}
