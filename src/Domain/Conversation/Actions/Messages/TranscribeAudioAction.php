<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Messages;

use Domain\Conversation\Enums\VoiceNoteFormat;
use Domain\Knowledge\Ingestion\Services\Media\TranscriptionAudio;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Turns a voice note into text: sends a working copy of the recording to the
 * speech-to-text service (compressed first when the service does not take it
 * as it is, {@see TranscriptionAudio}), and always removes the copy. The copy
 * is named, and ffmpeg is told to read it, by the format detected from its
 * content, never by the name the visitor's browser gave it. Without a
 * speech-to-text service it fails at once, before any compression.
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
     * @param  VoiceNoteFormat  $format  its format, detected from its content
     */
    public function execute(string $audioPath, VoiceNoteFormat $format): string
    {
        if (! $this->transcriber->isEnabled()) {
            throw new TranscriptionNotConfiguredException;
        }

        $workingCopy = create_temp_dir('/transcription').'/'.Str::uuid().'.'.$format->extension();

        File::copy($audioPath, $workingCopy);

        try {
            return $this->audio->withPrepared($workingCopy, $this->transcriber->transcribe(...), $format->demuxer());
        } finally {
            File::delete($workingCopy);
        }
    }
}
