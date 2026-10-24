<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Actions\CreatePromptAction;
use Domain\Agent\Prompt\Data\CreatePromptData;

/**
 * A reusable prompt belongs to the workspace, never to the teammate who wrote
 * it, so every teammate can edit and attach it.
 */
it('stores a prompt a teammate creates on the shared workspace', function (): void {
    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();

    $prompt = app(CreatePromptAction::class)->execute(new CreatePromptData(
        user: $teammate,
        name: 'Team persona',
        prompt: 'Be kind.',
    ));

    expect($prompt->workspace_id)->toBe($owner->workspace()->id)
        ->and($prompt->name)->toBe('Team persona');
});
