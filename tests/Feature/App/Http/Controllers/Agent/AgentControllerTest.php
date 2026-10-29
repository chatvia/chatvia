<?php

use App\Identity\Workspace\Models\User;

/**
 * The agents list and creating an agent: a teammate sees only the agents
 * their role reaches, and only the team owner may create one, with no agent
 * cap without a license key.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    $this->granted = createAgent($this->owner, ['name' => 'Granted']);
    $this->other = createAgent($this->owner, ['name' => 'Other']);
});

it('lists every team agent for the owner and only granted agents for a teammate', function (): void {
    $teammate = teammateWithPermissions($this->owner, [], allAgents: false, agentExternalIds: [$this->granted->external_id]);

    $this->actingAs($this->owner)->getJson(route('agents.index'))
        ->assertOk()
        ->assertJsonCount(2);
    $this->actingAs($teammate)->getJson(route('agents.index'))
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.external_id', $this->granted->external_id);
});

it('lets only the team owner create an agent, an eleventh one too without a license key', function (): void {
    withoutLicense();
    foreach (range(1, 8) as $n) {
        createAgent($this->owner, ['name' => "Bot {$n}"]);
    }
    $teammate = teammateWithPermissions($this->owner, ['agent.setup']);

    $this->actingAs($teammate)
        ->postJson(route('agents.store'), ['name' => 'Nope'])
        ->assertForbidden();
    $this->actingAs($this->owner)
        ->post(route('agents.store'), ['name' => 'Eleventh'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($this->owner->workspace()->agents()->count())->toBe(11)
        ->and($this->owner->workspace()->agents()->where('name', 'Nope')->exists())->toBeFalse();
});
