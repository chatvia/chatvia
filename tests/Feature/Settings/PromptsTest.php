<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Membership\Team\Services\TeamService;
use Domain\Prompt\Models\AgentPrompt;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    // The settings controllers gate every action on has_team_permission(),
    // which requires the user to have a current/primary team.
    app(TeamService::class)->createDefaultTeam($this->user);
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
        ->post(route('library.prompts.store'), [
            'name' => 'Sales persona',
            'prompt' => 'You are a persuasive sales agent.',
        ])
        ->assertRedirect();

    expect($this->user->prompts()->where('name', 'Sales persona')->exists())->toBeTrue();
});

it('validates required fields on create', function (): void {
    $this->actingAs($this->user)
        ->post(route('library.prompts.store'), ['name' => '', 'prompt' => ''])
        ->assertSessionHasErrors(['name', 'prompt']);
});

it('rejects a duplicate name on create', function (): void {
    makePrompt($this->user, ['name' => 'Taken']);

    $this->actingAs($this->user)
        ->post(route('library.prompts.store'), ['name' => 'Taken', 'prompt' => 'x'])
        ->assertSessionHasErrors('name');
});

it('updates a prompt', function (): void {
    $prompt = makePrompt($this->user);

    $this->actingAs($this->user)
        ->patch(route('library.prompts.update', ['external_id' => $prompt->external_id]), [
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
        ->patch(route('library.prompts.update', ['external_id' => $prompt->external_id]), [
            'name' => 'Hijack',
            'prompt' => 'x',
        ])
        ->assertForbidden();
});

it('deletes a prompt and detaches it from agents', function (): void {
    $prompt = makePrompt($this->user);

    $agent = Agent::create([
        'user_id' => $this->user->id,
        'team_id' => $this->user->primaryTeam()->id,
        'name' => 'Bot',
        'external_id' => Str::random(14),
        'sub_domain' => Str::random(14),
        'agent_prompt_id' => $prompt->id,
        'is_public' => false,
        'source' => 'dashboard',
    ]);

    $this->actingAs($this->user)
        ->delete(route('library.prompts.destroy', ['external_id' => $prompt->external_id]))
        ->assertRedirect();

    expect(AgentPrompt::whereKey($prompt->id)->exists())->toBeFalse();
    expect($agent->fresh()->agent_prompt_id)->toBeNull();
});
