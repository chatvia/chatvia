<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Queries\Platform\AgentActivityQuery;
use Illuminate\Support\Carbon;
use Tests\Support\Analytics\PlatformScopes;

/**
 * The admin panel's agent list: each agent's traffic in the window and the
 * last hour it had any traffic at all, which reads only the agent's newest
 * rollup row.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
});

it('gives each agent its window traffic and its last active hour, with zeros for a quiet one', function (): void {
    $workspace = User::factory()->create()->workspace();
    $busy = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $old = Agent::factory()->create(['workspace_id' => $workspace->id]);
    $quiet = Agent::factory()->create(['workspace_id' => $workspace->id]);

    analyticsRollup($busy, '2031-03-10 10:00:00', ['user_messages' => 2, 'assistant_messages' => 3, 'conversations_started' => 1]);
    analyticsRollup($busy, '2031-03-19 08:00:00', ['user_messages' => 1, 'assistant_messages' => 1]);
    analyticsRollup($busy, '2030-11-02 09:00:00', ['user_messages' => 7]);
    analyticsRollup($old, '2030-12-24 18:00:00', ['user_messages' => 4]);

    $activity = app(AgentActivityQuery::class)->get(PlatformScopes::of(workspaceId: $workspace->id), [$busy->id, $old->id, $quiet->id]);

    expect(array_keys($activity))->toBe([$busy->id, $old->id, $quiet->id])
        ->and($activity[$busy->id]->messages)->toBe(7)
        ->and($activity[$busy->id]->conversations)->toBe(1)
        ->and($activity[$busy->id]->lastActiveAt?->toDateTimeString())->toBe('2031-03-19 08:00:00')
        ->and($activity[$old->id]->messages)->toBe(0)
        ->and($activity[$old->id]->lastActiveAt?->toDateTimeString())->toBe('2030-12-24 18:00:00')
        ->and($activity[$quiet->id]->messages)->toBe(0)
        ->and($activity[$quiet->id]->lastActiveAt)->toBeNull();
});

it('finds the last active hour install-wide too, and nothing for no agents', function (): void {
    $agent = Agent::factory()->create();
    analyticsRollup($agent, '2031-01-05 07:00:00', ['user_messages' => 1]);

    $query = app(AgentActivityQuery::class);

    expect($query->get(PlatformScopes::of(), [$agent->id])[$agent->id]->lastActiveAt?->toDateTimeString())->toBe('2031-01-05 07:00:00')
        ->and($query->get(PlatformScopes::of(), []))->toBe([])
        // Another workspace's scope does not see the agent's rows.
        ->and($query->get(PlatformScopes::of(workspaceId: $agent->workspace_id + 1000000), [$agent->id])[$agent->id]->lastActiveAt)->toBeNull();
});
