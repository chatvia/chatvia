<?php

use Database\Factories\AgentFileFactory;
use Domain\Agent\Actions\Analytics\BuildAgentStatsAction;
use Domain\Agent\Data\Resources\Stats\ConversationTrendData;
use Domain\Agent\Data\Resources\Stats\DailyMessagesData;
use Domain\Agent\Data\Resources\Stats\MessageByDayData;
use Domain\Agent\Data\Resources\Stats\TopKnowledgeData;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\DateRangeData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The agent Analytics overview. Its numbers come from the Analytics read
 * models; these tests pin the shaping: document types of the top files, the
 * daily series following the selected range, and the full payload keys.
 */
function basRollup(Agent $agent, string $hour, int $replies, int $conversations): void
{
    DB::connection(config('chatvia.analytics.connection'))->table('analytics_agent_hourly_rollups')->insert([
        'agent_id' => $agent->id,
        'workspace_id' => $agent->workspace_id,
        'bucket_hour' => $hour,
        'country_code' => '--',
        'llm_external_name' => 'test-model',
        'user_messages' => $replies,
        'assistant_messages' => $replies,
        'conversations_started' => $conversations,
    ]);
}

it('reports the document classification of the most accessed files', function (): void {
    $agent = Agent::factory()->create();

    $classified = AgentFileFactory::new()->create(['filename' => 'pricing.pdf', 'document_classification' => 'pricing_sheet']);
    $classified->agents()->attach($agent->id, ['retrieval_count' => 7]);

    $unclassified = AgentFileFactory::new()->create(['filename' => 'notes.txt', 'document_classification' => null]);
    $unclassified->agents()->attach($agent->id, ['retrieval_count' => 2]);

    $stats = app(BuildAgentStatsAction::class)->execute($agent, DateRangeData::fromDates(null, null));

    expect(array_map(static fn (TopKnowledgeData $file): array => $file->toArray(), $stats->top_knowledge))->toBe([
        ['name' => 'pricing.pdf', 'type' => 'pricing_sheet', 'accessed' => 7],
        ['name' => 'notes.txt', 'type' => 'unknown', 'accessed' => 2],
    ]);
});

it('builds the full analytics payload for an agent', function (): void {
    $agent = Agent::factory()->create();

    $payload = app(BuildAgentStatsAction::class)->execute($agent, DateRangeData::fromDates(null, null))->toArray();

    expect($payload)->toHaveKeys(['total_messages', 'top_knowledge', 'feedback'])
        ->and($payload['activity_by_hour'])->toHaveCount(24)
        ->and($payload['activity_by_day_of_week'])->toHaveCount(7)
        ->and($payload['messages_last_week'])->toHaveCount(7);
});

it('builds the daily series for the selected range, not the last days before today', function (): void {
    Carbon::setTestNow('2026-09-30 12:00:00');
    $agent = Agent::factory()->create();

    // Activity in an earlier month, inside the selected range.
    basRollup($agent, '2026-03-10 09:00:00', replies: 4, conversations: 2);

    $stats = app(BuildAgentStatsAction::class)->execute($agent, DateRangeData::fromDates('2026-03-09', '2026-03-11'));

    expect(array_map(static fn (ConversationTrendData $day): array => $day->toArray(), $stats->conversations_trend))
        ->toBe([['date' => '2026-03-10', 'conversations' => 2]])
        ->and(array_map(static fn (DailyMessagesData $day): array => $day->toArray(), $stats->daily_messages))
        ->toBe([['date' => '2026-03-10', 'messages' => 4]])
        ->and(array_column(array_map(static fn (MessageByDayData $day): array => $day->toArray(), $stats->messages_last_week), 'value'))
        ->toBe([0, 4, 0]);
});
