<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;

/**
 * Regression guard for H2: the model previously declared
 * belongsToMany('agent_youtube_video', ...) but no migration created that
 * pivot table, so any attach/detach raised SQLSTATE[42P01]. The relation is
 * now polymorphic via the shared `agent_sources` table that SourceFile /
 * SourceWebsite / SourceText already use.
 */
it('attaches a YouTube video to an agent via the polymorphic agent_sources table', function (): void {
    $agent = Agent::factory()->create();

    /** @var SourceYoutubeVideo $video */
    $video = SourceYoutubeVideo::create([
        'workspace_id' => $agent->workspace_id,
        'url' => 'https://www.youtube.com/watch?v=test',
        'length' => 1,
        'status' => 'pending',
    ]);

    $video->agents()->attach($agent->id);

    expect($video->agents()->count())->toBe(1)
        ->and($video->agents()->first()->id)->toBe($agent->id);
});

it('detaches a YouTube video from an agent (used by the observer on delete)', function (): void {
    $agent = Agent::factory()->create();

    /** @var SourceYoutubeVideo $video */
    $video = SourceYoutubeVideo::create([
        'workspace_id' => $agent->workspace_id,
        'url' => 'https://www.youtube.com/watch?v=detach',
        'length' => 1,
        'status' => 'pending',
    ]);
    $video->agents()->attach($agent->id);

    $video->agents()->detach();

    expect($video->agents()->count())->toBe(0);
});
