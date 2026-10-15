<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\Enums\SourceStatus;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Domain\Membership\Team\Services\TeamService;
use Inertia\Testing\AssertableInertia;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * YouTube sources are unfinished: added videos are saved but never ingested.
 * The install-wide `chatvia.features.youtube_sources` switch (default off)
 * hides the routes (404) and the dashboard entry point, while existing rows
 * stay in the database untouched.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->user);
    $this->user->refresh();

    $this->agent = makeTeamAgent($this->user, 'Support Bot');

    $this->video = SourceYoutubeVideo::create([
        'workspace_id' => $this->user->workspace()->id,
        'url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        'length' => 0,
        'status' => SourceStatus::Pending->value,
    ]);
    $this->agent->youtubeVideos()->attach($this->video->id);
});

it('answers 404 on every YouTube source route while the feature is off', function (): void {
    config(['chatvia.features.youtube_sources' => false]);
    $params = ['agent' => $this->agent->external_id, 'youtube' => $this->video->id];

    $this->actingAs($this->user)->getJson(route('agents.sources.youtube.index', $params))->assertNotFound();
    $this->actingAs($this->user)->get(route('agents.sources.youtube.index', $params))->assertNotFound();
    $this->actingAs($this->user)
        ->postJson(route('agents.sources.youtube.store', $params), ['url' => 'https://youtu.be/abcdefghijk'])
        ->assertNotFound();
    $this->actingAs($this->user)->deleteJson(route('agents.sources.youtube.destroy', $params))->assertNotFound();
    $this->actingAs($this->user)->postJson(route('agents.sources.youtube.retrain', $params))->assertNotFound();

    expect(SourceYoutubeVideo::query()->whereKey($this->video->id)->exists())->toBeTrue()
        ->and(SourceYoutubeVideo::query()->count())->toBe(1);
});

it('serves the YouTube source routes when the feature is on', function (): void {
    config(['chatvia.features.youtube_sources' => true]);

    $this->actingAs($this->user)
        ->getJson(route('agents.sources.youtube.index', ['agent' => $this->agent->external_id]))
        ->assertOk()
        ->assertJsonPath('0.id', $this->video->id);
});

it('shares the switch with the dashboard so the entry point can hide', function (bool $enabled): void {
    config(['chatvia.features.youtube_sources' => $enabled]);

    $this->actingAs($this->user)
        ->get(route('agents.sources', ['agent' => $this->agent->external_id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page): AssertableInertia => $page->where('features.youtube_sources', $enabled));
})->with([
    'off' => false,
    'on' => true,
]);

it('adds a video as JSON and answers a refused link with a url field error', function (): void {
    config(['chatvia.features.youtube_sources' => true]);
    $route = route('agents.sources.youtube.store', ['agent' => $this->agent->external_id]);

    $this->actingAs($this->user)
        ->postJson($route, ['url' => 'https://youtu.be/abcdefghijk'])
        ->assertOk()
        ->assertJsonStructure(['message']);

    // Passes the request's URL check but holds no video id.
    $this->actingAs($this->user)
        ->postJson($route, ['url' => 'https://www.youtube.com/channel'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url' => 'Could not extract video ID from URL.']);

    $this->actingAs($this->user)
        ->postJson($route, ['url' => $this->video->url])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');

    expect($this->agent->youtubeVideos()->count())->toBe(2);
});
