<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

it('creates an agent attached to a library prompt via prompt_id', function (): void {
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->user->workspace()->id]);

    $this->withToken($this->token)
        ->postJson('/api/v1/agents', [
            'name' => 'Bot',
            'team_id' => $this->user->primaryTeam()->external_id,
            'prompt_id' => $prompt->external_id,
            'is_public' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.prompt_id', $prompt->external_id)
        ->assertJsonPath('data.system_instructions', null);

    $agent = Agent::where('name', 'Bot')->firstOrFail();
    expect($agent->agent_prompt_id)->toBe($prompt->id);
    expect($agent->system_instructions)->toBeNull();
});

it('creates an agent with custom system_instructions', function (): void {
    $this->withToken($this->token)
        ->postJson('/api/v1/agents', [
            'name' => 'Bot',
            'team_id' => $this->user->primaryTeam()->external_id,
            'system_instructions' => 'Custom prompt',
            'is_public' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.system_instructions', 'Custom prompt')
        ->assertJsonPath('data.prompt_id', null);
});

it('rejects sending both prompt_id and system_instructions', function (): void {
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->user->workspace()->id]);

    $this->withToken($this->token)
        ->postJson('/api/v1/agents', [
            'name' => 'Bot',
            'team_id' => $this->user->primaryTeam()->external_id,
            'prompt_id' => $prompt->external_id,
            'system_instructions' => 'Custom prompt',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prompt_id']);
});

it('rejects an unknown prompt_id', function (): void {
    $this->withToken($this->token)
        ->postJson('/api/v1/agents', [
            'name' => 'Bot',
            'team_id' => $this->user->primaryTeam()->external_id,
            'prompt_id' => 'does-not-exist',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prompt_id']);
});

it('swaps an agent between a library prompt and a custom prompt on update', function (): void {
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->user->workspace()->id]);

    $agent = createAgent($this->user, ['system_instructions' => 'Original custom']);

    // Attach a library prompt → clears system_instructions.
    $this->withToken($this->token)
        ->patchJson("/api/v1/agents/{$agent->external_id}", ['prompt_id' => $prompt->external_id])
        ->assertOk()
        ->assertJsonPath('data.prompt_id', $prompt->external_id)
        ->assertJsonPath('data.system_instructions', null);

    expect($agent->fresh()->agent_prompt_id)->toBe($prompt->id);

    // Swap back to a custom prompt → clears agent_prompt_id.
    $this->withToken($this->token)
        ->patchJson("/api/v1/agents/{$agent->external_id}", ['system_instructions' => 'New custom'])
        ->assertOk()
        ->assertJsonPath('data.system_instructions', 'New custom')
        ->assertJsonPath('data.prompt_id', null);

    expect($agent->fresh()->agent_prompt_id)->toBeNull();
});
