<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Actions\AttachSourceToAgentAction;

/**
 * Agents share a workspace's trained sources: attaching adds the agent link
 * once, and a second attach is refused.
 */
it('attaches a source once', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['workspace_id' => $agent->workspace_id]);

    $attach = app(AttachSourceToAgentAction::class);

    expect($attach->execute($agent, $file))->toBeTrue()
        ->and($attach->execute($agent, $file))->toBeFalse()
        ->and($agent->files()->whereKey($file->id)->count())->toBe(1);
});
