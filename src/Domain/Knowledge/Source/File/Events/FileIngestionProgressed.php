<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Events;

use Domain\Knowledge\Source\File\Models\SourceFile;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Pushes a file's ingestion progress (0-100) to the agent's Files page. Per-
 * chunk events can stay below 100 (duplicate or blank pages create no chunk),
 * so the batch's completion step always sends a final one after the status
 * flips to ingested.
 */
class FileIngestionProgressed implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SourceFile $sourceFile,
        public string $agentExternalId,
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
            'progress' => $this->sourceFile->ingestionProgress(),
        ];
    }
}
