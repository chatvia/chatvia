<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Enums\SpeechFormat;
use Domain\Llm\Exceptions\SpeechNotConfiguredException;

/**
 * Used when no text-to-speech service is set up. The read-aloud button is
 * hidden then, and a call still fails with a clear "not set up" error.
 */
class NullSpeaker implements Speaker
{
    public function speak(string $text, string $voice): string
    {
        throw new SpeechNotConfiguredException;
    }

    public function format(): SpeechFormat
    {
        return SpeechFormat::Mp3;
    }

    public function isEnabled(): bool
    {
        return false;
    }

    public function name(): string
    {
        return 'none';
    }

    public function defaultVoice(): string
    {
        return '';
    }

    public function voices(): array
    {
        return [];
    }
}
