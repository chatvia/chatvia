<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Actions\UpdatePromptAction;
use Domain\Agent\Prompt\Data\UpdatePromptData;
use Domain\Agent\Prompt\Models\AgentPrompt;

/**
 * Editing a reusable prompt changes it in place and keeps it on its
 * workspace. Which prompts a person may edit is decided on the route
 * (`{prompt}` binding + `workspace.scope`).
 */
it('updates a workspace prompt in place', function (): void {
    $owner = User::factory()->create();
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $owner->workspace()->id]);

    $updated = app(UpdatePromptAction::class)->execute($prompt, new UpdatePromptData(name: 'Renamed', prompt: 'New body'));

    expect($updated->is($prompt))->toBeTrue()
        ->and($prompt->fresh())
        ->name->toBe('Renamed')
        ->prompt->toBe('New body')
        ->workspace_id->toBe($owner->workspace()->id);
});
