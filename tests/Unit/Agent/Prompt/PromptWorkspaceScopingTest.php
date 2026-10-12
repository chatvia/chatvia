<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Actions\CreatePromptAction;
use Domain\Agent\Prompt\Actions\ResolveAgentPromptIdAction;
use Domain\Agent\Prompt\Actions\UpdatePromptAction;
use Domain\Agent\Prompt\Data\CreatePromptData;
use Domain\Agent\Prompt\Data\UpdatePromptData;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Support\Exceptions\DomainValidationException;

/**
 * Reusable prompts belong to the workspace (CreatePromptAction stores them on
 * the acting user's workspace). Every other read/write must use the same key, or a
 * teammate can create a prompt they can then never edit or attach.
 */
function workspacePrompt(User $owner, string $name = 'Persona'): AgentPrompt
{
    return $owner->workspace()->prompts()->create(['name' => $name, 'prompt' => 'Be helpful.']);
}

it('stores a prompt a teammate creates on the shared workspace', function (): void {
    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();

    $prompt = app(CreatePromptAction::class)->execute(new CreatePromptData(
        user: $teammate,
        name: 'Team persona',
        prompt: 'Be kind.',
    ));

    expect($prompt->workspace_id)->toBe($owner->workspace()->id);
});

it('lets a teammate update a prompt owned by their workspace', function (): void {
    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();
    $prompt = workspacePrompt($owner);

    $updated = app(UpdatePromptAction::class)->execute(
        $teammate,
        $prompt->external_id,
        new UpdatePromptData(name: 'Renamed', prompt: 'New body'),
    );

    expect($updated->is($prompt))->toBeTrue()
        ->and($prompt->fresh()->name)->toBe('Renamed')
        ->and($prompt->fresh()->workspace_id)->toBe($owner->workspace()->id);
});

it('does not let a user update another workspace\'s prompt', function (): void {
    $prompt = workspacePrompt(User::factory()->create());

    expect(fn () => app(UpdatePromptAction::class)->execute(
        User::factory()->create(),
        $prompt->external_id,
        new UpdatePromptData(name: 'Hijack', prompt: 'x'),
    ))->toThrow(ModelNotFoundException::class);
});

it('resolves a workspace prompt id for the owner and for a teammate', function (): void {
    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();
    $prompt = workspacePrompt($owner);

    $resolver = app(ResolveAgentPromptIdAction::class);

    expect($resolver->execute($owner->workspace(), $prompt->external_id))->toBe($prompt->id)
        ->and($resolver->execute($teammate->workspace(), $prompt->external_id))->toBe($prompt->id)
        ->and($resolver->execute($teammate->workspace(), null))->toBeNull();
});

it('refuses to resolve a prompt from another workspace', function (): void {
    $prompt = workspacePrompt(User::factory()->create());

    expect(fn () => app(ResolveAgentPromptIdAction::class)->execute(User::factory()->create()->workspace(), $prompt->external_id))
        ->toThrow(DomainValidationException::class);
});
