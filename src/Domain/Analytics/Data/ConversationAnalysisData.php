<?php

declare(strict_types=1);

namespace Domain\Analytics\Data;

use Domain\Analytics\Enums\Sentiment;
use Domain\Analytics\Enums\Tone;
use Spatie\LaravelData\Data;

/**
 * What the analysis model found in a conversation (or one message): the
 * agent's topics that apply, new topic suggestions, sentiment, tone and
 * language.
 */
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
        public Sentiment $sentiment,
        public Tone $tone,
        public ?string $language,
    ) {}
}
