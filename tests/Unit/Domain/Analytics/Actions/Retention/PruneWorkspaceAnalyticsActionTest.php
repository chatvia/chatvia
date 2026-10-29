<?php

use App\Admin\Settings\BrandSettings;
use App\Identity\Workspace\Models\User;
use Domain\Analytics\Actions\Retention\PruneWorkspaceAnalyticsAction;
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
    'analytics_agent_daily',
    'analytics_agent_country_daily',
    'analytics_message_feedback',
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

    $db->table('analytics_events')->insert([...$base, 'message_external_id' => (string) Str::uuid7(), 'occurred_at' => $at]);
    $db->table('analytics_agent_hourly_rollups')->insert([...$base, 'bucket_hour' => $at]);
    $db->table('analytics_agent_daily')->insert([...$base, 'bucket_day' => $day]);
    $db->table('analytics_agent_country_daily')->insert([...$base, 'bucket_day' => $day, 'country_code' => 'de']);
    $db->table('analytics_message_feedback')->insert([...$base, 'message_external_id' => (string) Str::uuid7(), 'feedback' => 'positive', 'occurred_at' => $at]);
    $db->table('analytics_conversation_metrics')->insert([...$base, 'conversation_external_id' => (string) Str::uuid7(), 'started_at' => $at]);
    $db->table('analytics_session_firsts')->insert([...$base, 'session_external_id' => (string) Str::uuid7(), 'first_seen_at' => $at]);
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

beforeEach(fn () => withEnterpriseLicense());

it('deletes the workspace\'s older rows from every analytics table once, feedback included', function (): void {
    $workspace = User::factory()->create()->workspace()->id;
    pwaRetention($workspace, 60);
    pwaSeed($workspace, daysAgo: 90);
    pwaSeed($workspace, daysAgo: 10, agentId: 920002);
    $other = User::factory()->create()->workspace()->id;
    pwaSeed($other, daysAgo: 90, agentId: 920003);

    $deleted = app(PruneWorkspaceAnalyticsAction::class)->execute();

    expect($deleted)->toBe(count(PWA_TABLES))
        ->and(app(PruneWorkspaceAnalyticsAction::class)->execute())->toBe(0)
        ->and(pwaCounts($workspace))->each->toBe(1)
        ->and(pwaCounts($other))->each->toBe(1);
});

it('never uses a window below the configured floor', function (): void {
    $workspace = User::factory()->create()->workspace()->id;
    pwaRetention($workspace, 5);
    pwaSeed($workspace, daysAgo: 20);

    expect(app(PruneWorkspaceAnalyticsAction::class)->execute())->toBe(0)
        ->and(pwaCounts($workspace))->each->toBe(1);
});

it('keeps every analytics row without an Enterprise license', function (): void {
    $workspace = User::factory()->create()->workspace()->id;
    pwaRetention($workspace, 60);
    pwaSeed($workspace, daysAgo: 90);
    saveSettings(BrandSettings::class, ['license_key' => null]);

    expect(app(PruneWorkspaceAnalyticsAction::class)->execute())->toBe(0)
        ->and(pwaCounts($workspace))->each->toBe(1);
});
