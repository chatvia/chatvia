<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Actions\AttachSourceToAgentAction;
use Domain\Knowledge\Source\Actions\DetachSourceFromAgentAction;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;

/**
 * Agents share a workspace's trained sources: attaching adds the agent link
 * once (a second attach is refused), detaching removes the link and keeps the
 * source for the other agents.
 */
it('attaches a source once', function (): void {
    $agent = Agent::factory()->create();
    $file = AgentFileFactory::new()->create(['user_id' => $agent->user_id]);

    $attach = app(AttachSourceToAgentAction::class);

    expect($attach->execute($agent, $file))->toBeTrue()
        ->and($attach->execute($agent, $file))->toBeFalse()
        ->and($agent->files()->whereKey($file->id)->count())->toBe(1);
});

it('detaches a source without deleting it', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['user_id' => $agent->user_id]);
    $website = SourceWebsite::query()->create(['user_id' => $agent->user_id, 'url' => 'https://docs.example.test', 'status' => 'ingested']);
    $agent->websites()->attach($website->id);
    $other->websites()->attach($website->id);

    app(DetachSourceFromAgentAction::class)->execute($agent, $website);

    expect($agent->websites()->whereKey($website->id)->exists())->toBeFalse()
        ->and($other->websites()->whereKey($website->id)->exists())->toBeTrue()
        ->and(SourceWebsite::query()->find($website->id))->not->toBeNull();
});
