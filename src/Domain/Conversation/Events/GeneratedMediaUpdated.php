<?php

declare(strict_types=1);

namespace Domain\Conversation\Events;

use Domain\Conversation\Data\Media\GeneratedMediaData;
use Illuminate\Broadcasting\InteractsWithBroadcasting;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A generated image, audio file or video was made, or could not be made. Broadcast
 * at once to the conversation's private media channel as
 * `GeneratedMediaUpdated`, so the hosted chat swaps the placeholder for the
 * file without a reload. A private channel, not the conversation's presence
 * one, so listening never shows the visitor as online in the handoff view.
 */
class GeneratedMediaUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithBroadcasting, InteractsWithSockets;

    public function __construct(
        public string $conversationId,
        public GeneratedMediaData $media,
    ) {}

    /** The channel name without Echo's `private-` prefix. */
    public static function channelName(string $conversationId): string
    {
        return "chat-media.{$conversationId}";
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel(self::channelName($this->conversationId)),
        ];
    }

    public function broadcastAs(): string
    {
        return 'GeneratedMediaUpdated';
    }

    /** @return array<string, mixed> */
    public function broadcastWith(): array
    {
        return $this->media->toArray();
    }
}
