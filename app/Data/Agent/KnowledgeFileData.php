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
        public int $number_of_pages,
        public int $ingestion_progress,
        public DateTime $created_at
    ) {}

    public static function fromFile(AgentFile $file): self
    {
        return new self(
            id: $file->id,
            name: $file->name,
            size_in_kb: $file->size_in_kb,
            mime_type: $file->mime_type,
            status: $file->status,
            number_of_pages: $file->number_of_pages,
            ingestion_progress: $file->ingestionProgress(),
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
