<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use App\Mcp\McpCallerContext;
use App\Mcp\McpCallerResolver;
use App\Mcp\Servers\AccountAnalyticsServer;
use App\Mcp\Tools\Analytics\GetAccountOverviewTool;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\Fluent\AssertableJson;

/**
 * Seed messages in the per-agent hourly rollup (what the analytics tools sum
 * for message counts), in the current hour.
 */
function seedMessageRollup(int $workspaceId, int $userMessages, int $assistantMessages): void
{
    DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_hourly_rollups')->insert([
        'agent_id' => 1,
        'workspace_id' => $workspaceId,
        'bucket_hour' => now()->startOfHour(),
        'country_code' => '--',
        'llm_external_name' => 'qwen3',
        'user_messages' => $userMessages,
        'assistant_messages' => $assistantMessages,
    ]);
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

function callAs(?Workspace $workspace): void
{
    $request = Request::create('/mcp/account-analytics', 'POST');
    if ($workspace instanceof Workspace) {
        $request->headers->set('X-Chatvia-Workspace-Id', $workspace->external_id);
    }

    app()->instance(McpCallerContext::class, app(McpCallerResolver::class)->resolve($request));
}

it('reports account-overview numbers scoped to the workspace', function (): void {
    $workspace = User::factory()->create()->workspace();

    seedMessageRollup($workspace->id, 15, 12);
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

it('never leaks another workspace\'s numbers', function (): void {
    $a = User::factory()->create()->workspace();
    $b = User::factory()->create()->workspace();

    seedMessageRollup($b->id, 99, 77);
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
    callAs(null);

    AccountAnalyticsServer::tool(GetAccountOverviewTool::class, ['period' => 'all_time'])
        ->assertHasErrors(['workspace']);
});
