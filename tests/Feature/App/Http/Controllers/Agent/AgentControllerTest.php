<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Support\License\FreeTier;

/**
 * The agents list (each row with the workspace's member count), creating an
 * agent (team owners only) and deleting one. A teammate sees only the agents
 * their role reaches.
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

it('shows the workspace member count on every agent row', function (): void {
    // The owner, one teammate and two chatters.
    teammateWithPermissions($this->owner, []);
    Chatter::factory()->count(2)->ofWorkspace($this->owner)->create();
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

    expect($this->owner->workspace()->agents()->pluck('name')->sort()->values()->all())->toBe(['Fresh', 'Granted', 'Other']);
});

it('refuses an agent name longer than the column holds', function (): void {
    $this->actingAs($this->owner)
        ->postJson(route('agents.store'), ['name' => Str::repeat('a', 256)])
        ->assertJsonValidationErrors('name');

    expect($this->owner->workspace()->agents()->count())->toBe(2);
});

it('refuses an agent past the free limit with a message on the name field', function (): void {
    foreach (range(1, FreeTier::AGENTS - 2) as $n) {
        createAgent($this->owner, ['name' => "Bot {$n}"]);
    }

    $this->actingAs($this->owner)
        ->postJson(route('agents.store'), ['name' => 'Eleventh'])
        ->assertJsonValidationErrors(['name' => 'the most it can have without a ChatVia.ai license key']);

    withLicense();

    $this->actingAs($this->owner)
        ->post(route('agents.store'), ['name' => 'Eleventh'])
        ->assertRedirect();

    expect($this->owner->workspace()->agents()->count())->toBe(FreeTier::AGENTS + 1);
});

it('deletes the agent and goes back to the list', function (): void {
    Bus::fake([PurgeAgentJob::class]);

    $this->actingAs($this->owner)
        ->delete(route('agents.delete', $this->granted))
        ->assertRedirect(route('agents.index'));

    expect(Agent::withTrashed()->findOrFail($this->granted->id)->trashed())->toBeTrue();
});
