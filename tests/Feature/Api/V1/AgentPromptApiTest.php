<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->token = $this->user->createToken('account')->plainTextToken;
});

function promptApiHeaders(string $token): array
{
    return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
}

it('creates an agent attached to a library prompt via prompt_id', function (): void {
    $prompt = $this->user->workspace()->prompts()->create(['name' => 'Persona', 'prompt' => 'Be helpful.']);

    $this->withHeaders(promptApiHeaders($this->token))
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
    $this->withHeaders(promptApiHeaders($this->token))
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
    $prompt = $this->user->workspace()->prompts()->create(['name' => 'Persona', 'prompt' => 'Be helpful.']);

    $this->withHeaders(promptApiHeaders($this->token))
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
    $this->withHeaders(promptApiHeaders($this->token))
        ->postJson('/api/v1/agents', [
            'name' => 'Bot',
            'team_id' => $this->user->primaryTeam()->external_id,
            'prompt_id' => 'does-not-exist',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['prompt_id']);
});

it('swaps an agent between a library prompt and a custom prompt on update', function (): void {
    $prompt = $this->user->workspace()->prompts()->create(['name' => 'Persona', 'prompt' => 'Be helpful.']);

    $agent = Agent::create([
        'workspace_id' => $this->user->workspace()->id,
        'team_id' => $this->user->primaryTeam()->id,
        'name' => 'Bot',
        'external_id' => Str::random(14),
        'slug' => Str::random(14),
        'system_instructions' => 'Original custom',
        'is_public' => false,
        'source' => 'api',
    ]);

    // Attach a library prompt → clears system_instructions.
    $this->withHeaders(promptApiHeaders($this->token))
        ->patchJson("/api/v1/agents/{$agent->external_id}", ['prompt_id' => $prompt->external_id])
        ->assertOk()
        ->assertJsonPath('data.prompt_id', $prompt->external_id)
        ->assertJsonPath('data.system_instructions', null);

    expect($agent->fresh()->agent_prompt_id)->toBe($prompt->id);

    // Swap back to a custom prompt → clears agent_prompt_id.
    $this->withHeaders(promptApiHeaders($this->token))
        ->patchJson("/api/v1/agents/{$agent->external_id}", ['system_instructions' => 'New custom'])
        ->assertOk()
        ->assertJsonPath('data.system_instructions', 'New custom')
        ->assertJsonPath('data.prompt_id', null);

    expect($agent->fresh()->agent_prompt_id)->toBeNull();
});
