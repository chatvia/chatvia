<?php

use Domain\Agent\Actions\Account\RenameAgentAction;
use Domain\Agent\Data\Account\RenameAgentData;
use Domain\Agent\Models\Agent;

/**
 * Renaming changes the agent's name and moves its Hosted Chat subdomain to
 * the one given.
 */
it('renames the agent and moves its subdomain', function (): void {
    $agent = Agent::factory()->create(['name' => 'Old', 'slug' => 'old-name']);

    $renamed = app(RenameAgentAction::class)->execute($agent, new RenameAgentData('New', 'new-name'));

    expect($renamed->name)->toBe('New')
        ->and($renamed->slug)->toBe('new-name');
});
