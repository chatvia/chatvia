<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Llm\Operations\Media\Transcription;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TranscribeAudioAction
{
    public function __construct(
        private readonly AudioCompressor $audioCompressor,
        private readonly Transcription $transcription,
    ) {}

    public function execute(UploadedFile $file): string
    {
        $tempPath = create_temp_dir('/transcription').'/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        $file->move(dirname($tempPath), basename($tempPath));

        try {
            $compressedPath = $this->audioCompressor->process($tempPath);
            $result = $this->transcription->transcribe($compressedPath);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        return $result;
    }
}
