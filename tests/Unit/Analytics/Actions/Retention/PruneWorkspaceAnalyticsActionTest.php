<?php

use App\Identity\Workspace\Models\User;
use Domain\Analytics\Actions\Retention\PruneWorkspaceAnalyticsAction;
use Domain\Analytics\Enums\AnalyticsEventType;
use Domain\Analytics\Services\AnalyticsDatabase;
use Domain\Security\Models\WorkspaceSecurityPolicy;
use Illuminate\Support\Str;

/**
 * Workspace analytics retention: every analytics table loses that workspace's
 * rows older than its window; other workspaces and newer rows stay.
 */
const PWA_TABLES = [
    'analytics_events',
    'analytics_agent_hourly_rollups',
    'analytics_conversation_metrics',
    'analytics_session_firsts',
    'analytics_agent_sentiment_daily',
    'analytics_agent_tone_daily',
    'analytics_agent_topic_daily',
    'analytics_agent_source_daily',
    'analytics_agent_function_daily',
    'analytics_agent_mcp_tool_daily',
];

/** One row in every analytics table for the workspace, dated $daysAgo. */
function pwaSeed(int $workspaceId, int $daysAgo, int $agentId = 920001): void
{
    $db = app(AnalyticsDatabase::class);
    $at = now()->subDays($daysAgo)->startOfHour();
    $day = $at->toDateString();
    $base = ['agent_id' => $agentId, 'workspace_id' => $workspaceId];
    $unique = random_int(1, PHP_INT_MAX);

    $db->table('analytics_events')->insert([...$base, 'event_type' => AnalyticsEventType::FeedbackGiven->value, 'message_external_id' => (string) Str::uuid(), 'occurred_at' => $at]);
    $db->table('analytics_agent_hourly_rollups')->insert([...$base, 'bucket_hour' => $at, 'country_code' => '--', 'llm_external_name' => '']);
    $db->table('analytics_conversation_metrics')->insert([...$base, 'conversation_external_id' => (string) Str::uuid(), 'started_at' => $at]);
    $db->table('analytics_session_firsts')->insert([...$base, 'session_external_id' => (string) Str::uuid(), 'first_seen_at' => $at]);
    $db->table('analytics_agent_sentiment_daily')->insert([...$base, 'bucket_day' => $day, 'sentiment' => 'positive']);
    $db->table('analytics_agent_tone_daily')->insert([...$base, 'bucket_day' => $day, 'tone' => 'friendly']);
    $db->table('analytics_agent_topic_daily')->insert([...$base, 'bucket_day' => $day, 'topic_id' => $unique]);
    $db->table('analytics_agent_source_daily')->insert([...$base, 'bucket_day' => $day, 'sourceable_type' => 'source_file', 'sourceable_id' => $unique]);
    $db->table('analytics_agent_function_daily')->insert([...$base, 'bucket_day' => $day, 'function_id' => $unique]);
    $db->table('analytics_agent_mcp_tool_daily')->insert([...$base, 'bucket_day' => $day, 'server_slug' => 'charts', 'tool_name' => 'tool-'.$unique]);
}

/** @return array<string, int> rows per analytics table for the workspace */
function pwaCounts(int $workspaceId): array
{
    $db = app(AnalyticsDatabase::class);

    return collect(PWA_TABLES)
        ->mapWithKeys(fn (string $table): array => [$table => $db->table($table)->where('workspace_id', $workspaceId)->count()])
        ->all();
}

function pwaRetention(int $workspaceId, ?int $days): void
{
    WorkspaceSecurityPolicy::forWorkspace($workspaceId)->update(['retention_analytics_days' => $days]);
}

it('deletes the workspace\'s older rows from every analytics table, feedback included', function (): void {
    $workspace = User::factory()->create()->workspace()->id;
    pwaRetention($workspace, 60);
    pwaSeed($workspace, daysAgo: 90);
    pwaSeed($workspace, daysAgo: 10, agentId: 920002);

    $other = User::factory()->create()->workspace()->id; // no analytics retention set
    pwaSeed($other, daysAgo: 90, agentId: 920003);

    $deleted = app(PruneWorkspaceAnalyticsAction::class)->execute();

    expect($deleted)->toBe(count(PWA_TABLES))
        ->and(pwaCounts($workspace))->each->toBe(1)
        ->and(pwaCounts($other))->each->toBe(1);
});

it('deletes in small batches and is idempotent', function (): void {
    $workspace = User::factory()->create()->workspace()->id;
    pwaRetention($workspace, 30);
    foreach ([40, 50, 60] as $index => $daysAgo) {
        pwaSeed($workspace, $daysAgo, agentId: 921000 + $index);
    }

    expect(app(PruneWorkspaceAnalyticsAction::class)->execute(batchSize: 2))->toBe(3 * count(PWA_TABLES))
        ->and(app(PruneWorkspaceAnalyticsAction::class)->execute(batchSize: 2))->toBe(0)
        ->and(pwaCounts($workspace))->each->toBe(0);
});

it('never uses a window below the configured floor', function (): void {
    $workspace = User::factory()->create()->workspace()->id;
    pwaRetention($workspace, 5); // below the 30-day floor
    pwaSeed($workspace, daysAgo: 20);

    expect(app(PruneWorkspaceAnalyticsAction::class)->execute())->toBe(0)
        ->and(pwaCounts($workspace))->each->toBe(1);
});

it('runs from the app:prune-workspace-analytics command', function (): void {
    $workspace = User::factory()->create()->workspace()->id;
    pwaRetention($workspace, 30);
    pwaSeed($workspace, daysAgo: 45);

    $this->artisan('app:prune-workspace-analytics', ['--batch' => 1])->assertSuccessful();

    expect(pwaCounts($workspace))->each->toBe(0);
});
