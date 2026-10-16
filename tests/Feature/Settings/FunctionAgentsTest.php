<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = makeTeamAgent($this->user, 'Support Bot');
    $this->function = AgentFunction::factory()->forOwner($this->user)->create();
});

it('lists accessible agents as available when none are attached', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('integrations.functions.agents.index', ['function' => $this->function->external_id]))
        ->assertOk()
        ->assertJsonCount(0, 'attached')
        ->assertJsonCount(1, 'available')
        ->assertJsonPath('available.0.id', $this->agent->external_id);
});

it('attaches a function to an agent', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('integrations.functions.agents.attach', [
            'function' => $this->function->external_id,
            'agent' => $this->agent->external_id,
        ]))
        ->assertSuccessful()
        ->assertJsonCount(1, 'attached')
        ->assertJsonCount(0, 'available')
        ->assertJsonPath('attached.0.id', $this->agent->external_id);

    expect($this->function->agents()->whereKey($this->agent->id)->exists())->toBeTrue();
});

it('detaches a function from an agent', function (): void {
    $this->agent->functions()->attach($this->function->id);

    $this->actingAs($this->user)
        ->deleteJson(route('integrations.functions.agents.detach', [
            'function' => $this->function->external_id,
            'agent' => $this->agent->external_id,
        ]))
        ->assertOk()
        ->assertJsonCount(0, 'attached')
        ->assertJsonCount(1, 'available');

    expect($this->function->agents()->whereKey($this->agent->id)->exists())->toBeFalse();
});

it('lists and attaches agents from another team in the same account', function (): void {
    $otherTeam = app(TeamService::class)->createDefaultTeam($this->user);
    $otherTeamAgent = makeTeamAgent($this->user, 'Sales Bot', $otherTeam);

    $this->actingAs($this->user)
        ->getJson(route('integrations.functions.agents.index', ['function' => $this->function->external_id]))
        ->assertOk()
        ->assertJsonCount(2, 'available');

    $this->actingAs($this->user)
        ->postJson(route('integrations.functions.agents.attach', [
            'function' => $this->function->external_id,
            'agent' => $otherTeamAgent->external_id,
        ]))
        ->assertSuccessful();

    expect($this->function->agents()->whereKey($otherTeamAgent->id)->exists())->toBeTrue();
});

it('cannot manage agents for a function owned by someone else', function (): void {
    $other = User::factory()->create();
    $foreign = $other->workspace()->functions()->create([
        'internal_name' => 'foreign_fn',
        'description' => 'x',
        'parameters_schema' => ['type' => 'object'],
        'webhook_url' => 'https://example.com/x',
        'webhook_method' => 'POST',
        'auth_type' => 'none',
        'auth_config' => null,
        'is_enabled' => true,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('integrations.functions.agents.index', ['function' => $foreign->external_id]))
        ->assertNotFound();
});

it('cannot attach to an agent owned by another account', function (): void {
    $stranger = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($stranger);
    $strangerAgent = makeTeamAgent($stranger, 'Not Yours');

    $this->actingAs($this->user)
        ->postJson(route('integrations.functions.agents.attach', [
            'function' => $this->function->external_id,
            'agent' => $strangerAgent->external_id,
        ]))
        ->assertNotFound();
});
