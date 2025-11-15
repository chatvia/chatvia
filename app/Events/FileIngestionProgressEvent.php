<?php

namespace App\Events;

use App\Models\AgentFile;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class FileIngestionProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(public AgentFile $agentFile) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->agentFile->agent_id.'.files.progress'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'file_id' => $this->agentFile->id,
            'progress' => $this->agentFile->ingestionProgress(),
        ];
    }
}
