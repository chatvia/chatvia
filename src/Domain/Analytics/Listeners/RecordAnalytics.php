<?php

namespace Domain\Analytics\Listeners;

use Domain\Analytics\Actions\RecordConversationTopicAction;
use Domain\Analytics\Actions\RecordFeedbackGivenAction;
use Domain\Analytics\Actions\RecordFunctionInvocationAction;
use Domain\Analytics\Actions\RecordMcpToolInvocationAction;
use Domain\Analytics\Actions\RecordMessageAnalyzedAction;
use Domain\Analytics\Actions\RecordMessageSentAction;
use Domain\Analytics\Actions\RecordSourceRetrievalAction;
use Domain\Analytics\Events\ConversationTopicAssigned;
use Domain\Analytics\Events\FeedbackGiven;
use Domain\Analytics\Events\FunctionInvoked;
use Domain\Analytics\Events\McpToolInvoked;
use Domain\Analytics\Events\MessageAnalyzed;
use Domain\Analytics\Events\MessageSent;
use Domain\Analytics\Events\SourceRetrieved;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Events\Dispatcher;
use Throwable;

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
        if (! config('chatvia.analytics.enabled')) {
            return;
        }

        $this->recordMessageSent->execute($event);
    }

    public function onFeedbackGiven(FeedbackGiven $event): void
    {
        if (! config('chatvia.analytics.enabled')) {
            return;
        }

        $this->recordFeedbackGiven->execute($event);
    }

    public function onMessageAnalyzed(MessageAnalyzed $event): void
    {
        if (! config('chatvia.analytics.enabled')) {
            return;
        }

        $this->recordMessageAnalyzed->execute($event);
    }

    public function onConversationTopicAssigned(ConversationTopicAssigned $event): void
    {
        if (! config('chatvia.analytics.enabled')) {
            return;
        }

        $this->recordConversationTopic->execute($event);
    }

    public function onSourceRetrieved(SourceRetrieved $event): void
    {
        if (! config('chatvia.analytics.enabled')) {
            return;
        }

        $this->recordSourceRetrieval->execute($event);
    }

    public function onFunctionInvoked(FunctionInvoked $event): void
    {
        if (! config('chatvia.analytics.enabled')) {
            return;
        }

        $this->recordFunctionInvocation->execute($event);
    }

    public function onMcpToolInvoked(McpToolInvoked $event): void
    {
        if (! config('chatvia.analytics.enabled')) {
            return;
        }

        $this->recordMcpToolInvocation->execute($event);
    }

    public function failed(object $event, Throwable $exception): void
    {
        report($exception);
    }
}
