<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Events;

use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a file's ingestion progress (0-100) to the agent's Files page. The
 * chunk jobs send the batch's progress, capped at 99; only the batch's
 * completion step, after the status flips to ingested, sends 100.
 */
class FileIngestionProgressed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SourceFile $sourceFile,
        public string $agentExternalId,
        /** The job batch's progress; null for the final event, sent once the source is ingested. */
        public ?int $progress = null,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agentExternalId.'.files.progress'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'FileIngestionProgressed';
    }

    /** @return array{file_id: int, progress: int} */
    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->sourceFile->id,
            'progress' => $this->progress(),
        ];
    }

    private function progress(): int
    {
        if ($this->sourceFile->status === SourceStatus::Completed || $this->progress === null) {
            return $this->sourceFile->ingestionProgress();
        }

        return min($this->progress, 99);
    }
}
