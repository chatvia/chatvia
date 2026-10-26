<?php

declare(strict_types=1);

namespace Tests\Support\Llm;

use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Data\Speech\SpeechVoiceData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\SpeechFormat;
use Throwable;

/**
 * Offline stand-in for a text-to-speech service: it is set up, has two
 * voices, reads each text as "[voice:text]" in MP3, and remembers what it
 * read. Given a failure, every reading throws it.
 */
final class FakeSpeaker implements Speaker
{
    /** @var list<array{text: string, voice: string}> */
    public array $spoken = [];

    public function __construct(
        private readonly string $name = 'fake tts',
        private readonly ?Throwable $failure = null,
    ) {}

    public function speak(string $text, string $voice, AiCallTag $tag = AiCallTag::ReadAloud): string
    {
        if ($this->failure instanceof Throwable) {
            throw $this->failure;
        }

        $this->spoken[] = ['text' => $text, 'voice' => $voice];

        return "[{$voice}:{$text}]";
    }

    public function format(): SpeechFormat
    {
        return SpeechFormat::Mp3;
    }

    public function isEnabled(): bool
    {
        return true;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function defaultVoice(): string
    {
        return 'alto';
    }

    public function voices(): array
    {
        return [new SpeechVoiceData('alto', 'Alto'), new SpeechVoiceData('tenor', 'Tenor')];
    }
}
