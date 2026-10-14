<?php

declare(strict_types=1);

namespace Domain\Conversation\Actions\Messages;

use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Llm\Contracts\Transcriber;
use Domain\Llm\Exceptions\TranscriptionNotConfiguredException;
use Illuminate\Support\Str;

/**
 * Turns a voice note into text: compresses a working copy of the recording,
 * sends it to the speech-to-text service, and always removes the copy.
 * Without a speech-to-text service it fails at once, before any compression.
 *
 * @throws TranscriptionNotConfiguredException when no speech-to-text service is set up
 */
class TranscribeAudioAction
{
    public function __construct(
        private readonly AudioCompressor $audioCompressor,
        private readonly Transcriber $transcriber,
    ) {}

    /**
     * @param  string  $audioPath  the recording; it is copied, never changed
     * @param  string  $extension  its file extension, which tells the compressor the format
     */
    public function execute(string $audioPath, string $extension): string
    {
        if (! $this->transcriber->isEnabled()) {
            throw new TranscriptionNotConfiguredException;
        }

        $workingCopy = create_temp_dir('/transcription').'/'.Str::uuid().'.'.$extension;

        copy($audioPath, $workingCopy);

        try {
            return $this->transcriber->transcribe($this->audioCompressor->process($workingCopy));
        } finally {
            if (file_exists($workingCopy)) {
                @unlink($workingCopy);
            }
        }
    }
}
