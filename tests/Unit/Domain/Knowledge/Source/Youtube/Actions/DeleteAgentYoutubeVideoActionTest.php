<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Youtube\Actions\DeleteAgentYoutubeVideoAction;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;

/**
 * Removing a video from an agent deletes it only when no other agent uses
 * it; otherwise only this agent's link goes.
 */
it('deletes the video when the deleting agent is its only user', function (): void {
    $agent = Agent::factory()->create();
    $video = SourceYoutubeVideo::factory()->ingested()->attachedTo($agent)->create();

    app(DeleteAgentYoutubeVideoAction::class)->execute($agent, $video);

    expect(SourceYoutubeVideo::query()->find($video->id))->toBeNull();
});

it('only detaches when another agent still uses the video', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $video = SourceYoutubeVideo::factory()->ingested()->attachedTo($agent)->create();
    $video->agents()->attach($other->id);

    app(DeleteAgentYoutubeVideoAction::class)->execute($agent, $video);

    expect(SourceYoutubeVideo::query()->find($video->id))->not->toBeNull()
        ->and($video->agents()->pluck('agents.id')->all())->toBe([$other->id]);
});
