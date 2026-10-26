<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Media;

use Domain\Llm\Contracts\Speaker;
use Domain\Llm\Data\Speech\SpeechVoiceData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\SpeechFormat;
use Domain\Llm\Exceptions\SpeechFailedException;
use Domain\Llm\Services\AiCallTags;
use Domain\Llm\Services\ElevenLabsVoices;
use Domain\Llm\Services\SpeechTextParts;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Text to speech through ElevenLabs (`/text-to-speech/{voice}`, model
 * `eleven_flash_v2_5` by default, set in `services.elevenlabs.speech_model`),
 * with the ElevenLabs key and address of Settings → AI providers. A long
 * text is read in parts; MP3 parts play as one file when joined. Voices come
 * from the account ({@see ElevenLabsVoices}).
 */
class ElevenLabsSpeaker implements Speaker
{
    /** Within every offered model's limit (Multilingual v2 and v4 take 10,000). */
    private const int MAX_CHARACTERS = 5000;

    public function __construct(
        private readonly AiCallTags $tags,
        private readonly ElevenLabsVoices $voices,
        private readonly SpeechTextParts $parts,
    ) {}

    public function speak(string $text, string $voice): string
    {
        $url = Str::rtrim((string) config('services.elevenlabs.endpoint'), '/').'/text-to-speech/'.rawurlencode($voice).'?output_format=mp3_44100_128';

        try {
            return collect($this->parts->split($text, self::MAX_CHARACTERS))
                ->map(fn (string $part): string => $this->tags->during(AiCallTag::ReadAloud, fn (): string => Http::withHeaders(['xi-api-key' => (string) config('services.elevenlabs.api_key')])
                    ->accept('audio/mpeg')
                    ->timeout(120)
                    ->post($url, ['text' => $part, 'model_id' => $this->model()])
                    ->throw()
                    ->body()))
                ->implode('');
        } catch (Throwable $e) {
            throw new SpeechFailedException('ElevenLabs could not read the text: '.$e->getMessage(), $e->getCode(), previous: $e);
        }
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
        return 'elevenlabs '.$this->model();
    }

    public function defaultVoice(): string
    {
        $saved = (string) config('services.elevenlabs.speech_voice');

        return filled($saved) ? $saved : ($this->voices->recommended()[0]->id ?? '');
    }

    /** The recommended voices, with the admin's pick among them even when it is not one. */
    public function voices(): array
    {
        $voices = $this->voices->recommended();
        $default = $this->defaultVoice();

        if ($default !== '' && ! array_any($voices, fn (SpeechVoiceData $voice): bool => $voice->id === $default)) {
            $picked = array_find($this->voices->all(), fn (SpeechVoiceData $voice): bool => $voice->id === $default);
            array_unshift($voices, $picked ?? new SpeechVoiceData($default, $default));
        }

        return $voices;
    }

    private function model(): string
    {
        return (string) config('services.elevenlabs.speech_model');
    }
}
