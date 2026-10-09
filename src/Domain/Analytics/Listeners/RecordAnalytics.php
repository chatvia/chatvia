<?php

namespace Domain\Analytics\Listeners;

use Domain\Analytics\Actions\Recording\RecordConversationTopicAction;
use Domain\Analytics\Actions\Recording\RecordFeedbackGivenAction;
use Domain\Analytics\Actions\Recording\RecordFunctionInvocationAction;
use Domain\Analytics\Actions\Recording\RecordMcpToolInvocationAction;
use Domain\Analytics\Actions\Recording\RecordMessageAnalyzedAction;
use Domain\Analytics\Actions\Recording\RecordMessageSentAction;
use Domain\Analytics\Actions\Recording\RecordSourceRetrievalAction;
use Domain\Analytics\Data\Recording\FeedbackRecordData;
use Domain\Analytics\Data\Recording\FunctionInvocationRecordData;
use Domain\Analytics\Data\Recording\McpToolInvocationRecordData;
use Domain\Analytics\Data\Recording\MessageAnalysisRecordData;
use Domain\Analytics\Data\Recording\MessageSentRecordData;
use Domain\Analytics\Data\Recording\SourceRetrievalRecordData;
use Domain\Analytics\Data\Recording\TopicAssignmentRecordData;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Analytics\Events\FeedbackGiven;
use Domain\Analytics\Events\FunctionInvoked;
use Domain\Analytics\Events\MessageAnalyzed;
use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Events\SourceRetrieved;
use Domain\Integration\Mcp\Events\McpToolInvoked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Throwable;

/**
 * Queued subscriber that writes every analytics event into the analytics
 * tables, off the request path, on the analytics queue. Each handler turns its
 * event into a record DTO and hands it to the matching Record*Action. The
 * `chatvia.analytics.enabled` kill-switch skips all of them.
 *
 * The handler method names are part of the queued job payload, so they must
 * not be renamed while jobs may still be waiting in the queue.
 */
class RecordAnalytics implements ShouldQueue
{
    public function __construct(
        private readonly RecordMessageSentAction $recordMessageSent,
        private readonly RecordFeedbackGivenAction $recordFeedbackGiven,
        private readonly RecordMessageAnalyzedAction $recordMessageAnalyzed,
        private readonly RecordConversationTopicAction $recordConversationTopic,
        private readonly RecordSourceRetrievalAction $recordSourceRetrieval,
        private readonly RecordFunctionInvocationAction $recordFunctionInvocation,
        private readonly RecordMcpToolInvocationAction $recordMcpToolInvocation,
    ) {}

    public function viaQueue(): string
    {
        return (string) config('chatvia.analytics.queue');
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            MessageSent::class => 'onMessageSent',
            FeedbackGiven::class => 'onFeedbackGiven',
            MessageAnalyzed::class => 'onMessageAnalyzed',
            ConversationTopicAssigned::class => 'onConversationTopicAssigned',
            SourceRetrieved::class => 'onSourceRetrieved',
            FunctionInvoked::class => 'onFunctionInvoked',
            McpToolInvoked::class => 'onMcpToolInvoked',
        ];
    }

    public function onMessageSent(MessageSent $event): void
    {
        if ($this->enabled()) {
            $this->recordMessageSent->execute(MessageSentRecordData::fromEvent($event));
        }
    }

    public function onFeedbackGiven(FeedbackGiven $event): void
    {
        if ($this->enabled()) {
            $this->recordFeedbackGiven->execute(FeedbackRecordData::fromEvent($event));
        }
    }

    public function onMessageAnalyzed(MessageAnalyzed $event): void
    {
        if ($this->enabled()) {
            $this->recordMessageAnalyzed->execute(MessageAnalysisRecordData::fromEvent($event));
        }
    }

    public function onConversationTopicAssigned(ConversationTopicAssigned $event): void
    {
        if ($this->enabled()) {
            $this->recordConversationTopic->execute(TopicAssignmentRecordData::fromEvent($event));
        }
    }

    public function onSourceRetrieved(SourceRetrieved $event): void
    {
        if ($this->enabled()) {
            $this->recordSourceRetrieval->execute(SourceRetrievalRecordData::fromEvent($event));
        }
    }

    public function onFunctionInvoked(FunctionInvoked $event): void
    {
        if ($this->enabled()) {
            $this->recordFunctionInvocation->execute(FunctionInvocationRecordData::fromEvent($event));
        }
    }

    public function onMcpToolInvoked(McpToolInvoked $event): void
    {
        if ($this->enabled()) {
            $this->recordMcpToolInvocation->execute(McpToolInvocationRecordData::fromEvent($event));
        }
    }

    public function failed(object $event, Throwable $exception): void
    {
        report($exception);
    }

    private function enabled(): bool
    {
        return (bool) config('chatvia.analytics.enabled');
    }
}
