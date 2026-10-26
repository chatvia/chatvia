<?php

declare(strict_types=1);

namespace Domain\Llm\Contracts;

use Domain\Llm\Data\Speech\SpeechVoiceData;
use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Enums\SpeechFormat;
use Domain\Llm\Exceptions\SpeechFailedException;
use Domain\Llm\Exceptions\SpeechNotConfiguredException;

/**
 * Reads text aloud: turns it into one audio file. Used for the hosted chat's
 * "read aloud" button on agent replies, and for the audio files agents make. The implementation is chosen from
 * config (`chatvia.ai.speaker`).
 */
interface Speaker
{
    /**
     * @param  string  $text  Plain text of any length; a long one is read in parts that make one file.
     * @param  string  $voice  The id of a voice the service has.
     * @param  AiCallTag  $tag  What the reading is for, as Spectra shows it: a reply read aloud, or an audio file an agent makes.
     * @return string The audio file's bytes, in {@see format()}.
     *
     * @throws SpeechNotConfiguredException When no text-to-speech service is set up.
     * @throws SpeechFailedException When the service could not read the text.
     */
    public function speak(string $text, string $voice, AiCallTag $tag = AiCallTag::ReadAloud): string;

    public function format(): SpeechFormat;

    /** Whether a text-to-speech service is set up (Settings → AI models). */
    public function isEnabled(): bool;

    /**
     * The service and model that speak, part of the key of a saved
     * recording, so another service or model reads a reply again.
     */
    public function name(): string;

    /** The voice the admin picked (Settings → AI models → Read aloud), or the service's first. */
    public function defaultVoice(): string;

    /**
     * The voices an agent may pick from, the default among them.
     *
     * @return list<SpeechVoiceData>
     */
    public function voices(): array;
}
