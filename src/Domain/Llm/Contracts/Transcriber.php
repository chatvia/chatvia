<?php

declare(strict_types=1);

namespace Domain\Llm\Contracts;

use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;

/**
 * Turns speech in an audio file into text. Used for chat voice input and for
 * audio and video knowledge sources. The implementation is chosen from config
 * (`chatvia.ai.transcriber`).
 */
interface Transcriber
{
    /**
     * @param  string  $filePath  Local path of an audio or video file whose extension names its format:
     *                            one the service {@see takesAsIs()}, or an MP3 made for it.
     *
     * @throws TranscriptionNotConfiguredException When no speech-to-text service is set up.
     */
    public function transcribe(string $filePath): string;

    /**
     * Whether the service takes a file of this format (its extension, lower
     * case) and size as it is, so it needs no ffmpeg to re-encode it first.
     */
    public function takesAsIs(string $extension, int $bytes): bool;

    /** Whether a speech-to-text service is set up (Settings → AI models). */
    public function isEnabled(): bool;

    /**
     * The model that transcribes, saved on a source as its `parser` and part
     * of the cache key of a transcript, so a new model transcribes again.
     */
    public function name(): string;
}
