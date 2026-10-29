<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Queries\PendingHandoffsQuery;

/**
 * The dashboard's list of chats waiting for a teammate holds only the
 * visible pending conversations of one agent.
 */
it('lists only the visible pending conversations of the agent', function (): void {
    $agent = Agent::factory()->create();
    $pending = Conversation::factory()->count(2)->forAgent($agent)->visible()->handedOff(HandoffStatus::Pending)->create();
    Conversation::factory()->forAgent($agent)->visible()->handedOff(HandoffStatus::Human, $agent->workspace->owner)->create();
    Conversation::factory()->forAgent($agent)->visible()->create();
    Conversation::factory()->forAgent(Agent::factory()->create())->visible()->handedOff(HandoffStatus::Pending)->create();
    Conversation::factory()->forAgent($agent)->handedOff(HandoffStatus::Pending)->create(['is_visible' => false]);

    expect(app(PendingHandoffsQuery::class)->get($agent)->pluck('id')->all())
        ->toEqualCanonicalizing($pending->pluck('id')->all());
});
