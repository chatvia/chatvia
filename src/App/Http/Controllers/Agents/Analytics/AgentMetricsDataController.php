<?php

namespace App\Http\Controllers\Agents\Analytics;

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

        // Get conversation statistics using AgentQueryBuilder methods
        $totalMessages = $agent->countAssistantMessages();
        $totalConversations = $agent->countConversations();
        $avgMessagesPerConversation = $agent->avgMessagesPerConversation();
        $uniqueSessions = $agent->uniqueSessionsCount();

        // Feedback statistics
        $satisfactionData = $agent->satisfactionRate();
        $conversationsWithFeedback = $agent->conversationsWithFeedback();
        $totalPositiveFeedback = $satisfactionData['positive'];
        $totalNegativeFeedback = $satisfactionData['negative'];

        // Performance metrics
        $avgResponseTime = $agent->avgResponseTime();
        $avgFirstResponseTime = $agent->avgFirstResponseTime();
        $avgConversationDuration = $agent->avgConversationDuration();

        // Token statistics
        $totalTokens = $agent->totalTokensUsed();
        $avgTokensPerMessage = $agent->avgTokensPerMessage();

        // Model usage statistics
        $modelUsageData = $agent->mostUsedModels()->get();
        $llms = Llm::query()
            ->whereIn('external_name', $modelUsageData->pluck('model_external_name')->toArray())
            ->get();

        $modelUsage = $modelUsageData->map(function ($item) use ($llms) {
            $model = $llms->firstWhere('external_name', $item->model_external_name);

            return new ModelUsageData(
                name: $model->descriptive_name,
                icon: $model->icon,
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
