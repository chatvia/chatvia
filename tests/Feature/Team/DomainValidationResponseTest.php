<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Models\Topic;
use Domain\Membership\Team\Models\Role;
use Domain\Membership\Team\Services\TeamService;

/**
 * Controllers no longer hand-translate DomainValidationException: the global
 * mapping in bootstrap/app.php turns it into a field error for web forms and a
 * 422 with the same `message` for JSON callers.
 */
it('turns a domain rejection into a field error on a web form', function (): void {
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    $team = $owner->primaryTeam();
    $this->actingAs($owner)->post(route('teams.create-role', $team), ['name' => 'Support', 'all_agents' => true]);

    $this->actingAs($owner)
        ->from('/previous')
        ->post(route('teams.create-role', $team), ['name' => 'Support', 'all_agents' => true])
        ->assertRedirect('/previous')
        ->assertSessionHasErrors(['name' => 'A role with this name already exists.']);
});

it('turns a domain rejection into a 422 with the same message for JSON callers', function (): void {
    $agent = Agent::factory()->create();
    $topic = Topic::create(['workspace_id' => $agent->workspace_id, 'topic' => 'Billing', 'description' => 'x']);
    $agent->topics()->attach($topic->id);

    $this->actingAs($agent->workspace->owner)
        ->postJson(route('agents.topics.store', $agent), ['topic_id' => $topic->id])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'Topic already associated with this agent')
        ->assertJsonValidationErrors(['topic_id' => 'Topic already associated with this agent']);
});

it('refuses to delete a role a member still holds with a field error', function (): void {
    $owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($owner);

    $team = $owner->primaryTeam();
    $this->actingAs($owner)->post(route('teams.create-role', $team), ['name' => 'Support', 'all_agents' => true]);
    $role = Role::query()->where('team_id', $team->id)->where('name', 'Support')->firstOrFail();
    $team->members()->attach(User::factory()->create()->id, ['role_id' => $role->id]);

    $this->actingAs($owner)
        ->from('/previous')
        ->delete(route('teams.delete-role', ['team' => $team, 'roleId' => $role->id]))
        ->assertRedirect('/previous')
        ->assertSessionHasErrors(['role' => 'Cannot delete this role. 1 member(s) are assigned to it.']);

    expect(Role::query()->whereKey($role->id)->exists())->toBeTrue();
});
