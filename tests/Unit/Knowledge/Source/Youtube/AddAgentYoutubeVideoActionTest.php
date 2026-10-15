<?php

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Youtube\Actions\AddAgentYoutubeVideoAction;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Support\Exceptions\DomainValidationException;

/**
 * Adding a YouTube video writes the video row and its agent link in one
 * transaction. A URL without a video id, or one the agent already has, is
 * refused before anything is written.
 */
it('adds and attaches a YouTube video', function (): void {
    $agent = Agent::factory()->create();

    $video = app(AddAgentYoutubeVideoAction::class)->execute($agent, 'https://youtu.be/dQw4w9WgXcQ');

    expect($agent->youtubeVideos()->whereKey($video->id)->exists())->toBeTrue()
        ->and($video->workspace_id)->toBe($agent->workspace_id)
        ->and($video->status)->toBe(SourceStatus::Pending);
});

it('rolls back the YouTube video row when attaching it fails', function (): void {
    $agent = Agent::factory()->create();

    SourceYoutubeVideo::created(function (): void {
        throw new RuntimeException('database unavailable');
    });

    expect(fn (): SourceYoutubeVideo => app(AddAgentYoutubeVideoAction::class)->execute(
        $agent,
        'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
    ))->toThrow(RuntimeException::class);

    expect(SourceYoutubeVideo::query()->where('workspace_id', $agent->workspace_id)->exists())->toBeFalse();
});

it('refuses a URL without a video id', function (string $url): void {
    $agent = Agent::factory()->create();

    expect(fn (): SourceYoutubeVideo => app(AddAgentYoutubeVideoAction::class)->execute($agent, $url))
        ->toThrow(DomainValidationException::class, 'Could not extract video ID from URL.');

    expect(SourceYoutubeVideo::query()->where('workspace_id', $agent->workspace_id)->exists())->toBeFalse();
})->with([
    'another site' => ['https://vimeo.com/123456789'],
    'a channel page' => ['https://www.youtube.com/@chatvia'],
    'an id too short' => ['https://youtu.be/abc'],
]);

it('refuses a video the agent already has', function (): void {
    $agent = Agent::factory()->create();
    $url = 'https://www.youtube.com/watch?v=dQw4w9WgXcQ';
    app(AddAgentYoutubeVideoAction::class)->execute($agent, $url);

    expect(fn (): SourceYoutubeVideo => app(AddAgentYoutubeVideoAction::class)->execute($agent, $url))
        ->toThrow(DomainValidationException::class, 'This YouTube video has already been added to this agent.');

    expect($agent->youtubeVideos()->count())->toBe(1);
});
