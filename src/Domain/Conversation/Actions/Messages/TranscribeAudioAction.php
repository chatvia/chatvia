<?php

namespace Domain\Conversation\Actions\Messages;

use Domain\Knowledge\Ingestion\Services\Media\AudioCompressor;
use Domain\Llm\Contracts\Transcriber;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class TranscribeAudioAction
{
    public function __construct(
        private readonly AudioCompressor $audioCompressor,
        private readonly Transcriber $transcriber,
    ) {}

    public function execute(UploadedFile $file): string
    {
        $tempPath = create_temp_dir('/transcription').'/'.Str::uuid().'.'.$file->getClientOriginalExtension();

        $file->move(dirname($tempPath), basename($tempPath));

        try {
            $compressedPath = $this->audioCompressor->process($tempPath);
            $result = $this->transcriber->transcribe($compressedPath);
        } finally {
            if (file_exists($tempPath)) {
                @unlink($tempPath);
            }
        }

        return $result;
    }
}
