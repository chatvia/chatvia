<?php

namespace Domain\Analytics\Queries\Account\Outcomes;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\Outcomes\SatisfactionStatsData;
use Domain\Analytics\Data\Account\Outcomes\SentimentSplitData;
use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Queries\Account\Concerns\ReadsHourlyRollups;

/**
 * Quality signals: thumbs up vs down, the sentiment split of messages, how
 * often the AI could not find an answer, and its average answer confidence.
 * The AI reply figures come from the hourly rollup, which counts each reply
 * when it is recorded, so they need no scan of the chat log.
 */
class SatisfactionStatsQuery extends AccountAnalyticsQuery
{
    use ReadsHourlyRollups;

    public function get(AnalyticsScopeData $scope): SatisfactionStatsData
    {
        $feedback = $this->feedback($scope);

        $sentiment = $this->dailyRollup($scope, 'analytics_agent_sentiment_daily')
            ->groupBy('sentiment')
            ->selectRaw('sentiment, SUM(message_count) total')
            ->pluck('total', 'sentiment');

        $answers = $this->rollupHours($scope)
            ->selectRaw('COALESCE(SUM(assistant_messages), 0) total, COALESCE(SUM(unanswered_replies), 0) not_found, SUM(confidence_sum) / NULLIF(SUM(confidence_count), 0) avg_confidence')
            ->first();

        $aiReplies = (int) $answers->total;
        $unanswered = (int) $answers->not_found;

        return new SatisfactionStatsData(
            feedback: $feedback,
            positiveRatePct: $this->pct($feedback->positive, $feedback->rated()),
            sentiment: new SentimentSplitData(
                positive: (int) ($sentiment[Sentiment::Positive->value] ?? 0),
                neutral: (int) ($sentiment[Sentiment::Neutral->value] ?? 0),
                negative: (int) ($sentiment[Sentiment::Negative->value] ?? 0),
            ),
            aiReplies: $aiReplies,
            unanswered: $unanswered,
            unansweredRatePct: $this->pct($unanswered, $aiReplies),
            avgConfidence: $this->roundOrNull($answers->avg_confidence, 2),
        );
    }
}
