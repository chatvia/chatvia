<?php

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Str;

beforeEach(function (): void {
    $this->owner = User::factory()->create();
    app(TeamService::class)->createDefaultTeam($this->owner);
    $this->owner->refresh();

    $this->agent = createAgent($this->owner);
});

// Every analytics page and its data (routes/agents.php), with the permission
// the agent menu also uses to show the page.
dataset('analytics routes', [
    'overview page' => ['agent.analytics.chats', 'agents.analytics'],
    'overview data' => ['agent.analytics.chats', 'agents.analytics.data'],
    'chart data' => ['agent.analytics.chats', 'agents.analytics.chart-data'],
    'sentiment page' => ['agent.analytics.sentiment', 'agents.analytics.sentiment'],
    'sentiment data' => ['agent.analytics.sentiment', 'agents.sentiment.data'],
    'tone page' => ['agent.analytics.tone', 'agents.analytics.tone'],
    'tone data' => ['agent.analytics.tone', 'agents.tone.data'],
    'topics page' => ['agent.analytics.topics', 'agents.analytics.topics'],
    'topics data' => ['agent.analytics.topics', 'agents.topics.data'],
    'topic suggestions' => ['agent.analytics.topics', 'agents.topics.suggestions'],
    'sources page' => ['agent.analytics.sources', 'agents.analytics.sources'],
    'sources data' => ['agent.analytics.sources', 'agents.analytics.sources.data'],
]);

it('requires the matching analytics permission', function (string $permission, string $route): void {
    // Every other analytics permission, so only the one under test is missing.
    $others = array_values(array_filter(
        array_keys(config('team-permissions.permissions')),
        fn (string $code): bool => Str::startsWith($code, 'agent.analytics.') && $code !== $permission,
    ));
    $blocked = teammateWithPermissions($this->owner, ['agent.chat.view', ...$others]);
    $allowed = teammateWithPermissions($this->owner, [$permission]);

    $this->actingAs($blocked)->get(route($route, $this->agent))->assertForbidden();
    $this->actingAs($allowed)->get(route($route, $this->agent))->assertOk();
    $this->actingAs($this->owner)->get(route($route, $this->agent))->assertOk();
})->with('analytics routes');

it('rejects an end date before the start date', function (): void {
    $this->actingAs($this->owner)
        ->getJson(route('agents.analytics.data', [$this->agent, 'start_date' => '2026-06-10', 'end_date' => '2026-06-01']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('end_date');
});
