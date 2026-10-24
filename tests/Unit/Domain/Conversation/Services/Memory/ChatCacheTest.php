<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Str;

/**
 * The mark of who owns a conversation carries the viewer's kind as well as
 * their id, so a chatter and a staff account with the same id never own each
 * other's conversations, and nobody owns one without a viewer.
 */
it('keeps the ownership of a staff account and a chatter with the same id apart', function (): void {
    $workspace = Agent::factory()->create()->workspaceExternalId();
    $conversationId = (string) Str::uuid();

    ChatCache::rememberOwner($conversationId, ChatViewerData::staff(42), $workspace);

    expect(ChatCache::isOwnedBy($conversationId, ChatViewerData::staff(42), $workspace))->toBeTrue()
        ->and(ChatCache::isOwnedBy($conversationId, ChatViewerData::chatter(42), $workspace))->toBeFalse()
        ->and(ChatCache::isOwnedBy($conversationId, null, $workspace))->toBeFalse();
});
