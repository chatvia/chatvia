<?php

declare(strict_types=1);

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
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Throwable;

/**
 * Queued subscriber that writes every analytics event into the analytics
 * tables, off the request path, on the records queue (quick writes only, so
 * the counters never wait behind the slow AI analysis on the analytics queue). Each handler turns its
 * event into a record DTO and hands it to the matching Record*Action. The
 * `chatvia.analytics.enabled` kill-switch skips all of them.
 *
 * The handler method names are part of the queued job payload, so they must
 * not be renamed while jobs may still be waiting in the queue.
 *
 * A failed write is retried: every Record*Action is one statement or one
 * transaction, so a failure leaves nothing behind, and the message write is
 * idempotent on its event row. Retries ride out lock timeouts and deadlocks
 * on the busy rollup rows instead of dropping the event.
 */
// A failed write leaves nothing behind, so another try is always safe.
#[Tries(5)]
// Longer and longer waits, so a locked rollup row is free by the next try.
#[Backoff(5, 30, 120)]
class RecordAnalytics implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    public function __construct(
        private readonly RecordMessageSentAction $recordMessageSent,
        private readonly RecordFeedbackGivenAction $recordFeedbackGiven,
        private readonly RecordMessageAnalyzedAction $recordMessageAnalyzed,
        private readonly RecordConversationTopicAction $recordConversationTopic,
        private readonly RecordSourceRetrievalAction $recordSourceRetrieval,
        private readonly RecordFunctionInvocationAction $recordFunctionInvocation,
        private readonly RecordMcpToolInvocationAction $recordMcpToolInvocation,
    ) {}

    // The queue name comes from config, which an attribute cannot read.
    public function viaQueue(): string
    {
        return (string) config('chatvia.analytics.record_queue', 'records');
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
