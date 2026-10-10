<?php

use App\Identity\Workspace\Models\User;
use Domain\Form\Models\FormDefinition;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

/**
 * Pins the per-agent permission gate (the `agent.permission:<perm>` route
 * middleware) on the dashboard source routes.
 *
 * A teammate WITH agent access but WITHOUT the permission must get 403; the owner
 * and a teammate WITH the permission must pass. Both teammates share the same
 * (all-agents) access and differ only in the permission, so the 403-vs-pass
 * contrast isolates the permission gate — not agent access.
 */
function teammateWithPermissions(User $owner, array $permissions): User
{
    $team = $owner->primaryTeam();

    $role = app(CreateTeamRoleAction::class)->execute(new SaveTeamRoleData(
        team: $team,
        name: 'Role-'.Str::random(8),
        permissions: $permissions,
        allAgents: true,
        agentExternalIds: [],
    ));

    $member = User::factory()->employee($owner)->create(['current_team_id' => $team->id]);
    $team->members()->attach($member->id, ['role_id' => $role->id]);

    return $member;
}

beforeEach(function (): void {
    $this->owner = User::factory()->create();

    // Factory users start bare — create the default team explicitly.
    app(TeamService::class)->createDefaultTeam($this->owner);

    $this->agent = createAgent($this->owner);
});

// [permission, index route name, store route name]
dataset('training routes', [
    'text' => ['agent.sources.train.text', 'agents.sources.text.index', 'agents.sources.text.store'],
    'qa' => ['agent.sources.train.qa', 'agents.sources.qa.index', 'agents.sources.qa.store'],
    'web' => ['agent.sources.train.web', 'agents.sources.websites.index', 'agents.sources.websites.store'],
]);

it('gates source-training index/store routes by their permission', function (string $perm, string $indexRoute, string $storeRoute): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, [$perm]);

    // index (read): blocked 403, allowed 200
    $this->actingAs($blocked)->getJson(route($indexRoute, $this->agent))->assertForbidden();
    $this->actingAs($allowed)->getJson(route($indexRoute, $this->agent))->assertOk();

    // store: the permission gate runs before validation, so a blocked teammate
    // gets 403 even with an empty body; a permitted one passes the gate and then
    // fails validation (422).
    $this->actingAs($blocked)->postJson(route($storeRoute, $this->agent), [])->assertForbidden();
    $this->actingAs($allowed)->postJson(route($storeRoute, $this->agent), [])->assertStatus(422);
})->with('training routes');

it('gates the qa delete route by agent.sources.delete', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.sources.delete']);

    // {id} is a plain int, so route binding doesn't 404 before the gate runs.
    $this->actingAs($blocked)
        ->deleteJson(route('agents.sources.qa.destroy', [$this->agent, 999999]))
        ->assertForbidden();

    // permitted passes the gate; the controller's findOrFail then 404s.
    $this->actingAs($allowed)
        ->deleteJson(route('agents.sources.qa.destroy', [$this->agent, 999999]))
        ->assertNotFound();
});

it('gates the submissions list and bulk-delete by agent.leads.view', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.leads.view']);
    $form = FormDefinition::factory()->forAgent($this->agent)->create();

    $this->actingAs($blocked)->getJson(route('agents.forms.submissions.index', [$this->agent, $form]))->assertForbidden();
    $this->actingAs($allowed)->getJson(route('agents.forms.submissions.index', [$this->agent, $form]))->assertOk();

    // Empty body → nothing deleted → the controller redirects back (302); the
    // contrast with the blocked 403 isolates the permission gate.
    $this->actingAs($blocked)->deleteJson(route('agents.forms.submissions.destroy', [$this->agent, $form]), [])->assertForbidden();
    $this->actingAs($allowed)->deleteJson(route('agents.forms.submissions.destroy', [$this->agent, $form]), [])->assertStatus(302);
});

it('gates leads settings by agent.leads.settings', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.leads.settings']);

    $this->actingAs($blocked)->patchJson(route('agents.leads.settings', $this->agent), [])->assertForbidden();
    $this->actingAs($allowed)->patchJson(route('agents.leads.settings', $this->agent), [])->assertStatus(422);
});

it('gates submissions export by agent.leads.export', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.leads.export']);
    $form = FormDefinition::factory()->forAgent($this->agent)->create();

    $this->actingAs($blocked)->get(route('agents.forms.submissions.export', [$this->agent, $form]))->assertForbidden();
    $this->actingAs($allowed)->get(route('agents.forms.submissions.export', [$this->agent, $form]))->assertOk();
});

it('gates the chat-log conversations list by agent.chat.view', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.chat.view']);

    $this->actingAs($blocked)->getJson(route('agents.logs.conversations', $this->agent))->assertForbidden();
    $this->actingAs($allowed)->getJson(route('agents.logs.conversations', $this->agent))->assertOk();
});

it('gates conversation deletion by agent.chat.delete', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.chat.delete']);

    // {conversation} is a string param looked up (by external_id, a uuid) AFTER
    // the gate. A valid-but-nonexistent uuid keeps blocked at the gate (403) and
    // lets allowed pass it, then 404 on the lookup.
    $missing = '11111111-1111-1111-1111-111111111111';
    $this->actingAs($blocked)
        ->deleteJson(route('agents.logs.delete-conversation', [$this->agent, $missing]))
        ->assertForbidden();
    $this->actingAs($allowed)
        ->deleteJson(route('agents.logs.delete-conversation', [$this->agent, $missing]))
        ->assertNotFound();
});

it('gates file processing by agent.sources.train.files', function (): void {
    $blocked = teammateWithPermissions($this->owner, []);
    $allowed = teammateWithPermissions($this->owner, ['agent.sources.train.files']);

    $this->actingAs($blocked)->postJson(route('agents.sources.files.process', $this->agent))->assertForbidden();
    $this->actingAs($allowed)->postJson(route('agents.sources.files.process', $this->agent))->assertOk();
});

it('lets the owner through (owners hold every permission)', function (): void {
    $this->actingAs($this->owner)
        ->getJson(route('agents.sources.text.index', $this->agent))
        ->assertOk();
});
