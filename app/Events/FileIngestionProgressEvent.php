<?php

namespace App\Events;

use App\Models\SourceFile;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileIngestionProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(
        public SourceFile $sourceFile,
        public int $agentId,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agentId.'.files.progress'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->sourceFile->id,
            'progress' => $this->sourceFile->ingestionProgress(),
        ];
    }
}
