<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Prompt\Actions\ResolveAgentPromptIdAction;
use Domain\Agent\Prompt\Models\AgentPrompt;
use Support\Exceptions\DomainValidationException;

/**
 * A prompt's external id resolves to its key only inside its own workspace,
 * for the owner and every teammate alike; another workspace's prompt is a
 * `prompt_id` error.
 */
it('resolves a workspace prompt id for the owner and for a teammate', function (): void {
    $owner = User::factory()->create();
    $teammate = User::factory()->employee($owner)->create();
    $prompt = AgentPrompt::factory()->create(['workspace_id' => $owner->workspace()->id]);

    $resolver = app(ResolveAgentPromptIdAction::class);

    expect($resolver->execute($owner->workspace(), $prompt->external_id))->toBe($prompt->id)
        ->and($resolver->execute($teammate->workspace(), $prompt->external_id))->toBe($prompt->id)
        ->and($resolver->execute($teammate->workspace(), null))->toBeNull();
});

it('refuses to resolve a prompt from another workspace', function (): void {
    $prompt = AgentPrompt::factory()->create();

    expect(fn () => app(ResolveAgentPromptIdAction::class)->execute(User::factory()->create()->workspace(), $prompt->external_id))
        ->toThrow(DomainValidationException::class);
});
