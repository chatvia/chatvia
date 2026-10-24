<?php

use App\Identity\Chatter\Models\Chatter;
use Domain\Agent\Models\Agent;
use Domain\Membership\Agent\Actions\RevokeAgentMembershipAction;

/**
 * Revoking a chatter's agent removes the `agent_chatter` row; revoking a
 * grant that is already gone changes nothing and does not fail.
 */
it('revokes the chatter\'s grant, and a second revoke changes nothing', function (): void {
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();

    app(RevokeAgentMembershipAction::class)->execute($agent, $chatter);
    app(RevokeAgentMembershipAction::class)->execute($agent, $chatter);

    expect($agent->chatters()->whereKey($chatter->id)->exists())->toBeFalse()
        ->and(Chatter::query()->whereKey($chatter->id)->exists())->toBeTrue();
});
