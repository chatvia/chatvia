<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Actions\Roles\CreateTeamRoleAction;
use Domain\Membership\Team\Data\SaveTeamRoleData;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

/**
 * Regression: the agent analytics pages and their data endpoints were open to
 * any teammate who could reach the agent, even without the analytics
 * permission the agent menu checks. Each one now requires its own
 * `agent.analytics.*` permission on the route.
 */
function analyticsTeammate(User $owner, array $permissions): User
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
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->billing()->firstOrCreate([]);
    $this->owner->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_active',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'quantity' => 1,
    ]);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

dataset('analytics routes', [
    'overview data' => ['agent.analytics.chats', 'agents.analytics.data'],
    'chart data' => ['agent.analytics.chats', 'agents.analytics.chart-data'],
    'sources data' => ['agent.analytics.sources', 'agents.analytics.sources.data'],
]);

it('requires the matching analytics permission', function (string $permission, string $route): void {
    $blocked = analyticsTeammate($this->owner, ['agent.chat.view']);
    $allowed = analyticsTeammate($this->owner, [$permission]);

    $this->actingAs($blocked)->getJson(route($route, $this->agent))->assertForbidden();
    $this->actingAs($allowed)->getJson(route($route, $this->agent))->assertOk();
    $this->actingAs($this->owner)->getJson(route($route, $this->agent))->assertOk();
})->with('analytics routes');

it('rejects an end date before the start date', function (): void {
    $this->actingAs($this->owner)
        ->getJson(route('agents.analytics.data', [$this->agent, 'start_date' => '2026-06-10', 'end_date' => '2026-06-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end_date');
});
