<?php

use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Services\Servers\AccountAnalyticsServer;
use Domain\Integration\Mcp\Services\Tools\Analytics\GetAccountOverviewTool;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;
use Support\Enums\User\UserType;

/**
 * Seed `message_sent` rows in the granular event log (what the analytics tools
 * count, by exact occurred_at — not the hour-bucketed rollup).
 */
function seedMessageEvents(int $workspaceId, int $userMessages, int $assistantMessages): void
{
    $session = (string) Str::uuid();
    $make = fn (string $role): array => [
        'agent_id' => 1,
        'workspace_id' => $workspaceId,
        'event_type' => 'message_sent',
        'role' => $role,
        'conversation_external_id' => (string) Str::uuid(),
        'message_external_id' => (string) Str::uuid(),
        'session_external_id' => $session,
        'occurred_at' => now(),
        'inserted_at' => now(),
    ];

    $rows = [];
    for ($i = 0; $i < $userMessages; $i++) {
        $rows[] = $make('user');
    }
    for ($i = 0; $i < $assistantMessages; $i++) {
        $rows[] = $make('assistant');
    }

    if ($rows !== []) {
        DB::connection(config('chatvia.analytics.connection'))->table('analytics_events')->insert($rows);
    }
}

/**
 * Seed per-conversation metric rows (what the tools count by exact started_at).
 */
function seedConversations(int $workspaceId, int $count): void
{
    $rows = [];
    for ($i = 0; $i < $count; $i++) {
        $rows[] = [
            'conversation_external_id' => (string) Str::uuid(),
            'agent_id' => 1,
            'workspace_id' => $workspaceId,
            'started_at' => now(),
            'ended_at' => now(),
            'message_count' => 2,
            'duration_seconds' => 0,
        ];
    }

    if ($rows !== []) {
        DB::connection(config('chatvia.analytics.connection'))->table('analytics_conversation_metrics')->insert($rows);
    }
}

function callAs(User $workspace): void
{
    app(HttpRequest::class)->headers->set('X-Chatvia-Workspace-Id', $workspace->external_id);
}

it('reports account-overview numbers scoped to the workspace', function (): void {
    $workspace = User::factory()->create(['type' => UserType::Workspace]);

    seedMessageEvents($workspace->id, 15, 12);
    seedConversations($workspace->id, 5);

    callAs($workspace);

    AccountAnalyticsServer::tool(GetAccountOverviewTool::class, ['period' => 'all_time'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('incoming_messages', 15)
            ->where('ai_messages', 12)
            ->where('total_messages', 27)
            ->where('conversations', 5)
            ->etc());
});

it('counts messages that fall in a mid-hour cycle (rollup boundary regression)', function (): void {
    $this->travelTo(Carbon::create(2026, 6, 11, 17, 23, 0));

    $workspace = User::factory()->create(['type' => UserType::Workspace]);

    // Cycle starts 10 min into the hour (17:10) — the partial first hour the
    // hour-bucketed rollup (bucket 17:00) dropped because 17:00 < 17:10. Events
    // use exact occurred_at (17:23), so they count.
    $workspace->credits()->create([
        'messages' => 1000,
        'started_at' => now()->startOfHour()->addMinutes(10),
        'ends_at' => now()->addMonth(),
    ]);
    seedMessageEvents($workspace->id, 2, 2); // occurred_at = 17:23, in-cycle

    callAs($workspace);

    AccountAnalyticsServer::tool(GetAccountOverviewTool::class, ['period' => 'current_cycle'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('incoming_messages', 2)
            ->where('ai_messages', 2)
            ->etc());
});

it('never leaks another workspace\'s numbers', function (): void {
    $a = User::factory()->create(['type' => UserType::Workspace]);
    $b = User::factory()->create(['type' => UserType::Workspace]);

    seedMessageEvents($b->id, 99, 77);
    seedConversations($b->id, 42);

    callAs($a);

    AccountAnalyticsServer::tool(GetAccountOverviewTool::class, ['period' => 'all_time'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json): AssertableJson => $json
            ->where('incoming_messages', 0)
            ->where('ai_messages', 0)
            ->where('conversations', 0)
            ->etc());
});

it('errors when no workspace identity is present', function (): void {
    app(HttpRequest::class)->headers->remove('X-Chatvia-Workspace-Id');

    AccountAnalyticsServer::tool(GetAccountOverviewTool::class, ['period' => 'all_time'])
        ->assertHasErrors(['workspace']);
});
