<?php

use Domain\Agent\Actions\Analytics\BuildAgentStatsAction;
use Domain\Agent\Data\Resources\Stats\ConversationTrendData;
use Domain\Agent\Data\Resources\Stats\DailyMessagesData;
use Domain\Agent\Data\Resources\Stats\MessageByDayData;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\DateRangeData;

/**
 * The agent Analytics overview reads its numbers from the Analytics read
 * models, and its daily series follow the selected range, not the last days
 * before today.
 */
it('builds the daily series for the selected range, not the last days before today', function (): void {
    $this->travelTo('2026-09-30 12:00:00');
    $agent = Agent::factory()->create();
    analyticsRollup($agent, '2026-03-10 09:00:00', ['user_messages' => 4, 'assistant_messages' => 4, 'conversations_started' => 2]);

    $stats = app(BuildAgentStatsAction::class)->execute($agent, DateRangeData::fromDates('2026-03-09', '2026-03-11'));

    expect(array_map(static fn (ConversationTrendData $day): array => $day->toArray(), $stats->conversations_trend))
        ->toBe([['date' => '2026-03-10', 'conversations' => 2]])
        ->and(array_map(static fn (DailyMessagesData $day): array => $day->toArray(), $stats->daily_messages))
        ->toBe([['date' => '2026-03-10', 'messages' => 4]])
        ->and(array_column(array_map(static fn (MessageByDayData $day): array => $day->toArray(), $stats->messages_last_week), 'value'))
        ->toBe([0, 4, 0]);
});
