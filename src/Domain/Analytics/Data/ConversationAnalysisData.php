<?php

namespace Domain\Analytics\Data;

use Domain\Analytics\Enums\SentimentEnum;
use Domain\Analytics\Enums\ToneEnum;
use Spatie\LaravelData\Data;

class ConversationAnalysisData extends Data
{
    /**
     * @param  int[]  $topicIds  Existing topic ids that apply (already filtered to the agent's allowed set).
     * @param  string[]  $suggestedTopics  Emergent topic labels for themes no existing topic covers.
     * @param  string|null  $language  ISO 639-1 two-letter code, or null when undetected.
     */
    public function __construct(
        public array $topicIds,
        public array $suggestedTopics,
        public SentimentEnum $sentiment,
        public ToneEnum $tone,
        public ?string $language,
    ) {}
}
