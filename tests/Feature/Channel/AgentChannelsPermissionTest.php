<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

/**
 * The agent Channels page has its own `agent.channels` permission: a teammate
 * needs `agent.channels` specifically, and `agent.setup` alone does not open it.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

it('lets the owner open the channels page', function (): void {
    $this->actingAs($this->owner)
        ->get(route('agents.channels', $this->agent))
        ->assertOk();
});

it('lets a teammate WITH agent.channels open the channels page', function (): void {
    $member = teammateWithPermissions($this->owner, ['agent.channels']);

    $this->actingAs($member)
        ->get(route('agents.channels', $this->agent))
        ->assertOk();
});

it('forbids a teammate WITHOUT agent.channels', function (): void {
    $member = teammateWithPermissions($this->owner, []);

    $this->actingAs($member)
        ->get(route('agents.channels', $this->agent))
        ->assertForbidden();
});

it('forbids a teammate who has agent.setup but not agent.channels', function (): void {
    // The contrast proves Channels is not covered by the Setup grant.
    $member = teammateWithPermissions($this->owner, ['agent.setup']);

    $this->actingAs($member)
        ->get(route('agents.channels', $this->agent))
        ->assertForbidden();
});
