<?php

use Domain\Agent\Actions\Settings\SetAgentDomainAction;
use Domain\Agent\Models\Agent;

/**
 * SetAgentDomainAction saves the agent's custom domain; the form request
 * checks the value, so one smoke test is enough.
 */
it('sets a custom domain', function (): void {
    $agent = Agent::factory()->create();

    app(SetAgentDomainAction::class)->execute($agent, 'help.acme.com');

    expect($agent->fresh()->domain)->toBe('help.acme.com');
});
