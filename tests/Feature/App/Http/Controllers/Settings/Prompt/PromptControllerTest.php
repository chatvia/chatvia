<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Models\AgentPrompt;

/**
 * The workspace prompt library (PromptController): prompts belong to the
 * workspace, so a teammate with account.prompts creates them like the owner.
 * A name is unique in the workspace, on create and on rename.
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
