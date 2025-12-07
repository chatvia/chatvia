<?php

namespace App\Data\Agent\Stats;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class AgentMetricsData extends Data
{
    /**
     * @param  ModelUsageData[]  $modelUsage
     */
    public function __construct(
        public int $totalMessages,
        public int $totalConversations,
        public ?float $avgMessagesPerConversation,
        public int $uniqueSessions,
        public int $conversationsWithFeedback,
        public int $totalPositiveFeedback,
        public int $totalNegativeFeedback,
        public float $avgResponseTime,
        public ?float $avgFirstResponseTime,
        public ?float $avgConversationDuration,
        public ?int $totalTokens,
        public ?float $avgTokensPerMessage,
        #[DataCollectionOf(ModelUsageData::class)]
        public array $modelUsage,
    ) {}
}
