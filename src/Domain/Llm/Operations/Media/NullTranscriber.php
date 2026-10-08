<?php

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;

/**
 * Used when no speech-to-text service is set up. Every call fails with a clear
 * "not configured" error instead of a network or credentials error.
 */
class NullTranscriber implements Transcriber
{
    public function transcribe(string $filePath): string
    {
        throw new TranscriptionNotConfiguredException;
    }
}
