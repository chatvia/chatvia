<?php

namespace Domain\Analytics\Queries\Account\Outcomes;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Outcomes\SatisfactionStatsData;
use Domain\Analytics\Data\Account\Outcomes\SentimentSplitData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Illuminate\Support\Facades\DB;

/**
 * Quality signals: thumbs up vs down, the sentiment split of messages, how
 * often the AI could not find an answer, and its average answer confidence.
 */
class SatisfactionStatsQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope): SatisfactionStatsData
    {
        $feedback = $this->feedback($scope);

        $sentiment = $this->dailyRollup($scope, 'analytics_agent_sentiment_daily')
            ->groupBy('sentiment')
            ->selectRaw('sentiment, SUM(message_count) total')
            ->pluck('total', 'sentiment');

        $answers = DB::table('chat_messages as m')
            ->join('chat_conversations as c', 'm.chat_conversation_id', '=', 'c.id')
            ->join('chat_sessions as s', 'c.chat_session_id', '=', 's.id')
            ->whereIn('s.agent_id', $scope->agentIds)
            ->whereNull('c.deleted_at')
            ->where('m.role', 'assistant')
            ->whereBetween('m.created_at', [$scope->period->start, $scope->period->end])
            ->selectRaw('COUNT(*) total, COUNT(*) FILTER (WHERE m.has_llm_found_answer = false) not_found, AVG(m.confidence_score) avg_confidence')
            ->first();

        $aiReplies = (int) $answers->total;
        $unanswered = (int) $answers->not_found;

        return new SatisfactionStatsData(
            feedback: $feedback,
            positiveRatePct: $this->pct($feedback->positive, $feedback->rated()),
            sentiment: new SentimentSplitData(
                positive: (int) ($sentiment['positive'] ?? 0),
                neutral: (int) ($sentiment['neutral'] ?? 0),
                negative: (int) ($sentiment['negative'] ?? 0),
            ),
            aiReplies: $aiReplies,
            unanswered: $unanswered,
            unansweredRatePct: $this->pct($unanswered, $aiReplies),
            avgConfidence: $this->roundOrNull($answers->avg_confidence, 2),
        );
    }
}
