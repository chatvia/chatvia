<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;

/**
 * Controllers leave DomainValidationException to the global mapping in
 * bootstrap/app.php, which turns it into a field error for web forms and a
 * 422 with the same `message` for JSON callers.
 */
it('turns a domain rejection into a field error on a web form', function (): void {
    $owner = User::factory()->create();
    $team = ownerTeam($owner);
    teamRole($team, 'Support');

    $this->actingAs($owner)
        ->from('/previous')
        ->post(route('teams.create-role', $team), ['name' => 'Support', 'all_agents' => true])
        ->assertRedirect('/previous')
        ->assertSessionHasErrors(['name' => 'A role with this name already exists.']);
});

it('turns a domain rejection into a 422 with the same message for JSON callers', function (): void {
    $agent = Agent::factory()->create();
    $topic = Topic::factory()->attachedTo($agent)->create();

    $this->actingAs($agent->workspace->owner)
        ->postJson(route('agents.topics.store', $agent), ['topic_id' => $topic->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Topic already associated with this agent')
        ->assertJsonValidationErrors(['topic_id' => 'Topic already associated with this agent']);
});
