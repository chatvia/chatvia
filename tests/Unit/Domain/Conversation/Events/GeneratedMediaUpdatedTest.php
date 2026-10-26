<?php

use Domain\Conversation\Data\Media\GeneratedMediaData;
use Domain\Conversation\Events\GeneratedMediaUpdated;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Broadcasting\PrivateChannel;

/**
 * A made or failed file is announced on the conversation's private media
 * channel, under its own name, with the fields the chat swaps in.
 */
it('goes to the conversation media channel with the file as it stands', function (): void {
    $media = GeneratedMedia::factory()->ready()->create();
    $event = new GeneratedMediaUpdated($media->conversation->external_id, GeneratedMediaData::fromModel($media));

    [$channel] = $event->broadcastOn();

    expect($channel)->toBeInstanceOf(PrivateChannel::class)
        ->and($channel->name)->toBe('private-chat-media.'.$media->conversation->external_id)
        ->and($event->broadcastAs())->toBe('GeneratedMediaUpdated')
        ->and($event->broadcastWith())->toMatchArray(['id' => $media->external_id, 'kind' => 'image', 'status' => 'ready', 'url' => $media->url]);
});
