<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Account\Outcomes;

use Domain\Analytics\Data\Account\FeedbackTallyData;

/**
 * Quality signals over a window: thumbs up/down, sentiment, how often the AI
 * could not find an answer, and its average answer confidence.
 */
final readonly class SatisfactionStatsData
{
    public function __construct(
        public FeedbackTallyData $feedback,
        public float $positiveRatePct,
        public SentimentSplitData $sentiment,
        public int $aiReplies,
        public int $unanswered,
        public float $unansweredRatePct,
        public int|float|null $avgConfidence,
    ) {}
}
