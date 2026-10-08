<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserQuota;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Membership\Team\Services\TeamService;

/**
 * Controllers no longer hand-translate DomainValidationException: the global
 * mapping in bootstrap/app.php turns it into a field error for web forms and a
 * 422 with the same `message` for JSON callers.
 */
it('turns a domain rejection into a field error on a web form', function (): void {
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);
    $owner->billing()->firstOrCreate([]);
    UserQuota::firstOrCreate(['user_id' => $owner->id], config('billing.packages.starter.quota_limits.fixed', []));

    $team = $owner->primaryTeam();
    $this->actingAs($owner)->post(route('teams.create-role', $team), ['name' => 'Support', 'all_agents' => true]);

    $this->actingAs($owner)
        ->from('/previous')
        ->post(route('teams.create-role', $team), ['name' => 'Support', 'all_agents' => true])
        ->assertRedirect('/previous')
        ->assertSessionHasErrors(['name' => 'A role with this name already exists.']);
});

it('turns a domain rejection into a 422 with the same message for JSON callers', function (): void {
    $agent = proPlanAgent();
    $topic = Topic::create(['user_id' => $agent->user_id, 'topic' => 'Billing', 'description' => 'x']);
    $agent->topics()->attach($topic->id);

    $this->actingAs($agent->user)
        ->postJson(route('agents.topics.store', $agent), ['topic_id' => $topic->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Topic already associated with this agent')
        ->assertJsonValidationErrors(['topic_id' => 'Topic already associated with this agent']);
});
