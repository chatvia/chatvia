<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\GeneratedMedia;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * While a file is being made, the hosted chat asks how the conversation's
 * images and audio stand.
 */
it('lists the images and audio of the visitor\'s conversation as they stand', function (): void {
    Storage::fake(GeneratedMedia::DISK);
    $agent = Agent::factory()->create(['is_public' => true]);
    $id = HostedChatVisitor::startConversation($agent);
    $conversation = Conversation::query()->where('external_id', $id)->firstOrFail();
    $pending = GeneratedMedia::factory()->forConversation($conversation)->create();
    $ready = GeneratedMedia::factory()->forConversation($conversation)->audio()->stored()->create();

    $this->getJson("/chat/{$agent->slug}/conversations/{$id}/media")
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.id', $pending->external_id)
        ->assertJsonPath('0.status', 'pending')
        ->assertJsonPath('0.url', null)
        ->assertJsonPath('1.id', $ready->external_id)
        ->assertJsonPath('1.kind', 'audio')
        ->assertJsonPath('1.status', 'ready')
        ->assertJsonPath('1.url', $ready->url);
});
