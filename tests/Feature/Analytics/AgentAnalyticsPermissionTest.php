<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

dataset('analytics routes', [
    'overview data' => ['agent.analytics.chats', 'agents.analytics.data'],
    'chart data' => ['agent.analytics.chats', 'agents.analytics.chart-data'],
    'sources data' => ['agent.analytics.sources', 'agents.analytics.sources.data'],
]);

it('requires the matching analytics permission', function (string $permission, string $route): void {
    $blocked = teammateWithPermissions($this->owner, ['agent.chat.view']);
    $allowed = teammateWithPermissions($this->owner, [$permission]);

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
