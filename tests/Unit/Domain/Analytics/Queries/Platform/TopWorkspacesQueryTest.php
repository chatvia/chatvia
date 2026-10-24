<?php

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Data\Platform\TopWorkspaceData;
use Domain\Analytics\Queries\Platform\TopWorkspacesQuery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Analytics\PlatformScopes;

/**
 * The admin dashboard's busiest workspaces in the window, with their owner's
 * email, traffic and agent count; one deleted since is still counted, without
 * a name. Runs in a future month no seeded row falls in.
 */
beforeEach(function (): void {
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
});

it('ranks the busiest workspaces, naming a deleted one as such', function (): void {
    $acme = User::factory()->workspace()->create(['name' => 'Acme']);
    analyticsRollup(createAgent($acme, ['name' => 'Sales']), '2031-03-10 10:00:00', ['user_messages' => 10, 'assistant_messages' => 10, 'conversations_started' => 4]);
    analyticsRollup(createAgent($acme, ['name' => 'Support']), '2031-03-11 10:00:00', ['user_messages' => 1, 'assistant_messages' => 1, 'conversations_started' => 1]);
    analyticsRollup(createAgent(User::factory()->workspace()->create(['name' => 'Globex'])), '2031-03-12 10:00:00', ['user_messages' => 6, 'assistant_messages' => 6, 'conversations_started' => 2]);

    // Traffic of a workspace (and agent) deleted since: no rows to name it by.
    DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_hourly_rollups')->insert([
        'agent_id' => 999_999_001, 'workspace_id' => 999_999_002, 'bucket_hour' => '2031-03-12 10:00:00',
        'country_code' => '--', 'llm_id' => 0, 'user_messages' => 1, 'assistant_messages' => 0,
    ]);

    $workspaces = app(TopWorkspacesQuery::class)->get(PlatformScopes::of());

    expect(collect($workspaces)->map(fn (TopWorkspaceData $row): ?string => $row->name)->all())->toBe(['Acme', 'Globex', null])
        ->and($workspaces[0]->email)->toBe($acme->email)
        ->and($workspaces[2]->email)->toBeNull()
        ->and($workspaces[0]->messages)->toBe(22)
        ->and($workspaces[0]->conversations)->toBe(5)
        ->and($workspaces[0]->agents)->toBe(2);
});

it('counts an admin\'s own workspace as a workspace', function (): void {
    $admin = User::factory()->admin()->create(['name' => 'Staff']);
    analyticsRollup(createAgent($admin), '2031-03-10 10:00:00', ['user_messages' => 3, 'assistant_messages' => 3]);

    $workspaces = app(TopWorkspacesQuery::class)->get(PlatformScopes::of());

    expect($workspaces[0]->name)->toBe('Staff')
        ->and($workspaces[0]->workspaceId)->toBe($admin->workspace()->id)
        ->and($workspaces[0]->email)->toBe($admin->email);
});
