<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use RuntimeException;

/**
 * No speech-to-text service is set up on this install. The message is shown to
 * the owner (as the failure reason of an audio source), so it names the fix in
 * plain words and no vendor.
 */
class TranscriptionNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Audio transcription is not configured on this server.');
    }
}
