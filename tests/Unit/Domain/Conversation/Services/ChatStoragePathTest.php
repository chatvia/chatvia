<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\ChatStoragePath;

/**
 * Every chat file sits under the agent's folder, in its conversation's
 * folder by kind, so one prefix wipe removes a conversation, an agent or a
 * workspace.
 */
it('puts a conversation\'s files under the agent\'s chat folder', function (): void {
    $agent = Agent::factory()->create();
    $root = 'workspaces/'.$agent->workspaceExternalId().'/agents/'.$agent->external_id.'/chat';

    expect(ChatStoragePath::root($agent))->toBe($root)
        ->and(ChatStoragePath::forConversation($agent, 'c1'))->toBe($root.'/c1')
        ->and(ChatStoragePath::forConversation($agent, 'c1', '/images/'))->toBe($root.'/c1/images')
        ->and(ChatStoragePath::pending($agent))->toBe($root.'/pending');
});

it('refuses an empty conversation id, which would point at every chat', function (): void {
    ChatStoragePath::forConversation(Agent::factory()->create(), '');
})->throws(LogicException::class);

it('picks the folder by kind', function (?string $kind, string $folder): void {
    expect(ChatStoragePath::kindFolder($kind))->toBe($folder);
})->with([
    'image' => ['image', 'images'],
    'video' => ['video', 'videos'],
    'audio' => ['audio', 'audio'],
    'pdf' => ['pdf', 'files'],
    'unknown' => [null, 'files'],
]);
