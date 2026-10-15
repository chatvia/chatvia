<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Youtube\Actions\DeleteAgentYoutubeVideoAction;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;

/**
 * A retrain clears every chunk of the video, the summary chunk (index 0)
 * included, and puts it back to pending. Removing a video from an agent
 * deletes it only when no other agent uses it.
 */
function retrainTestVideo(Agent $agent): SourceYoutubeVideo
{
    return SourceYoutubeVideo::create([
        'workspace_id' => $agent->workspace_id,
        'url' => 'https://www.youtube.com/watch?v='.uniqid(),
        'length' => 1,
        'status' => SourceStatus::Completed->value,
        'failure_reason' => 'stale error',
    ]);
}

it('retrains by deleting every chunk, the summary included, and waiting again', function (): void {
    $agent = Agent::factory()->create();
    $video = retrainTestVideo($agent);
    Chunk::factory()->forSource($video)->summary()->create();
    Chunk::factory()->forSource($video)->create();
    $otherVideo = Chunk::factory()->forSource(retrainTestVideo($agent))->create();

    $video->retrain();

    $video->refresh();
    expect(Chunk::query()->ofSource($video)->exists())->toBeFalse()
        ->and(Chunk::query()->find($otherVideo->id))->not->toBeNull()
        ->and($video->status)->toBe(SourceStatus::Pending)
        ->and($video->failure_reason)->toBeNull();
});

it('deletes the video when the deleting agent is its only user', function (): void {
    $agent = Agent::factory()->create();
    $video = retrainTestVideo($agent);
    $video->agents()->attach($agent->id);

    app(DeleteAgentYoutubeVideoAction::class)->execute($agent, $video);

    expect(SourceYoutubeVideo::find($video->id))->toBeNull();
});

it('only detaches when another agent still uses the video', function (): void {
    $agent = Agent::factory()->create();
    $other = Agent::factory()->create(['workspace_id' => $agent->workspace_id]);
    $video = retrainTestVideo($agent);
    $video->agents()->attach([$agent->id, $other->id]);

    app(DeleteAgentYoutubeVideoAction::class)->execute($agent, $video);

    expect(SourceYoutubeVideo::find($video->id))->not->toBeNull()
        ->and($video->agents()->pluck('agents.id')->all())->toBe([$other->id]);
});
