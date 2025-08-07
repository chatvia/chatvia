<?php

namespace App\Data\Agent;

use App\Models\AgentFile;
use DateTime;
use Illuminate\Database\Eloquent\Collection;
use Spatie\LaravelData\Data;

class KnowledgeFileData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public int $size_in_kb,
        public string $mime_type,
        public string $status,
        public int $length,
        public int $ingestion_progress,
        public bool $is_audio_file,
        public DateTime $created_at
    ) {}

    public static function fromFile(AgentFile $file): self
    {
        return new self(
            id: $file->id,
            name: $file->filename,
            size_in_kb: $file->size_in_kb,
            mime_type: $file->mime_type,
            status: $file->status,
            length: $file->length,
            ingestion_progress: $file->ingestionProgress(),
            is_audio_file: $file->isAudioFile(),
            created_at: $file->created_at,
        );
    }

    public static function fromFiles(Collection $files): \Illuminate\Support\Collection
    {
        return collect($files)->map(function (AgentFile $file) {
            return self::fromFile($file);
        });
    }
}
