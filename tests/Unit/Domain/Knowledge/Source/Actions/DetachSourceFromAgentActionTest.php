<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Actions\DetachSourceFromAgentAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Detaching a shared source removes this agent's link and keeps the source
 * for the other agents.
 */
it('detaches a source without deleting it', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $website = SourceWebsite::factory()->ingested()->attachedTo($agent)->create();
    $other->websites()->attach($website->id);

    app(DetachSourceFromAgentAction::class)->execute($agent, $website);

    expect($agent->websites()->whereKey($website->id)->exists())->toBeFalse()
        ->and($other->websites()->whereKey($website->id)->exists())->toBeTrue()
        ->and(SourceWebsite::query()->find($website->id))->not->toBeNull();
});
