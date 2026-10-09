<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Youtube\Actions\DeleteAgentYoutubeVideoAction;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;

/**
 * Regression: retrain() and DeleteAgentYoutubeVideoAction filtered chunks by
 * a nonexistent chunks.agent_id column — Postgres validates columns at parse
 * time, so both endpoints threw SQLSTATE 42703 on EVERY call (even with zero
 * chunks). retrain() also reset a nonexistent error_message column (the real
 * column is failure_reason). Chunks are per-source; the agent filter is gone.
 */
function retrainTestVideo(Agent $agent): SourceYoutubeVideo
{
    return SourceYoutubeVideo::create([
        'user_id' => $agent->user_id,
        'url' => 'https://www.youtube.com/watch?v='.uniqid(),
        'length' => 1,
        'status' => SourceStatus::Completed->value,
        'failure_reason' => 'stale error',
    ]);
}

it('retrains without referencing a nonexistent agent_id column on chunks', function (): void {
    $agent = Agent::factory()->create();
    $video = retrainTestVideo($agent);

    $video->retrain();

    $video->refresh();
    expect($video->status)->toBe(SourceStatus::Pending)
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
    $other = Agent::factory()->create(['user_id' => $agent->user_id]);
    $video = retrainTestVideo($agent);
    $video->agents()->attach([$agent->id, $other->id]);

    app(DeleteAgentYoutubeVideoAction::class)->execute($agent, $video);

    expect(SourceYoutubeVideo::find($video->id))->not->toBeNull()
        ->and($video->agents()->pluck('agents.id')->all())->toBe([$other->id]);
});
