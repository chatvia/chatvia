<?php

namespace Database\Seeders\Demo;

use App\Identity\Workspace\Models\Workspace;
use DateTimeInterface;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Recording\RecordConversationTopicAction;
use Domain\Analytics\Actions\Recording\RecordFeedbackGivenAction;
use Domain\Analytics\Actions\Recording\RecordMessageAnalyzedAction;
use Domain\Analytics\Actions\Recording\RecordMessageSentAction;
use Domain\Analytics\Data\Recording\FeedbackRecordData;
use Domain\Analytics\Data\Recording\MessageAnalysisRecordData;
use Domain\Analytics\Data\Recording\MessageSentRecordData;
use Domain\Analytics\Data\Recording\TopicAssignmentRecordData;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * Writes demo analytics through the same recording actions the app's
 * listeners use, so the demo rollups have exactly the shape of real ones.
 * Both the analytics history and the seeded chats record through here.
 */
class DemoAnalytics
{
    /** Every analytics table; each row carries its workspace. */
    private const array TABLES = [
        'analytics_events',
        'analytics_agent_hourly_rollups',
        'analytics_agent_daily',
        'analytics_agent_country_daily',
        'analytics_message_feedback',
        'analytics_conversation_metrics',
        'analytics_session_firsts',
        'analytics_agent_sentiment_daily',
        'analytics_agent_tone_daily',
        'analytics_agent_topic_daily',
        'analytics_agent_source_daily',
        'analytics_agent_function_daily',
        'analytics_agent_mcp_tool_daily',
    ];

    public function __construct(
        private readonly AnalyticsDatabase $analytics,
        private readonly RecordMessageSentAction $messageSent,
        private readonly RecordMessageAnalyzedAction $messageAnalyzed,
        private readonly RecordFeedbackGivenAction $feedbackGiven,
        private readonly RecordConversationTopicAction $topicAssigned,
    ) {}

    /** Remove the workspace's analytics, leaving every other workspace's alone. */
    public function clear(Workspace $workspace): void
    {
        foreach (self::TABLES as $table) {
            $this->analytics->table($table)->where('workspace_id', $workspace->id)->delete();
        }
    }

    /** A visitor message. */
    public function visitorMessage(Agent $agent, ?int $chatterId, string $session, string $conversation, string $message, ?string $country, DateTimeInterface $at, ?DemoClient $client = null): void
    {
        $this->messageSent->execute(new MessageSentRecordData(
            agentId: $agent->id,
            workspaceId: $agent->workspace_id,
            chatterId: $chatterId,
            conversationExternalId: $conversation,
            messageExternalId: $message,
            sessionExternalId: $session,
            role: 'user',
            llmId: null,
            countryCode: $country,
            responseTimeMs: null,
            promptTokens: null,
            completionTokens: null,
            occurredAt: $at,
            source: $client?->source,
            deviceType: $client?->deviceType,
            browser: $client?->browser,
            os: $client?->os,
        ));
    }

    /** An AI reply, answered by the agent's model. */
    public function reply(Agent $agent, ?int $chatterId, string $session, string $conversation, string $message, ?string $country, DemoReply $reply, DateTimeInterface $at, ?DemoClient $client = null): void
    {
        $this->messageSent->execute(new MessageSentRecordData(
            agentId: $agent->id,
            workspaceId: $agent->workspace_id,
            chatterId: $chatterId,
            conversationExternalId: $conversation,
            messageExternalId: $message,
            sessionExternalId: $session,
            role: 'assistant',
            llmId: $agent->llm_id,
            countryCode: $country,
            responseTimeMs: $reply->responseTimeMs,
            promptTokens: $reply->promptTokens,
            completionTokens: $reply->completionTokens,
            occurredAt: $at,
            hasFoundAnswer: $reply->foundAnswer,
            confidenceScore: $reply->confidence,
            source: $client?->source,
            deviceType: $client?->deviceType,
            browser: $client?->browser,
            os: $client?->os,
        ));
    }

    /** The sentiment and tone the message analysis found in a visitor message. */
    public function analyzed(Agent $agent, string $sentiment, string $tone, DateTimeInterface $at): void
    {
        $this->messageAnalyzed->execute(new MessageAnalysisRecordData(
            agentId: $agent->id,
            workspaceId: $agent->workspace_id,
            sentiment: $sentiment,
            tone: $tone,
            occurredAt: $at,
        ));
    }

    /** A thumbs up or down on an AI reply. */
    public function feedback(Agent $agent, ?int $chatterId, string $conversation, string $message, string $feedback, ?string $country, DateTimeInterface $at): void
    {
        $this->feedbackGiven->execute(new FeedbackRecordData(
            agentId: $agent->id,
            workspaceId: $agent->workspace_id,
            chatterId: $chatterId,
            conversationExternalId: $conversation,
            messageExternalId: $message,
            feedback: $feedback,
            llmId: $agent->llm_id,
            countryCode: $country,
            occurredAt: $at,
        ));
    }

    /** A topic the conversation analysis gave a conversation started at $at. */
    public function topic(Agent $agent, int $topicId, DateTimeInterface $at): void
    {
        $this->topicAssigned->execute(new TopicAssignmentRecordData(
            agentId: $agent->id,
            workspaceId: $agent->workspace_id,
            topicId: $topicId,
            occurredAt: $at,
        ));
    }
}
