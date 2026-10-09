<?php

use Domain\Agent\Actions\Account\RenameAgentAction;
use Domain\Agent\Data\Account\RenameAgentData;
use Domain\Agent\Models\Agent;

/**
 * Renaming changes the agent's name, and its Hosted Chat subdomain when one is
 * given; without one the subdomain stays.
 */
it('renames the agent and moves its subdomain', function (): void {
    $agent = Agent::factory()->create(['name' => 'Old', 'sub_domain' => 'old-name']);

    $renamed = app(RenameAgentAction::class)->execute($agent, new RenameAgentData('New', 'new-name'));

    expect($renamed->name)->toBe('New')
        ->and($renamed->sub_domain)->toBe('new-name');
});

it('keeps the subdomain when none is given', function (): void {
    $agent = Agent::factory()->create(['name' => 'Old', 'sub_domain' => 'old-name']);

    $renamed = app(RenameAgentAction::class)->execute($agent, new RenameAgentData('New'));

    expect($renamed->name)->toBe('New')
        ->and($renamed->sub_domain)->toBe('old-name');
});
