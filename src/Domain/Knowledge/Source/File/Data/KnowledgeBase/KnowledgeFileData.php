<?php

namespace Domain\Knowledge\Source\File\Data\KnowledgeBase;

use DateTime;
use Domain\Agent\Data\Account\AgentData;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Spatie\LaravelData\Data;

/**
 * One row of the account-wide knowledge base list: a file and the agent that
 * uses it.
 */
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
        public SourceStatus $status,
        public int $retrieval_count,
        public DateTime $created_at,
        public DateTime $updated_at,
    ) {}

    public static function fromFile(SourceFile $file, Agent $agent): self
    {
        return new self(
            id: $file->id,
            agent: new AgentData(
                id: $agent->id,
                name: $agent->name,
                slug: $agent->slug,
                url: $agent->url,
                chat_url: $agent->chat_url,
            ),
            filename: $file->filename,
            url: $file->url,
            is_audio: $file->isAudioFile(),
            size_in_kb: (float) $file->size_in_kb,
            mime_type: $file->mime_type,
            length: $file->length,
            status: $file->status,
            retrieval_count: $file->retrieval_count,
            created_at: $file->created_at,
            updated_at: $file->updated_at,
        );
    }
}
