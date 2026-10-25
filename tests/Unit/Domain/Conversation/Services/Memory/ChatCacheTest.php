<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Data\Memory\ChatViewerData;
use Domain\Conversation\Services\Memory\ChatCache;
use Illuminate\Support\Str;

/**
 * The mark of who owns a conversation carries the agent and the viewer's kind
 * as well as their id, so a chatter and a staff account with the same id
 * never own each other's conversations, a conversation of one agent is never
 * owned on another, and nobody owns one without a viewer.
 */
it('keeps the ownership of a staff account and a chatter with the same id apart', function (): void {
    $agent = Agent::factory()->create();
    $conversationId = (string) Str::uuid();

    ChatCache::rememberOwner($conversationId, ChatViewerData::staff(42), $agent);

    expect(ChatCache::isOwnedBy($conversationId, ChatViewerData::staff(42), $agent))->toBeTrue()
        ->and(ChatCache::isOwnedBy($conversationId, ChatViewerData::chatter(42), $agent))->toBeFalse()
        ->and(ChatCache::isOwnedBy($conversationId, null, $agent))->toBeFalse();
});

it('never counts a conversation of one agent as owned on another agent of the workspace', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $conversationId = (string) Str::uuid();

    ChatCache::rememberOwner($conversationId, ChatViewerData::chatter(7), $agent);

    expect(ChatCache::isOwnedBy($conversationId, ChatViewerData::chatter(7), $agent))->toBeTrue()
        ->and(ChatCache::isOwnedBy($conversationId, ChatViewerData::chatter(7), $other))->toBeFalse();
});
