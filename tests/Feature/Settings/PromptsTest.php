<?php

use App\Auth\Models\User;
use App\Teams\Services\TeamService;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentPrompt;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
});

function makePrompt(User $user, array $overrides = []): AgentPrompt
{
    return $user->prompts()->create(array_merge([
        'name' => 'Support persona',
        'prompt' => 'You are a helpful support agent.',
    ], $overrides));
}

it('creates a prompt', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.prompts.store'), [
            'name' => 'Sales persona',
            'prompt' => 'You are a persuasive sales agent.',
        ])
        ->assertRedirect();

    expect($this->user->prompts()->where('name', 'Sales persona')->exists())->toBeTrue();
});

it('validates required fields on create', function (): void {
    $this->actingAs($this->user)
        ->post(route('settings.prompts.store'), ['name' => '', 'prompt' => ''])
        ->assertSessionHasErrors(['name', 'prompt']);
});

it('rejects a duplicate name on create', function (): void {
    makePrompt($this->user, ['name' => 'Taken']);

    $this->actingAs($this->user)
        ->post(route('settings.prompts.store'), ['name' => 'Taken', 'prompt' => 'x'])
        ->assertSessionHasErrors('name');
});

it('updates a prompt', function (): void {
    $prompt = makePrompt($this->user);

    $this->actingAs($this->user)
        ->patch(route('settings.prompts.update', ['external_id' => $prompt->external_id]), [
            'name' => 'Renamed',
            'prompt' => 'New body',
        ])
        ->assertRedirect();

    $fresh = $prompt->fresh();
    expect($fresh->name)->toBe('Renamed');
    expect($fresh->prompt)->toBe('New body');
});

it('cannot update a prompt owned by another user', function (): void {
    $other = User::factory()->create();
    $prompt = makePrompt($other);

    $this->actingAs($this->user)
        ->patch(route('settings.prompts.update', ['external_id' => $prompt->external_id]), [
            'name' => 'Hijack',
            'prompt' => 'x',
        ])
        ->assertForbidden();
});

it('deletes a prompt and detaches it from agents', function (): void {
    app(TeamService::class)->createDefaultTeam($this->user);
    $prompt = makePrompt($this->user);

    $agent = Agent::create([
        'user_id' => $this->user->id,
        'team_id' => $this->user->primaryTeam()->id,
        'name' => 'Bot',
        'type' => 'knowledge_base',
        'external_id' => Str::random(14),
        'sub_domain' => Str::random(14),
        'agent_prompt_id' => $prompt->id,
        'is_public' => false,
        'source' => 'dashboard',
    ]);

    $this->actingAs($this->user)
        ->delete(route('settings.prompts.destroy', ['external_id' => $prompt->external_id]))
        ->assertRedirect();

    expect(AgentPrompt::whereKey($prompt->id)->exists())->toBeFalse();
    expect($agent->fresh()->agent_prompt_id)->toBeNull();
});
