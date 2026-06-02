<?php

namespace Domain\Source\File\Data\KnowledgeBase;

use DateTime;
use Domain\Agent\Data\Account\AgentData;
use Spatie\LaravelData\Data;

final class KnowledgeFileData extends Data
{
    public function __construct(
        public int $id,
        public AgentData $agent,
        public string $filename,
        public string $url,
        public bool $is_audio,
        public float $size_in_kb,
        public string $mime_type,
        public int $length,
        public string $status,
        public int $retrieval_count,
        public DateTime $created_at,
        public DateTime $updated_at,
    ) {}

    public static function fromCollection($files): array
    {
        $data = [];

        foreach ($files as $file) {
            $data[] = new self(
                id: $file->id,
                agent: AgentData::from($file->agent),
                filename: $file->filename,
                url: $file->url,
                is_audio: $file->isAudioFile(),
                size_in_kb: $file->size_in_kb,
                mime_type: $file->mime_type,
                length: $file->length,
                status: $file->status,
                retrieval_count: $file->retrieval_count,
                created_at: $file->created_at,
                updated_at: $file->updated_at,
            );
        }

        return $data;
    }
}
