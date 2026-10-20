<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Messages;

use Domain\Knowledge\Ingestion\Services\Media\TranscriptionAudio;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Illuminate\Support\Str;

/**
 * Turns a voice note into text: sends a working copy of the recording to the
 * speech-to-text service (compressed first when the service does not take it
 * as it is, {@see TranscriptionAudio}), and always removes the copy. Without
 * a speech-to-text service it fails at once, before any compression.
 *
 * @throws TranscriptionNotConfiguredException when no speech-to-text service is set up
 */
class TranscribeAudioAction
{
    public function __construct(
        private readonly TranscriptionAudio $audio,
        private readonly Transcriber $transcriber,
    ) {}

    /**
     * @param  string  $audioPath  the recording; it is copied, never changed
     * @param  string  $extension  its file extension, which names the format
     */
    public function execute(string $audioPath, string $extension): string
    {
        if (! $this->transcriber->isEnabled()) {
            throw new TranscriptionNotConfiguredException;
        }

        $workingCopy = create_temp_dir('/transcription').'/'.Str::uuid().'.'.$extension;

        copy($audioPath, $workingCopy);

        try {
            return $this->audio->withPrepared($workingCopy, $this->transcriber->transcribe(...));
        } finally {
            if (file_exists($workingCopy)) {
                @unlink($workingCopy);
            }
        }
    }
}
