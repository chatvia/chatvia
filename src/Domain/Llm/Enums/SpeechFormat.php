<?php

declare(strict_types=1);

namespace Domain\Llm\Enums;

/**
 * The audio file a text-to-speech service makes: MP3 (ElevenLabs) or WAV
 * (OpenAI's realtime model sends raw audio, which is wrapped as WAV). Saved
 * replies keep the extension, and the chat plays the file with its type.
 */
enum SpeechFormat: string
{
    case Mp3 = 'mp3';
    case Wav = 'wav';

    public function extension(): string
    {
        return $this->value;
    }

    public function mimeType(): string
    {
        return match ($this) {
            self::Mp3 => 'audio/mpeg',
            self::Wav => 'audio/wav',
        };
    }
}
