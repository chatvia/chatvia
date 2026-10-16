<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Domain\Membership\Team\Services\TeamService;

/**
 * The workspace prompt library: create, rename, delete (which also detaches
 * the prompt from agents), shared by teammates with account.prompts and
 * hidden from other workspaces.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    // The settings routes are gated by a team permission, which requires the
    // user to have a current/primary team.
    app(TeamService::class)->createDefaultTeam($this->user);
});

it('creates a prompt', function (): void {
    $this->actingAs($this->user)
        ->post(route('library.prompts.store'), [
            'name' => 'Sales persona',
            'prompt' => 'You are a persuasive sales agent.',
        ])
        ->assertRedirect();

    expect($this->user->workspace()->prompts()->where('name', 'Sales persona')->exists())->toBeTrue();
});

it('validates required fields on create', function (): void {
    $this->actingAs($this->user)
        ->post(route('library.prompts.store'), ['name' => '', 'prompt' => ''])
        ->assertSessionHasErrors(['name', 'prompt']);
});

it('rejects a duplicate name on create', function (): void {
    AgentPrompt::factory()->create(['workspace_id' => $this->user->workspace()->id, 'name' => 'Taken']);

    $this->actingAs($this->user)
        ->post(route('library.prompts.store'), ['name' => 'Taken', 'prompt' => 'x'])
        ->assertSessionHasErrors('name');
});

it('updates a prompt', function (): void {
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->user->workspace()->id]);

    $this->actingAs($this->user)
        ->patch(route('library.prompts.update', ['prompt' => $prompt->external_id]), [
            'name' => 'Renamed',
            'prompt' => 'New body',
        ])
        ->assertRedirect();

    $fresh = $prompt->fresh();
    expect($fresh->name)->toBe('Renamed');
    expect($fresh->prompt)->toBe('New body');
});

it('hides a prompt of another workspace from an update', function (): void {
    $other = User::factory()->create();
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $other->workspace()->id]);

    $this->actingAs($this->user)
        ->patch(route('library.prompts.update', ['prompt' => $prompt->external_id]), [
            'name' => 'Hijack',
            'prompt' => 'x',
        ])
        ->assertNotFound();
});

it('deletes a prompt and detaches it from agents', function (): void {
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->user->workspace()->id]);

    $agent = createAgent($this->user, ['agent_prompt_id' => $prompt->id]);

    $this->actingAs($this->user)
        ->delete(route('library.prompts.destroy', ['prompt' => $prompt->external_id]))
        ->assertRedirect();

    expect(AgentPrompt::whereKey($prompt->id)->exists())->toBeFalse();
    expect($agent->fresh()->agent_prompt_id)->toBeNull();
});

describe('teammates share the workspace prompt library', function (): void {
    beforeEach(function (): void {
        $this->teammate = teammateWithPermissions($this->user, ['account.prompts']);
    });

    it('stores a teammate-created prompt under the workspace and lists it back to them', function (): void {
        $this->actingAs($this->teammate)
            ->post(route('library.prompts.store'), ['name' => 'Team persona', 'prompt' => 'Be kind.'])
            ->assertRedirect();

        $prompt = AgentPrompt::query()->where('name', 'Team persona')->sole();
        expect($prompt->workspace_id)->toBe($this->user->workspace()->id);

        $this->actingAs($this->teammate)
            ->getJson(route('library.prompts.list'))
            ->assertOk()
            ->assertJsonPath('data.0.id', $prompt->external_id);
    });

    it('lets a teammate edit and delete a workspace prompt', function (): void {
        $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->user->workspace()->id]);

        $this->actingAs($this->teammate)
            ->patch(route('library.prompts.update', ['prompt' => $prompt->external_id]), [
                'name' => 'Edited by teammate',
                'prompt' => 'New body',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        expect($prompt->fresh()->name)->toBe('Edited by teammate');

        $this->actingAs($this->teammate)
            ->delete(route('library.prompts.destroy', ['prompt' => $prompt->external_id]))
            ->assertRedirect();

        expect(AgentPrompt::whereKey($prompt->id)->exists())->toBeFalse();
    });

    it('rejects a teammate rename that collides with another workspace prompt', function (): void {
        AgentPrompt::factory()->create(['workspace_id' => $this->user->workspace()->id, 'name' => 'Taken']);
        $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->user->workspace()->id, 'name' => 'Mine']);

        $this->actingAs($this->teammate)
            ->patch(route('library.prompts.update', ['prompt' => $prompt->external_id]), [
                'name' => 'Taken',
                'prompt' => 'x',
            ])
            ->assertSessionHasErrors('name');
    });

    it('still hides another workspace\'s prompts from a teammate', function (): void {
        $other = User::factory()->create();
        $foreign = AgentPrompt::factory()->create(['workspace_id' => $other->workspace()->id]);

        $this->actingAs($this->teammate)
            ->delete(route('library.prompts.destroy', ['prompt' => $foreign->external_id]))
            ->assertNotFound();

        expect(AgentPrompt::whereKey($foreign->id)->exists())->toBeTrue();
    });
});
