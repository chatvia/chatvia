<?php

use App\Auth\Models\User;
use App\Teams\Models\Team;
use App\Teams\Services\TeamService;
use Domain\Agents\Models\Agent;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->agent = makeTeamAgent($this->user, 'Support Bot');
    $this->function = $this->user->functions()->create([
        'name' => 'get_order_status',
        'description' => 'Get the order status',
        'parameters_schema' => ['type' => 'object', 'properties' => []],
        'webhook_url' => 'https://example.com/webhook',
        'webhook_method' => 'POST',
        'auth_type' => 'none',
        'auth_config' => null,
        'enabled' => true,
    ]);
});

function makeTeamAgent(User $user, string $name, ?Team $team = null): Agent
{
    return Agent::create([
        'user_id' => $user->id,
        'team_id' => ($team ?? $user->primaryTeam())->id,
        'name' => $name,
        'type' => 'knowledge_base',
        'external_id' => Str::random(14),
        'sub_domain' => Str::random(14),
        'is_public' => false,
        'analytics_enabled' => true,
        'created_via' => 'external',
    ]);
}

it('lists accessible agents as available when none are attached', function (): void {
    $this->actingAs($this->user)
        ->getJson(route('settings.functions.agents.index', ['external_id' => $this->function->external_id]))
        ->assertOk()
        ->assertJsonCount(0, 'attached')
        ->assertJsonCount(1, 'available')
        ->assertJsonPath('available.0.id', $this->agent->external_id);
});

it('attaches a function to an agent', function (): void {
    $this->actingAs($this->user)
        ->postJson(route('settings.functions.agents.attach', [
            'external_id' => $this->function->external_id,
            'agent' => $this->agent->external_id,
        ]))
        ->assertOk()
        ->assertJsonCount(1, 'attached')
        ->assertJsonCount(0, 'available')
        ->assertJsonPath('attached.0.id', $this->agent->external_id);

    expect($this->function->agents()->whereKey($this->agent->id)->exists())->toBeTrue();
});

it('detaches a function from an agent', function (): void {
    $this->agent->functions()->attach($this->function->id);

    $this->actingAs($this->user)
        ->deleteJson(route('settings.functions.agents.detach', [
            'external_id' => $this->function->external_id,
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
        ->getJson(route('settings.functions.agents.index', ['external_id' => $this->function->external_id]))
        ->assertOk()
        ->assertJsonCount(2, 'available');

    $this->actingAs($this->user)
        ->postJson(route('settings.functions.agents.attach', [
            'external_id' => $this->function->external_id,
            'agent' => $otherTeamAgent->external_id,
        ]))
        ->assertOk();

    expect($this->function->agents()->whereKey($otherTeamAgent->id)->exists())->toBeTrue();
});

it('cannot manage agents for a function owned by someone else', function (): void {
    $other = User::factory()->create();
    $foreign = $other->functions()->create([
        'name' => 'foreign_fn',
        'description' => 'x',
        'parameters_schema' => ['type' => 'object'],
        'webhook_url' => 'https://example.com/x',
        'webhook_method' => 'POST',
        'auth_type' => 'none',
        'auth_config' => null,
        'enabled' => true,
    ]);

    $this->actingAs($this->user)
        ->getJson(route('settings.functions.agents.index', ['external_id' => $foreign->external_id]))
        ->assertNotFound();
});

it('cannot attach to an agent owned by another account', function (): void {
    $stranger = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($stranger);
    $strangerAgent = makeTeamAgent($stranger, 'Not Yours');

    $this->actingAs($this->user)
        ->postJson(route('settings.functions.agents.attach', [
            'external_id' => $this->function->external_id,
            'agent' => $strangerAgent->external_id,
        ]))
        ->assertNotFound();
});
