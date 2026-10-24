<?php

use App\Identity\Workspace\Models\User;
use Domain\Knowledge\Source\Youtube\Models\SourceYoutubeVideo;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * YouTube sources are unfinished: added videos are saved but never ingested.
 * The install-wide `chatvia.features.youtube_sources` switch (default off)
 * hides the routes of AgentYoutubeController and RetrainAgentYoutubeController
 * (404) and the entry point on the Sources page, while existing rows stay in
 * the database untouched.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->agent = makeTeamAgent($this->user, 'Support Bot');
    $this->video = SourceYoutubeVideo::factory()->attachedTo($this->agent)->create();
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
        ->and(SourceYoutubeVideo::query()->where('workspace_id', $this->agent->workspace_id)->count())->toBe(1);
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
        ->assertInertia(fn (Assert $page): Assert => $page->where('features.youtube_sources', $enabled));
})->with([
    'off' => false,
    'on' => true,
]);
