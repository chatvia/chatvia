<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Llm\Contracts\Transcriber;
use Illuminate\Support\Str;

/**
 * Turns a voice note into text: compresses a working copy of the recording,
 * sends it to the speech-to-text service, and always removes the copy.
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
