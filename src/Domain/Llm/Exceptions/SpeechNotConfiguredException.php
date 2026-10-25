<?php

declare(strict_types=1);

namespace Domain\Llm\Exceptions;

use RuntimeException;

/**
 * No text-to-speech service is set up on this install, so a reply cannot be
 * read aloud. The message names the fix in plain words and no vendor.
 */
class SpeechNotConfiguredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Reading replies aloud is not set up on this server.');
    }
}
