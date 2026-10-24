<?php

use App\Admin\Widgets\WorkspaceStatsOverview;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

/**
 * The numbers on a workspace's page in the admin panel: this month's
 * messages and tool calls, and how much of its message limit is used.
 */
beforeEach(function (): void {
    // A future month no seeded row falls in.
    $this->travelTo(Carbon::parse('2031-03-20 15:30:00', 'UTC'));
    $this->admin = adminUser();
    $this->workspace = User::factory()->create()->workspace();
});

it('shows this month\'s numbers, the limit and the tool calls', function (): void {
    $agent = createAgent($this->workspace->owner);
    analyticsRollup($agent, '2031-03-10 10:00:00', ['user_messages' => 600, 'assistant_messages' => 634, 'conversations_started' => 77]);
    // Last month: not counted.
    analyticsRollup($agent, '2031-02-10 10:00:00', ['user_messages' => 5, 'assistant_messages' => 5]);

    $analytics = DB::connection(config('chatvia.analytics.connection'));
    $base = ['agent_id' => $agent->id, 'workspace_id' => $agent->workspace_id];
    $analytics->table('analytics_agent_function_daily')->insert([...$base, 'bucket_day' => '2031-03-11', 'function_id' => 1, 'invocation_count' => 4]);
    $analytics->table('analytics_agent_mcp_tool_daily')->insert([...$base, 'bucket_day' => '2031-03-12', 'server_slug' => 'charts', 'tool_name' => 'bar', 'invocation_count' => 3]);
    $analytics->table('analytics_agent_mcp_tool_daily')->insert([...$base, 'bucket_day' => '2031-02-12', 'server_slug' => 'charts', 'tool_name' => 'bar', 'invocation_count' => 50]);

    MessageLimit::factory()->forWorkspace($this->workspace)->create(['monthly_limit' => 1000, 'messages_used' => 250]);

    Livewire::actingAs($this->admin, 'admin')
        ->test(WorkspaceStatsOverview::class, ['record' => $this->workspace])
        ->assertSee('1,234')
        ->assertSee('250 / 1,000')
        ->assertSee('25% of agent replies used, restarts on 1 April')
        ->assertSeeInOrder(['Tool calls', '7']);
});

it('says when a workspace has no message limit', function (): void {
    Livewire::actingAs($this->admin, 'admin')
        ->test(WorkspaceStatsOverview::class, ['record' => $this->workspace])
        ->assertSee('No limit');
});
