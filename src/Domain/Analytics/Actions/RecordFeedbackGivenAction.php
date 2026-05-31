<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Events\FeedbackGivenEvent;
use Illuminate\Support\Facades\DB;

class RecordFeedbackGivenAction
{
    public function execute(FeedbackGivenEvent $event): void
    {
        // Rollup semantics: only count NEW positive/negative reactions. Clearing
        // feedback (any → null) records the event but does NOT decrement the
        // rollup, so totals survive both deletion and toggle. Switching
        // positive↔negative records the new direction as a fresh +1; the prior
        // reaction stays counted as the user's original opinion. Yields
        // "lifetime reactions given" rather than "current snapshot count".
        $positiveDelta = $event->newFeedback === 'positive' && $event->priorFeedback !== 'positive' ? 1 : 0;
        $negativeDelta = $event->newFeedback === 'negative' && $event->priorFeedback !== 'negative' ? 1 : 0;

        $connection = config('analytics.connection');
        $countrySentinel = config('analytics.unknown_country_code');
        $llmSentinel = config('analytics.unknown_llm_external_name');

        $db = DB::connection($connection);

        $db->transaction(function () use (
            $db, $event, $countrySentinel, $llmSentinel,
            $positiveDelta, $negativeDelta
        ): void {
            $db->table('analytics_events')->insert([
                'agent_id' => $event->agentId,
                'event_type' => 'feedback_given',
                'feedback' => $event->newFeedback,
                'prior_feedback' => $event->priorFeedback,
                'llm_external_name' => $event->llmExternalName,
                'country_code' => $event->countryCode,
                'conversation_external_id' => $event->conversationExternalId,
                'message_external_id' => $event->messageExternalId,
                'occurred_at' => $event->occurredAt,
            ]);

            if ($positiveDelta || $negativeDelta) {
                $db->statement(<<<'SQL'
                    INSERT INTO analytics_agent_hourly_rollups (
                        agent_id, bucket_hour, country_code, llm_external_name,
                        positive_feedback, negative_feedback
                    ) VALUES (
                        ?, date_trunc('hour', ?::timestamptz), ?, ?,
                        ?, ?
                    )
                    ON CONFLICT (agent_id, bucket_hour, country_code, llm_external_name) DO UPDATE SET
                        positive_feedback = analytics_agent_hourly_rollups.positive_feedback + EXCLUDED.positive_feedback,
                        negative_feedback = analytics_agent_hourly_rollups.negative_feedback + EXCLUDED.negative_feedback
                SQL, [
                    $event->agentId,
                    $event->occurredAt,
                    $event->countryCode ?? $countrySentinel,
                    $event->llmExternalName ?? $llmSentinel,
                    $positiveDelta,
                    $negativeDelta,
                ]);
            }

            if ($event->newFeedback !== null) {
                $db->table('analytics_conversation_metrics')
                    ->where('conversation_external_id', $event->conversationExternalId)
                    ->update(['had_feedback' => true]);
            }
        });
    }
}
