<?php

namespace App\Events;

use App\Models\SourceWebsite;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WebsiteIngestionProgressEvent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets, SerializesModels;

    public function __construct(public SourceWebsite $website) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('agents.'.$this->website->agent_id.'.crawler.progress'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'website_id' => $this->website->id,
            'progress' => $this->website->ingestionProgress(),
        ];
    }
}
