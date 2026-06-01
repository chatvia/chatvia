<?php

namespace App\Http\Controllers\Agents\Analytics\Metrics;

use Domain\Agents\Data\Stats\AgentMetricsData;
use Domain\Agents\Data\Stats\ModelFeedbackData;
use Domain\Agents\Data\Stats\ModelUsageData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Llm;
use Illuminate\Http\Request;

class AgentMetricsDataController
{
    public function __invoke(Request $request, Agent $agent): AgentMetricsData
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        // Single aggregate replaces ~12 separate query/join chains.
        $agg = $agent->aggregateStats();

        $totalMessages = (int) ($agg->total_assistant ?? 0);
        $totalConversations = (int) ($agg->total_conversations ?? 0);
        $avgMessagesPerConversation = $agg->avg_messages_per_conversation !== null
            ? round((float) $agg->avg_messages_per_conversation, 1)
            : 0;
        $uniqueSessions = (int) ($agg->unique_sessions ?? 0);

        $totalPositiveFeedback = (int) ($agg->positive_feedback ?? 0);
        $totalNegativeFeedback = (int) ($agg->negative_feedback ?? 0);
        $conversationsWithFeedback = (int) ($agg->conversations_with_feedback ?? 0);

        $avgResponseTime = $agg->avg_response_ms !== null ? ((float) $agg->avg_response_ms) / 1000 : null;
        $avgFirstResponseTime = $agg->avg_first_response_seconds !== null
            ? (float) $agg->avg_first_response_seconds
            : null;
        $avgConversationDuration = $agg->avg_conversation_duration_seconds !== null
            ? (float) $agg->avg_conversation_duration_seconds
            : null;

        $totalTokens = $agg->total_tokens !== null ? (int) $agg->total_tokens : null;
        $avgTokensPerMessage = $agg->avg_tokens !== null ? (int) round((float) $agg->avg_tokens) : null;

        // Model usage statistics (still its own grouped query)
        $modelUsageData = $agent->mostUsedModels()->get();
        $llms = Llm::query()
            ->whereIn('external_name', $modelUsageData->pluck('model_external_name')->toArray())
            ->get();

        $modelUsage = $modelUsageData->map(function ($item) use ($llms) {
            $model = $llms->firstWhere('external_name', $item->model_external_name);

            // Null-safe: an llm row may have been renamed or removed since the
            // rollup was written. Fall back to the raw external name.
            return new ModelUsageData(
                name: $model?->descriptive_name ?? $item->model_external_name,
                icon: $model?->icon,
                count: $item->usage_count,
                percentage: $item->percentage,
                feedback: new ModelFeedbackData(
                    positive: $item->positive_feedback,
                    negative: $item->negative_feedback,
                ),
            );
        })->all();

        return new AgentMetricsData(
            totalMessages: $totalMessages,
            totalConversations: $totalConversations,
            avgMessagesPerConversation: $avgMessagesPerConversation,
            uniqueSessions: $uniqueSessions,
            conversationsWithFeedback: $conversationsWithFeedback,
            totalPositiveFeedback: $totalPositiveFeedback,
            totalNegativeFeedback: $totalNegativeFeedback,
            avgResponseTime: $avgResponseTime ? round($avgResponseTime, 2) : 0,
            avgFirstResponseTime: $avgFirstResponseTime ? round($avgFirstResponseTime, 2) : null,
            avgConversationDuration: $avgConversationDuration ? round($avgConversationDuration, 2) : null,
            totalTokens: $totalTokens,
            avgTokensPerMessage: $avgTokensPerMessage,
            modelUsage: $modelUsage,
        );
    }
}
