<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Agent\Activity;

/**
 * An agent's all-time headline numbers: message and conversation counts,
 * response times, tokens, feedback, conversation shape, the busiest hour, and
 * the first day with data. Averages are null when there is nothing to average.
 */
final readonly class AgentTotalsData
{
    public function __construct(
        public int $assistantMessages = 0,
        public int $userMessages = 0,
        public int $conversations = 0,
        public ?float $avgResponseMs = null,
        public int $totalTokens = 0,
        public ?float $avgTokensPerReply = null,
        public int $positiveFeedback = 0,
        public int $negativeFeedback = 0,
        public int $conversationsWithFeedback = 0,
        public ?float $avgMessagesPerConversation = null,
        public ?float $avgConversationSeconds = null,
        public ?float $avgFirstResponseSeconds = null,
        public ?int $peakHour = null,
        public ?string $firstDataDate = null,
        public int $uniqueSessions = 0,
    ) {}
}
