<?php

use App\Identity\Workspace\Models\User;

/**
 * POST agents.sources.youtube.store adds a video as JSON. A link the action
 * refuses (AddAgentYoutubeVideoActionTest) answers as a validation error on
 * `url`, so the dashboard shows it under the field.
 */
it('adds a video as JSON and answers a refused link with a url field error', function (): void {
    config(['chatvia.features.youtube_sources' => true]);
    $user = User::factory()->create();
    $agent = makeTeamAgent($user, 'Support Bot');
    $route = route('agents.sources.youtube.store', ['agent' => $agent->external_id]);

    $this->actingAs($user)
        ->postJson($route, ['url' => 'https://youtu.be/abcdefghijk'])
        ->assertOk()
        ->assertJsonStructure(['message']);

    // Passes the request's URL check but holds no video id.
    $this->actingAs($user)
        ->postJson($route, ['url' => 'https://www.youtube.com/channel'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url' => 'Could not extract video ID from URL.']);
});
