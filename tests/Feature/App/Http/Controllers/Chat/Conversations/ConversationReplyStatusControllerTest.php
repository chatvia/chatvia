<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Turn\RunningTurns;
use Tests\Support\Conversation\HostedChatVisitor;

/**
 * A chat reloaded mid-reply asks whether its reply is still being written.
 */
it('says whether a reply is being written in the visitor\'s conversation', function (): void {
    $agent = Agent::factory()->create(['is_public' => true]);
    $id = HostedChatVisitor::startConversation($agent);

    $this->getJson("/chat/{$agent->slug}/conversations/{$id}/reply-status")
        ->assertOk()
        ->assertExactJson(['running' => false]);

    app(RunningTurns::class)->track($id, fn (): Generator => (function (): Generator {
        yield 'a';
    })());

    $this->getJson("/chat/{$agent->slug}/conversations/{$id}/reply-status")
        ->assertOk()
        ->assertExactJson(['running' => true]);
});
