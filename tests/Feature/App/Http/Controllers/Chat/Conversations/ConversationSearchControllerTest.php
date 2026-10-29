<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;

/**
 * Conversation search finds a signed-in chatter's conversations in their
 * existing chat session, and as a read it never creates another one.
 */
it('finds a signed-in chatter\'s conversation by title in their existing session', function (): void {
    $agent = Agent::factory()->create(['is_public' => false]);
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $conversation = Conversation::factory()->forAgent($agent)->ofChatter($chatter)->hostedChat()->visible()->withTitle('Billing question')->create();

    $this->actingAs($chatter->fresh(), 'chat')
        ->getJson("/chat/{$agent->slug}/conversations/search?q=billing")
        ->assertOk()
        ->assertJsonPath('0.id', $conversation->external_id);

    expect(Session::query()->where('agent_id', $agent->id)->count())->toBe(1);
});
