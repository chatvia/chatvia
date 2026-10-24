<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Models\AgentPrompt;

/**
 * The workspace prompt library (PromptController): prompts belong to the
 * workspace, so a teammate with account.prompts creates, lists, renames and
 * deletes them like the owner. A name is unique in the workspace, and
 * deleting a prompt detaches it from the agents that used it.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    ownerTeam($this->owner);
    $this->teammate = teammateWithPermissions($this->owner, ['account.prompts']);
});

it('stores a teammate\'s prompt under the workspace', function (): void {
    $this->actingAs($this->teammate)
        ->post(route('library.prompts.store'), ['name' => 'Team persona', 'prompt' => 'Be kind.'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(AgentPrompt::query()->where('name', 'Team persona')->sole())
        ->workspace_id->toBe($this->owner->workspace()->id)
        ->prompt->toBe('Be kind.');
});

it('validates the required fields on create', function (): void {
    $this->actingAs($this->owner)
        ->post(route('library.prompts.store'), ['name' => '', 'prompt' => ''])
        ->assertSessionHasErrors(['name', 'prompt']);
});

it('rejects a name the workspace already uses', function (string $write): void {
    AgentPrompt::factory()->create(['workspace_id' => $this->owner->workspace()->id, 'name' => 'Taken']);
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->owner->workspace()->id, 'name' => 'Mine']);

    $this->actingAs($this->teammate)
        ->from(route('library.prompts.edit'))
        ->{$write === 'create' ? 'post' : 'patch'}(
            $write === 'create' ? route('library.prompts.store') : route('library.prompts.update', ['prompt' => $prompt->external_id]),
            ['name' => 'Taken', 'prompt' => 'x'],
        )
        ->assertSessionHasErrors('name');

    expect(AgentPrompt::query()->where('workspace_id', $this->owner->workspace()->id)->pluck('name')->sort()->values()->all())->toBe(['Mine', 'Taken']);
})->with([
    'on create' => 'create',
    'on rename' => 'rename',
]);

it('lists the workspace\'s prompts to a teammate', function (): void {
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->owner->workspace()->id]);

    $this->actingAs($this->teammate)
        ->getJson(route('library.prompts.list'))
        ->assertOk()
        ->assertJsonPath('data.0.id', $prompt->external_id);
});

it('lets a teammate rename and rewrite a workspace prompt', function (): void {
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->owner->workspace()->id]);

    $this->actingAs($this->teammate)
        ->patch(route('library.prompts.update', ['prompt' => $prompt->external_id]), [
            'name' => 'Edited by teammate',
            'prompt' => 'New body',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($prompt->fresh())
        ->name->toBe('Edited by teammate')
        ->prompt->toBe('New body');
});

it('lets a teammate delete a workspace prompt, which detaches it from its agents', function (): void {
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $this->owner->workspace()->id]);
    $agent = createAgent($this->owner, ['agent_prompt_id' => $prompt->id]);

    $this->actingAs($this->teammate)
        ->delete(route('library.prompts.destroy', ['prompt' => $prompt->external_id]))
        ->assertRedirect();

    expect(AgentPrompt::query()->whereKey($prompt->id)->exists())->toBeFalse()
        ->and($agent->fresh()->agent_prompt_id)->toBeNull();
});
