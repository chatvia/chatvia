<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

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

it('shows the workspace member count on every agent row', function (): void {
    // The owner, one teammate and two chatters.
    teammateWithPermissions($this->owner, []);
    Chatter::factory()->count(2)->create(['workspace_id' => $this->owner->workspace()->id]);
    Chatter::factory()->create(); // another workspace's chatter

    $this->actingAs($this->owner)->getJson(route('agents.index'))
        ->assertOk()
        ->assertJsonPath('0.total_members', 4)
        ->assertJsonPath('1.total_members', 4);
});

it('lets only the team owner create an agent', function (): void {
    $teammate = teammateWithPermissions($this->owner, ['agent.setup']);

    $this->actingAs($teammate)
        ->postJson(route('agents.store'), ['name' => 'Nope'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Only team owners can create agents.');

    $this->actingAs($this->owner)
        ->post(route('agents.store'), ['name' => 'Fresh'])
        ->assertRedirect();

    expect($this->owner->workspace()->agents()->where('name', 'Fresh')->exists())->toBeTrue();
});

it('gates the identity and rate-limit pages on agent.identity.manage', function (string $routeName): void {
    $blocked = teammateWithPermissions($this->owner, ['agent.setup']);
    $allowed = teammateWithPermissions($this->owner, ['agent.identity.manage']);

    $this->actingAs($blocked)->get(route($routeName, $this->granted))->assertForbidden();
    $this->actingAs($allowed)->get(route($routeName, $this->granted))->assertOk();
})->with(['agents.identity', 'agents.security.rate-limit']);
